<?php

namespace App\Models;

use Database\Factories\PengaturanOperasionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $tanggal_jatuh_tempo
 * @property int|null $batas_peringatan_kontrak_hari
 * @property int|null $diperbarui_oleh
 */
class PengaturanOperasional extends Model
{
    /** @use HasFactory<PengaturanOperasionalFactory> */
    use HasFactory;

    public const ID_TETAP = 1;

    protected $table = 'pengaturan_operasional';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'tanggal_jatuh_tempo',
        'batas_peringatan_kontrak_hari',
        'diperbarui_oleh',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_jatuh_tempo' => 'integer',
            'batas_peringatan_kontrak_hari' => 'integer',
        ];
    }

    /**
     * Baris pengaturan tunggal (id = 1). Dibuat kosong bila belum ada.
     */
    public static function aktif(): self
    {
        return static::query()->find(self::ID_TETAP)
            ?? static::query()->forceCreate(['id' => self::ID_TETAP])->refresh();
    }

    /**
     * Tagihan hanya boleh diterbitkan bila tanggal jatuh tempo sudah diatur (F-10).
     */
    public function sudahSiapTerbitTagihan(): bool
    {
        return $this->tanggal_jatuh_tempo !== null;
    }

    public function diperbaruiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperbarui_oleh');
    }
}
