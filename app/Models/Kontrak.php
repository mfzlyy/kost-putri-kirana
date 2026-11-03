<?php

namespace App\Models;

use App\Enums\StatusKontrak;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KontrakFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Sisa hari sampai tanggal berakhir, dihitung per tanggal kalender di zona waktu aplikasi.
     * Bernilai negatif bila tanggal berakhir sudah lewat.
     */
    public function sisaHari(?CarbonInterface $tanggal = null): int
    {
        $hariIni = CarbonImmutable::parse(
            ($tanggal ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString()
        );

        return (int) $hariIni->diffInDays($this->tanggal_berakhir, false);
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
        $hariIni = CarbonImmutable::parse(
            ($tanggal ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString()
        );

        $query->where('kontrak.status_kontrak', StatusKontrak::Aktif->value)
            ->whereBetween('kontrak.tanggal_berakhir', [
                $hariIni->toDateString(),
                $hariIni->addDays($hari)->toDateString(),
            ]);
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
