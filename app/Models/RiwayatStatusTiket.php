<?php

namespace App\Models;

use App\Enums\StatusTiket;
use Carbon\CarbonImmutable;
use Database\Factories\RiwayatStatusTiketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tiket_id
 * @property StatusTiket|null $status_sebelum
 * @property StatusTiket $status_sesudah
 * @property int $diubah_oleh
 * @property CarbonImmutable $waktu_perubahan
 */
class RiwayatStatusTiket extends Model
{
    /** @use HasFactory<RiwayatStatusTiketFactory> */
    use HasFactory;

    protected $table = 'riwayat_status_tiket';

    public $timestamps = false;

    protected $fillable = [
        'tiket_id',
        'status_sebelum',
        'status_sesudah',
        'diubah_oleh',
        'waktu_perubahan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_sebelum' => StatusTiket::class,
            'status_sesudah' => StatusTiket::class,
            'waktu_perubahan' => 'immutable_datetime',
        ];
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(TiketKeluhan::class, 'tiket_id');
    }

    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
