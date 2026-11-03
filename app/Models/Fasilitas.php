<?php

namespace App\Models;

use Database\Factories\FasilitasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama_fasilitas
 * @property string $lokasi
 * @property int|null $kamar_id
 * @property int|null $odoo_equipment_id
 */
class Fasilitas extends Model
{
    /** @use HasFactory<FasilitasFactory> */
    use HasFactory;

    protected $table = 'fasilitas';

    public $timestamps = false;

    protected $fillable = [
        'nama_fasilitas',
        'lokasi',
        'kamar_id',
        'odoo_equipment_id',
    ];

    /**
     * Kamar pemilik fasilitas; null untuk fasilitas area bersama.
     */
    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }

    public function tiketKeluhan(): HasMany
    {
        return $this->hasMany(TiketKeluhan::class, 'fasilitas_id');
    }
}
