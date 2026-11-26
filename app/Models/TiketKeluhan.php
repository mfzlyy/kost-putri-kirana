<?php

namespace App\Models;

use App\Enums\JenisTransaksiErp;
use App\Enums\LokasiKeluhan;
use App\Enums\StatusAkun;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTiket;
use App\Exceptions\KeluhanGagalDikirim;
use App\Exceptions\LokasiKeluhanTidakValid;
use App\Exceptions\StatusTiketBerubah;
use App\Exceptions\TiketSudahDitugaskan;
use App\Exceptions\VendorTidakAktif;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Database\Factories\TiketKeluhanFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $nomor_tiket
 * @property int $penghuni_id
 * @property int $fasilitas_id
 * @property int|null $vendor_id
 * @property LokasiKeluhan $lokasi
 * @property string $deskripsi
 * @property string|null $jenis_kerusakan
 * @property string|null $catatan_instruksi
 * @property StatusTiket $status_tiket
 * @property string|null $biaya_perbaikan
 * @property CarbonImmutable $tanggal_lapor
 */
class TiketKeluhan extends Model
{
    /** @use HasFactory<TiketKeluhanFactory> */
    use HasFactory;

    protected $table = 'tiket_keluhan';

    public $timestamps = false;

    protected $fillable = [
        'nomor_tiket',
        'penghuni_id',
        'fasilitas_id',
        'lokasi',
        'deskripsi',
        'jenis_kerusakan',
        'catatan_instruksi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lokasi' => LokasiKeluhan::class,
            'status_tiket' => StatusTiket::class,
            'biaya_perbaikan' => 'decimal:2',
            'tanggal_lapor' => 'immutable_datetime',
        ];
    }

    /**
     * Kunci advisory (bigint tetap) yang membuat penomoran dan pengecekan tiket ganda berjalan satu per satu.
     * Kunci tingkat transaksi, jadi lepas sendiri saat transaksi selesai.
     */
    public const KUNCI_NOMOR_TIKET = 410016;

    /**
     * Jendela waktu (detik) tiket kembar dianggap klik berulang (F-16 butir 3).
     */
    public const JENDELA_TIKET_GANDA = 60;

    /**
     * Nomor tiket berikutnya (KLH-0001, KLH-0002, ...) dari nomor numerik terbesar yang berpola KLH-angka.
     * Dipanggil di dalam transaksi; indeks unik nomor_tiket menjaga dari nomor ganda.
     */
    public static function nomorBerikutnya(): string
    {
        $terbesar = (int) static::query()
            ->whereRaw("nomor_tiket ~ '^KLH-[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(nomor_tiket FROM 5) AS integer)) AS terbesar')
            ->value('terbesar');

        return sprintf('KLH-%04d', $terbesar + 1);
    }

    /**
     * Penghuni mengajukan keluhan (F-16). Satu transaksi: kunci penomoran, cek tiket kembar, cek lokasi dan
     * fasilitas, nomor berikutnya, tiket berstatus diajukan, dan baris riwayat pertamanya.
     * Tiket kembar (penghuni, fasilitas, lokasi, dan deskripsi sama dalam 60 detik) dikembalikan tanpa tiket baru.
     * Nomor yang bentrok diulang satu kali; bila bentrok lagi, KeluhanGagalDikirim dilempar.
     *
     * @throws LokasiKeluhanTidakValid
     * @throws KeluhanGagalDikirim
     */
    public static function ajukanKeluhan(Penghuni $penghuni, LokasiKeluhan $lokasi, Fasilitas $fasilitas, string $deskripsi, User $pengaju): self
    {
        try {
            return static::ajukanDalamTransaksi($penghuni, $lokasi, $fasilitas, $deskripsi, $pengaju);
        } catch (UniqueConstraintViolationException) {
            try {
                return static::ajukanDalamTransaksi($penghuni, $lokasi, $fasilitas, $deskripsi, $pengaju);
            } catch (UniqueConstraintViolationException $e) {
                throw new KeluhanGagalDikirim($e);
            }
        }
    }

    private static function ajukanDalamTransaksi(Penghuni $penghuni, LokasiKeluhan $lokasi, Fasilitas $fasilitas, string $deskripsi, User $pengaju): self
    {
        return DB::transaction(function () use ($penghuni, $lokasi, $fasilitas, $deskripsi, $pengaju) {
            DB::select('select pg_advisory_xact_lock(?)', [self::KUNCI_NOMOR_TIKET]);

            $deskripsi = trim($deskripsi);
            $sekarang = now();

            $kembar = static::query()
                ->where('penghuni_id', $penghuni->id)
                ->where('fasilitas_id', $fasilitas->id)
                ->where('lokasi', $lokasi->value)
                ->where('deskripsi', $deskripsi)
                ->where('tanggal_lapor', '>=', $sekarang->copy()->subSeconds(self::JENDELA_TIKET_GANDA))
                ->orderByDesc('id')
                ->first();

            if ($kembar !== null) {
                return $kembar;
            }

            static::pastikanLokasiSesuai($penghuni, $lokasi, $fasilitas);

            $tiket = (new static)->forceFill([
                'nomor_tiket' => static::nomorBerikutnya(),
                'penghuni_id' => $penghuni->id,
                'fasilitas_id' => $fasilitas->id,
                'lokasi' => $lokasi,
                'deskripsi' => $deskripsi,
                'status_tiket' => StatusTiket::Diajukan,
                'tanggal_lapor' => $sekarang,
            ]);
            $tiket->save();

            $tiket->riwayatStatus()->create([
                'status_sebelum' => null,
                'status_sesudah' => StatusTiket::Diajukan,
                'diubah_oleh' => $pengaju->id,
                'waktu_perubahan' => $sekarang,
            ]);

            return $tiket->refresh();
        });
    }

    /**
     * Lokasi kamar: penghuni wajib berkontrak aktif dan fasilitas milik kamar kontrak itu.
     * Lokasi area bersama: fasilitas tidak boleh milik kamar mana pun.
     */
    private static function pastikanLokasiSesuai(Penghuni $penghuni, LokasiKeluhan $lokasi, Fasilitas $fasilitas): void
    {
        if ($lokasi === LokasiKeluhan::Kamar) {
            $kontrak = $penghuni->kontrakAktif()->first();

            if ($kontrak === null) {
                throw LokasiKeluhanTidakValid::tanpaKontrakAktif();
            }

            if ($fasilitas->kamar_id === null || (int) $fasilitas->kamar_id !== (int) $kontrak->kamar_id) {
                throw LokasiKeluhanTidakValid::fasilitasTidakAda();
            }

            return;
        }

        if ($fasilitas->kamar_id !== null) {
            throw LokasiKeluhanTidakValid::fasilitasTidakAda();
        }
    }

    /**
     * Pengelola menugaskan tiket ke satu vendor aktif (F-17, ATB-07: diajukan menjadi ditugaskan).
     * Satu transaksi: tiket dikunci dan dibaca ulang, status harus masih diajukan, akun vendor harus masih aktif,
     * lalu data penugasan disimpan beserta baris riwayat statusnya.
     *
     * @throws TiketSudahDitugaskan
     * @throws VendorTidakAktif
     */
    public function tugaskanVendor(Vendor $vendor, string $jenisKerusakan, string $instruksi, User $pengelola): void
    {
        DB::transaction(function () use ($vendor, $jenisKerusakan, $instruksi, $pengelola) {
            $terkini = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($terkini->status_tiket !== StatusTiket::Diajukan) {
                throw TiketSudahDitugaskan::sudahDitugaskan();
            }

            $vendorTerkini = Vendor::query()->with('user')->findOrFail($vendor->getKey());

            if ($vendorTerkini->user?->status_akun !== StatusAkun::Aktif) {
                throw VendorTidakAktif::nonaktif();
            }

            $terkini->forceFill([
                'vendor_id' => $vendorTerkini->id,
                'jenis_kerusakan' => trim($jenisKerusakan),
                'catatan_instruksi' => trim($instruksi),
                'status_tiket' => StatusTiket::Ditugaskan,
            ])->save();

            $terkini->riwayatStatus()->create([
                'status_sebelum' => StatusTiket::Diajukan,
                'status_sesudah' => StatusTiket::Ditugaskan,
                'diubah_oleh' => $pengelola->id,
                'waktu_perubahan' => now(),
            ]);

            $this->setRawAttributes($terkini->getAttributes(), true);
            $this->unsetRelation('vendor');
            $this->unsetRelation('riwayatStatus');
        });
    }

    /**
     * Vendor mulai mengerjakan (F-19, ATB-07: ditugaskan menjadi dikerjakan). Satu transaksi: tiket dikunci dan dibaca
     * ulang, status harus masih ditugaskan, lalu status dan baris riwayatnya disimpan. Hak vendor atas tiket diperiksa
     * pemanggil lewat TiketKeluhanPolicy::kerjakan.
     *
     * @throws StatusTiketBerubah
     */
    public function mulaiKerjakan(User $vendorUser): void
    {
        DB::transaction(function () use ($vendorUser) {
            $terkini = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($terkini->status_tiket !== StatusTiket::Ditugaskan) {
                throw StatusTiketBerubah::sudahBerubah();
            }

            $terkini->forceFill(['status_tiket' => StatusTiket::Dikerjakan])->save();

            $terkini->riwayatStatus()->create([
                'status_sebelum' => StatusTiket::Ditugaskan,
                'status_sesudah' => StatusTiket::Dikerjakan,
                'diubah_oleh' => $vendorUser->id,
                'waktu_perubahan' => now(),
            ]);

            $this->muatUlangDari($terkini);
        });
    }

    /**
     * Vendor menyelesaikan tiket dengan biaya perbaikan (F-20, ATB-07, ATB-09: dikerjakan menjadi selesai). Satu transaksi:
     * status dan biaya disimpan, riwayat dicatat, dan antrean ERP dibuat berstatus menunggu: satu baris biaya perbaikan,
     * ditambah satu baris pemeliharaan bila fasilitasnya punya odoo_equipment_id. Pengiriman ke Odoo bukan di sini.
     *
     * @throws StatusTiketBerubah
     * @throws InvalidArgumentException bila biaya negatif
     */
    public function selesaikan(int $biaya, User $vendorUser): void
    {
        DB::transaction(function () use ($biaya, $vendorUser) {
            $terkini = static::query()->with('fasilitas')->lockForUpdate()->findOrFail($this->getKey());

            if ($terkini->status_tiket !== StatusTiket::Dikerjakan) {
                throw StatusTiketBerubah::sudahBerubah();
            }

            if ($biaya < 0) {
                throw new InvalidArgumentException('Biaya perbaikan tidak boleh negatif.');
            }

            $terkini->forceFill([
                'biaya_perbaikan' => $biaya,
                'status_tiket' => StatusTiket::Selesai,
            ])->save();

            $terkini->riwayatStatus()->create([
                'status_sebelum' => StatusTiket::Dikerjakan,
                'status_sesudah' => StatusTiket::Selesai,
                'diubah_oleh' => $vendorUser->id,
                'waktu_perubahan' => now(),
            ]);

            $this->buatAntreanErp($terkini, JenisTransaksiErp::BiayaPerbaikan, 'B');

            if ($terkini->fasilitas?->odoo_equipment_id !== null) {
                $this->buatAntreanErp($terkini, JenisTransaksiErp::Pemeliharaan, 'M');
            }

            $this->muatUlangDari($terkini);
        });
    }

    private function buatAntreanErp(self $tiket, JenisTransaksiErp $jenis, string $akhiran): void
    {
        (new SinkronisasiErp)->forceFill([
            'jenis_transaksi' => $jenis,
            'tiket_id' => $tiket->id,
            'referensi_unik' => $tiket->referensiErp($akhiran),
            'status_sinkronisasi' => StatusSinkronisasi::Menunggu,
        ])->save();
    }

    private function muatUlangDari(self $terkini): void
    {
        $this->setRawAttributes($terkini->getAttributes(), true);
        $this->unsetRelation('riwayatStatus');
    }

    /**
     * Referensi unik untuk Odoo: KLH-0001 menjadi KPK-TKT-0001-B (biaya perbaikan) atau KPK-TKT-0001-M (pemeliharaan).
     * Angka nomor tiket dipertahankan apa adanya, termasuk lebih dari 4 digit (KLH-10000 menjadi KPK-TKT-10000-B).
     */
    public function referensiErp(string $akhiran): string
    {
        return 'KPK-TKT-'.substr($this->nomor_tiket, 4).'-'.$akhiran;
    }

    /**
     * Tanggal tiket diselesaikan menurut riwayat statusnya, pada zona waktu aplikasi (Asia/Jakarta), tanpa jam;
     * null bila tidak ada riwayat selesai. Dipakai sebagai tanggal catatan di Odoo (F-23, F-24).
     */
    public function tanggalSelesai(): ?CarbonImmutable
    {
        $riwayat = $this->relationLoaded('riwayatStatus') ? $this->riwayatStatus : $this->riwayatStatus()->get();

        return $riwayat->firstWhere('status_sesudah', StatusTiket::Selesai)
            ?->waktu_perubahan->setTimezone(config('app.timezone'))->startOfDay();
    }

    /**
     * Lama penanganan: dari waktu riwayat diajukan sampai waktu riwayat selesai (F-18 butir 4); null bila belum selesai.
     */
    public function durasiPenanganan(): ?CarbonInterval
    {
        $riwayat = $this->relationLoaded('riwayatStatus') ? $this->riwayatStatus : $this->riwayatStatus()->get();

        $diajukan = $riwayat->firstWhere('status_sesudah', StatusTiket::Diajukan);
        $selesai = $riwayat->firstWhere('status_sesudah', StatusTiket::Selesai);

        if ($diajukan === null || $selesai === null) {
            return null;
        }

        return $diajukan->waktu_perubahan->diff($selesai->waktu_perubahan);
    }

    /**
     * Antrean kerja kantor: tiket diajukan di atas, lalu status lain; di tiap kelompok yang terlama di atas (F-17 butir 1 dan 4).
     */
    #[Scope]
    protected function antreanKantor(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN tiket_keluhan.status_tiket = ? THEN 0 ELSE 1 END', [StatusTiket::Diajukan->value])
            ->orderBy('tiket_keluhan.tanggal_lapor')
            ->orderBy('tiket_keluhan.id');
    }

    #[Scope]
    protected function milikPenghuni(Builder $query, Penghuni $penghuni): void
    {
        $query->where('tiket_keluhan.penghuni_id', $penghuni->id);
    }

    /**
     * Tiket yang ditugaskan kepada vendor ini (ATB-08).
     */
    #[Scope]
    protected function milikVendor(Builder $query, Vendor $vendor): void
    {
        $query->where('tiket_keluhan.vendor_id', $vendor->id);
    }

    #[Scope]
    protected function ditugaskanKe(Builder $query, Vendor $vendor): void
    {
        $query->where('tiket_keluhan.vendor_id', $vendor->id);
    }

    public function penghuni(): BelongsTo
    {
        return $this->belongsTo(Penghuni::class, 'penghuni_id');
    }

    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(Fasilitas::class, 'fasilitas_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatusTiket::class, 'tiket_id')
            ->orderBy('waktu_perubahan')
            ->orderBy('id');
    }

    public function sinkronisasi(): HasMany
    {
        return $this->hasMany(SinkronisasiErp::class, 'tiket_id');
    }
}
