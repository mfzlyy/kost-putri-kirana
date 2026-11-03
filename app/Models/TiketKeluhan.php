<?php

namespace App\Models;

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use Carbon\CarbonImmutable;
use Database\Factories\TiketKeluhanFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Nomor tiket berikutnya (KLH-0001, KLH-0002, ...) dari nomor numerik terbesar yang ada.
     * Dipanggil di dalam transaksi; indeks unik nomor_tiket menjaga dari nomor ganda.
     */
    public static function nomorBerikutnya(): string
    {
        $terbesar = (int) static::query()
            ->selectRaw('MAX(CAST(SUBSTRING(nomor_tiket FROM 5) AS integer)) AS terbesar')
            ->value('terbesar');

        return sprintf('KLH-%04d', $terbesar + 1);
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
