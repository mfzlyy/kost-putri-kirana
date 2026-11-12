<?php

namespace App\Models;

use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Exceptions\PenerbitanTagihanDitolak;
use App\Exceptions\TagihanTidakBisaDiberiBukti;
use App\Support\Tagihan\HasilPenerbitan;
use App\Support\Tagihan\PenghitungNominal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TagihanFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * @property int $id
 * @property int $kontrak_id
 * @property CarbonImmutable $periode
 * @property string $nominal
 * @property CarbonImmutable $jatuh_tempo
 * @property StatusTagihan $status_tagihan
 * @property-read int $hari_terlambat
 */
class Tagihan extends Model
{
    /** @use HasFactory<TagihanFactory> */
    use HasFactory;

    /**
     * Tipe MIME bukti bayar yang diterima (ASM-P01) dan ekstensi penyimpanannya.
     */
    public const EKSTENSI_BUKTI = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    protected $table = 'tagihan';

    public $timestamps = false;

    protected $fillable = [
        'kontrak_id',
        'periode',
        'nominal',
        'jatuh_tempo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periode' => 'immutable_date',
            'nominal' => 'decimal:2',
            'jatuh_tempo' => 'immutable_date',
            'status_tagihan' => StatusTagihan::class,
        ];
    }

    /**
     * Tagihan tunggakan pada $tanggal (ATB-05). Tidak disimpan, selalu dihitung:
     * belum lunas, $tanggal lewat jatuh tempo, dan tidak ada bukti berstatus menunggu
     * yang diunggah paling lambat pada tanggal jatuh tempo.
     */
    #[Scope]
    protected function tunggakan(Builder $query, ?CarbonInterface $tanggal = null): void
    {
        $zona = config('app.timezone');

        $query->where('tagihan.status_tagihan', '<>', StatusTagihan::Lunas->value)
            ->where('tagihan.jatuh_tempo', '<', self::tanggalKalender($tanggal))
            ->whereDoesntHave('pembayaran', function (Builder $pembayaran) use ($zona) {
                $pembayaran->where('pembayaran.status_verifikasi', StatusVerifikasi::Menunggu->value)
                    ->whereRaw('(pembayaran.diunggah_pada AT TIME ZONE ?)::date <= tagihan.jatuh_tempo', [$zona]);
            });
    }

    #[Scope]
    protected function milikPenghuni(Builder $query, Penghuni $penghuni): void
    {
        $query->whereHas('kontrak', fn (Builder $kontrak) => $kontrak->where('kontrak.penghuni_id', $penghuni->id));
    }

    /**
     * Logika yang sama dengan scope tunggakan(), untuk satu objek.
     */
    public function isTunggakan(?CarbonInterface $tanggal = null): bool
    {
        if ($this->status_tagihan === StatusTagihan::Lunas) {
            return false;
        }

        if (self::tanggalKalender($tanggal) <= $this->jatuh_tempo->toDateString()) {
            return false;
        }

        $zona = config('app.timezone');

        $adaBuktiTepatWaktu = $this->pembayaran()
            ->where('status_verifikasi', StatusVerifikasi::Menunggu->value)
            ->get()
            ->contains(fn (Pembayaran $pembayaran) => $pembayaran->diunggah_pada
                ->setTimezone($zona)->toDateString() <= $this->jatuh_tempo->toDateString());

        return ! $adaBuktiTepatWaktu;
    }

    /**
     * Jumlah hari sejak jatuh tempo; 0 bila bukan tunggakan.
     */
    public function hariTerlambatPada(?CarbonInterface $tanggal = null): int
    {
        if (! $this->isTunggakan($tanggal)) {
            return 0;
        }

        return (int) $this->jatuh_tempo->diffInDays(CarbonImmutable::parse(self::tanggalKalender($tanggal)));
    }

    protected function hariTerlambat(): Attribute
    {
        return Attribute::get(fn (): int => $this->hariTerlambatPada());
    }

    /**
     * Rentang periode yang boleh diterbitkan: 12 bulan sebelum bulan berjalan sampai 1 bulan sesudahnya,
     * supaya salah pilih tahun tidak menerbitkan tagihan yang tidak masuk akal. Keduanya tanggal 1.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function rentangPeriodeTerbit(?CarbonInterface $hariIni = null): array
    {
        $bulanIni = Kontrak::hariIni($hariIni)->startOfMonth();

        return [$bulanIni->subMonthsNoOverflow(12), $bulanIni->addMonthNoOverflow()];
    }

    /**
     * Menerbitkan satu tagihan untuk setiap kontrak yang berhak pada $periode (F-10). Kontrak yang sudah punya
     * tagihan periode itu dilewati, juga bila permintaan bersamaan sudah menyisipkannya lebih dulu
     * (indeks uq_tagihan_kontrak_periode). Kontrak, pembayaran, dan sinkronisasi_erp tidak disentuh.
     *
     * @throws PenerbitanTagihanDitolak
     */
    public static function terbitkanPeriode(CarbonInterface $periode): HasilPenerbitan
    {
        return DB::transaction(function () use ($periode) {
            $rencana = static::susunRencanaPeriode($periode);

            $dibuat = 0;
            $dilewati = $rencana['dilewati'];
            $total = 0;
            $tidakPenuh = 0;

            foreach ($rencana['baris'] as $baris) {
                try {
                    // Savepoint sendiri: pelanggaran indeks unik tidak boleh menggagalkan transaksi luar.
                    DB::transaction(function () use ($baris, $rencana) {
                        (new static)->forceFill([
                            'kontrak_id' => $baris['kontrak']->id,
                            'periode' => $rencana['periode']->toDateString(),
                            'nominal' => $baris['nominal'],
                            'jatuh_tempo' => $baris['jatuhTempo']->toDateString(),
                            'status_tagihan' => StatusTagihan::BelumDibayar,
                        ])->save();
                    });
                } catch (UniqueConstraintViolationException $e) {
                    if ($e->index !== 'uq_tagihan_kontrak_periode') {
                        throw $e;
                    }

                    $dilewati++;

                    continue;
                }

                $dibuat++;
                $total += $baris['nominal'];
                $tidakPenuh += $baris['penuhSebulan'] ? 0 : 1;
            }

            return new HasilPenerbitan($rencana['periode'], $dibuat, $dilewati, $total, $tidakPenuh, $rencana['jatuhTempo']);
        });
    }

    /**
     * Pratinjau terbitkanPeriode(): angka yang sama tanpa menyimpan apa pun.
     *
     * @throws PenerbitanTagihanDitolak
     */
    public static function pratinjauPeriode(CarbonInterface $periode): HasilPenerbitan
    {
        $rencana = static::susunRencanaPeriode($periode);
        $baris = collect($rencana['baris']);

        return new HasilPenerbitan(
            $rencana['periode'],
            $baris->count(),
            $rencana['dilewati'],
            (int) $baris->sum('nominal'),
            $baris->where('penuhSebulan', false)->count(),
            $rencana['jatuhTempo'],
        );
    }

    /**
     * Kontrak berhak untuk $periode, dipisah antara yang perlu dibuatkan tagihan dan yang sudah punya.
     *
     * @return array{periode: CarbonImmutable, jatuhTempo: CarbonImmutable, dilewati: int, baris: list<array{kontrak: Kontrak, nominal: int, jatuhTempo: CarbonImmutable, penuhSebulan: bool}>}
     *
     * @throws PenerbitanTagihanDitolak
     */
    private static function susunRencanaPeriode(CarbonInterface $periode): array
    {
        $periode = CarbonImmutable::parse($periode->copy()->startOfMonth()->toDateString());
        [$terawal, $terakhir] = static::rentangPeriodeTerbit();

        if ($periode->lt($terawal) || $periode->gt($terakhir)) {
            throw PenerbitanTagihanDitolak::periodeDiLuarRentang();
        }

        $pengaturan = PengaturanOperasional::aktif();

        if (! $pengaturan->sudahSiapTerbitTagihan()) {
            throw PenerbitanTagihanDitolak::jatuhTempoBelumDiatur();
        }

        $penghitung = new PenghitungNominal;
        $tanggalJatuhTempo = (int) $pengaturan->tanggal_jatuh_tempo;

        $kontrak = Kontrak::query()
            ->tertagihPadaPeriode($periode, $penghitung->strategi())
            ->with(['kamar:id,nomor_kamar', 'penghuni:id,user_id', 'penghuni.user:id,nama'])
            ->withExists(['tagihan as sudah_ditagih' => fn ($tagihan) => $tagihan->where('tagihan.periode', $periode->toDateString())])
            ->orderBy('kontrak.id')
            ->get();

        $baris = [];
        $dilewati = 0;

        foreach ($kontrak as $item) {
            if ($item->sudah_ditagih) {
                $dilewati++;

                continue;
            }

            $baris[] = [
                'kontrak' => $item,
                'nominal' => $penghitung->untuk($item, $periode),
                'jatuhTempo' => $penghitung->jatuhTempoUntuk($item, $periode, $tanggalJatuhTempo),
                'penuhSebulan' => $penghitung->penuhSebulan($item, $periode),
            ];
        }

        return [
            'periode' => $periode,
            'jatuhTempo' => $periode->day($tanggalJatuhTempo),
            'dilewati' => $dilewati,
            'baris' => $baris,
        ];
    }

    /**
     * Penghuni mengunggah bukti bayar (F-12, ATB-03). Berkas disimpan di disk privat dengan nama acak (nama asli
     * tidak dipakai), lalu dalam satu transaksi dibuat pembayaran berstatus menunggu dan tagihan menjadi
     * menunggu_verifikasi. Bila transaksi gagal, berkas yang baru disimpan dihapus dan status tagihan tidak berubah.
     * Nominal selalu sama dengan nominal tagihan (ASM-P02). Sinkronisasi ERP baru dibuat saat pembayaran diterima.
     *
     * [ASUMSI] Nama method mengikuti pola method status di CLAUDE.md; class diagram belum memuat method unggah.
     *
     * @throws TagihanTidakBisaDiberiBukti
     */
    public function unggahBukti(UploadedFile $berkas, CarbonInterface $tanggalBayar): Pembayaran
    {
        $ekstensi = self::EKSTENSI_BUKTI[self::mimeIsiBerkas($berkas)]
            ?? throw new InvalidArgumentException('Tipe berkas bukti tidak didukung.');

        $jalur = Storage::disk('local')->putFileAs(
            'bukti-bayar/'.now()->setTimezone(config('app.timezone'))->format('Y/m'),
            $berkas,
            Str::uuid()->toString().'.'.$ekstensi,
        );

        try {
            return DB::transaction(function () use ($jalur, $tanggalBayar) {
                $terkunci = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

                if ($terkunci->status_tagihan !== StatusTagihan::BelumDibayar) {
                    throw TagihanTidakBisaDiberiBukti::statusTidakSesuai();
                }

                $pembayaran = new Pembayaran([
                    'tagihan_id' => $terkunci->id,
                    'tanggal_bayar' => $tanggalBayar->toDateString(),
                    'nominal_bayar' => $terkunci->nominal,
                    'bukti_bayar_path' => $jalur,
                ]);
                $pembayaran->forceFill([
                    'diunggah_pada' => now(),
                    'status_verifikasi' => StatusVerifikasi::Menunggu,
                ])->save();

                $terkunci->forceFill(['status_tagihan' => StatusTagihan::MenungguVerifikasi])->save();
                $this->status_tagihan = StatusTagihan::MenungguVerifikasi;

                return $pembayaran;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($jalur);

            // Kirim ganda: indeks uq_pembayaran_menunggu menolak bukti kedua untuk tagihan yang sama.
            if ($e instanceof UniqueConstraintViolationException && $e->index === 'uq_pembayaran_menunggu') {
                throw TagihanTidakBisaDiberiBukti::statusTidakSesuai();
            }

            throw $e;
        }
    }

    /**
     * Tipe MIME dari isi berkas (finfo), bukan dari nama atau ekstensi yang dikirim browser. Berkas teks yang
     * hanya diberi nama .jpg tidak lolos. Null bila isinya tidak bisa dibaca.
     */
    public static function mimeIsiBerkas(UploadedFile $berkas): ?string
    {
        $jalur = $berkas->getRealPath();

        if (! is_string($jalur) || ! is_file($jalur)) {
            return $berkas->getMimeType();
        }

        $mime = mime_content_type($jalur);

        return $mime === false ? null : $mime;
    }

    /**
     * Pembayaran terbaru (unggahan terakhir) tagihan ini; dimuat sekaligus untuk semua tagihan lewat with().
     */
    public function pembayaranTerakhir(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'tagihan_id')->latestOfMany(['diunggah_pada', 'id']);
    }

    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'tagihan_id');
    }

    /**
     * Tanggal kalender (Y-m-d) di zona waktu aplikasi.
     */
    private static function tanggalKalender(?CarbonInterface $tanggal): string
    {
        return ($tanggal ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString();
    }
}
