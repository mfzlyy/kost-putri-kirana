<?php

namespace App\Models;

use App\Enums\StatusKontrak;
use App\Exceptions\KontrakBentrok;
use App\Exceptions\KontrakSudahBerakhir;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KontrakFactory;
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
 * @property int $penghuni_id
 * @property int $kamar_id
 * @property CarbonImmutable $tanggal_mulai
 * @property CarbonImmutable $tanggal_berakhir
 * @property string $tarif_bulanan
 * @property StatusKontrak $status_kontrak
 */
class Kontrak extends Model
{
    /** @use HasFactory<KontrakFactory> */
    use HasFactory;

    protected $table = 'kontrak';

    public $timestamps = false;

    protected $fillable = [
        'penghuni_id',
        'kamar_id',
        'tanggal_mulai',
        'tanggal_berakhir',
        'tarif_bulanan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'immutable_date',
            'tanggal_berakhir' => 'immutable_date',
            'tarif_bulanan' => 'decimal:2',
            'status_kontrak' => StatusKontrak::class,
        ];
    }

    /**
     * Tanggal hari ini menurut zona waktu aplikasi (Asia/Jakarta), tanpa jam.
     */
    public static function hariIni(?CarbonInterface $tanggal = null): CarbonImmutable
    {
        return CarbonImmutable::parse(
            ($tanggal ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString()
        );
    }

    /**
     * Sisa hari sampai tanggal berakhir, dihitung per tanggal kalender di zona waktu aplikasi.
     * Bernilai negatif bila tanggal berakhir sudah lewat.
     */
    public function sisaHari(?CarbonInterface $tanggal = null): int
    {
        return (int) self::hariIni($tanggal)->diffInDays($this->tanggal_berakhir, false);
    }

    /**
     * Kontrak yang tanggal mulainya sebelum hari ini sudah berjalan; yang belum berjalan tanggalnya dibiarkan apa adanya saat diakhiri.
     */
    public function sudahBerjalan(?CarbonInterface $tanggal = null): bool
    {
        return $this->tanggal_mulai->toDateString() < self::hariIni($tanggal)->toDateString();
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('kontrak.status_kontrak', StatusKontrak::Aktif->value);
    }

    /**
     * Kontrak aktif yang tanggal berakhirnya jatuh antara hari ini dan $hari hari ke depan (F-08).
     */
    #[Scope]
    protected function akanBerakhir(Builder $query, int $hari, ?CarbonInterface $tanggal = null): void
    {
        $hariIni = self::hariIni($tanggal);

        $query->where('kontrak.status_kontrak', StatusKontrak::Aktif->value)
            ->whereBetween('kontrak.tanggal_berakhir', [
                $hariIni->toDateString(),
                $hariIni->addDays($hari)->toDateString(),
            ]);
    }

    /**
     * Kontrak aktif yang tanggal berakhirnya paling lambat $hari hari dari hari ini, termasuk yang sudah lewat
     * tetapi belum diakhiri otomatis (daftar peringatan F-08).
     */
    #[Scope]
    protected function perluPeringatan(Builder $query, int $hari, ?CarbonInterface $tanggal = null): void
    {
        $query->where('kontrak.status_kontrak', StatusKontrak::Aktif->value)
            ->where('kontrak.tanggal_berakhir', '<=', self::hariIni($tanggal)->addDays($hari)->toDateString());
    }

    /**
     * Kontrak aktif yang tanggal berakhirnya sudah lewat. Kontrak yang berakhir hari ini masih aktif sampai hari ini selesai.
     */
    #[Scope]
    protected function kedaluwarsa(Builder $query, ?CarbonInterface $tanggal = null): void
    {
        $query->where('kontrak.status_kontrak', StatusKontrak::Aktif->value)
            ->where('kontrak.tanggal_berakhir', '<', self::hariIni($tanggal)->toDateString());
    }

    /**
     * Membuat kontrak baru (F-07). Baris kamar dan penghuni dikunci, lalu dicek ulang tidak ada kontrak aktif.
     * Indeks unik parsial di basis data menjadi jaring pengaman untuk permintaan bersamaan.
     *
     * @throws KontrakBentrok
     */
    public static function buat(
        int $penghuniId,
        int $kamarId,
        CarbonInterface $tanggalMulai,
        CarbonInterface $tanggalBerakhir,
        int $tarifBulanan,
    ): self {
        try {
            return DB::transaction(function () use ($penghuniId, $kamarId, $tanggalMulai, $tanggalBerakhir, $tarifBulanan) {
                $kamar = Kamar::query()->lockForUpdate()->findOrFail($kamarId);
                Penghuni::query()->lockForUpdate()->findOrFail($penghuniId);

                if (static::query()->aktif()->where('kamar_id', $kamarId)->exists()) {
                    throw new KontrakBentrok('kamar', "Kamar {$kamar->nomor_kamar} sudah terisi.");
                }

                if (static::query()->aktif()->where('penghuni_id', $penghuniId)->exists()) {
                    throw new KontrakBentrok('penghuni', 'Penghuni ini sudah punya kontrak aktif.');
                }

                return static::query()->create([
                    'penghuni_id' => $penghuniId,
                    'kamar_id' => $kamarId,
                    'tanggal_mulai' => $tanggalMulai->toDateString(),
                    'tanggal_berakhir' => $tanggalBerakhir->toDateString(),
                    'tarif_bulanan' => $tarifBulanan,
                ])->refresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            throw self::petakanPelanggaranUnik($e, $kamarId);
        }
    }

    /**
     * Mengubah pelanggaran indeks unik parsial menjadi galat pada field yang tepat.
     * Pelanggaran indeks lain dikembalikan apa adanya.
     */
    public static function petakanPelanggaranUnik(UniqueConstraintViolationException $e, int $kamarId): KontrakBentrok|UniqueConstraintViolationException
    {
        return match ($e->index) {
            'uq_kontrak_aktif_per_kamar' => new KontrakBentrok(
                'kamar',
                'Kamar '.Kamar::query()->whereKey($kamarId)->value('nomor_kamar').' sudah terisi.'
            ),
            'uq_kontrak_aktif_per_penghuni' => new KontrakBentrok('penghuni', 'Penghuni ini sudah punya kontrak aktif.'),
            default => $e,
        };
    }

    /**
     * Mengakhiri kontrak aktif (F-07 butir 5). Dengan $tanggalKeluar, tanggal berakhir diganti agar riwayat
     * menunjukkan masa tinggal sebenarnya; tanpa itu tanggal tidak diubah. Tagihan, pembayaran, dan status
     * penghuni tidak disentuh.
     *
     * @throws KontrakSudahBerakhir
     */
    public function akhiri(?CarbonInterface $tanggalKeluar = null): void
    {
        DB::transaction(function () use ($tanggalKeluar) {
            $terkunci = $this->kunciDanPastikanAktif();

            if ($tanggalKeluar !== null) {
                $terkunci->tanggal_berakhir = $tanggalKeluar->toDateString();
            }

            $terkunci->forceFill(['status_kontrak' => StatusKontrak::Berakhir])->save();
        });

        $this->refresh();
    }

    /**
     * Perpanjangan dicatat sebagai kontrak baru (F-07 butir 6, ASM-P04). Kontrak lama ditutup lebih dulu tanpa
     * mengubah tanggalnya, karena indeks unik parsial hanya mengizinkan satu kontrak aktif per kamar dan per penghuni.
     *
     * @throws KontrakSudahBerakhir
     */
    public function perpanjang(CarbonInterface $tanggalBerakhirBaru, int $tarifBulanan): self
    {
        $baru = DB::transaction(function () use ($tanggalBerakhirBaru, $tarifBulanan) {
            $lama = $this->kunciDanPastikanAktif();
            $tanggalMulaiBaru = $lama->tanggal_berakhir->addDay();

            if ($tanggalBerakhirBaru->toDateString() <= $tanggalMulaiBaru->toDateString()) {
                throw new InvalidArgumentException('Tanggal berakhir baru harus setelah tanggal mulai.');
            }

            $lama->forceFill(['status_kontrak' => StatusKontrak::Berakhir])->save();

            return static::query()->create([
                'penghuni_id' => $lama->penghuni_id,
                'kamar_id' => $lama->kamar_id,
                'tanggal_mulai' => $tanggalMulaiBaru->toDateString(),
                'tanggal_berakhir' => $tanggalBerakhirBaru->toDateString(),
                'tarif_bulanan' => $tarifBulanan,
            ])->refresh();
        });

        $this->refresh();

        return $baru;
    }

    /**
     * Mengunci baris kontrak ini lalu memastikan statusnya masih aktif.
     *
     * @throws KontrakSudahBerakhir
     */
    private function kunciDanPastikanAktif(): self
    {
        $terkunci = static::query()->lockForUpdate()->findOrFail($this->getKey());

        if ($terkunci->status_kontrak !== StatusKontrak::Aktif) {
            throw new KontrakSudahBerakhir;
        }

        return $terkunci;
    }

    public function penghuni(): BelongsTo
    {
        return $this->belongsTo(Penghuni::class, 'penghuni_id');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class, 'kontrak_id');
    }
}
