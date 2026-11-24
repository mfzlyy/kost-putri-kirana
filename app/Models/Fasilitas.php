<?php

namespace App\Models;

use App\Enums\LokasiKeluhan;
use Database\Factories\FasilitasFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
     * Fasilitas yang boleh dipilih untuk satu lokasi keluhan: milik kamar itu, atau area bersama (tanpa kamar).
     * Lokasi kamar tanpa kamar (penghuni belum berkontrak) tidak menghasilkan apa pun.
     */
    #[Scope]
    protected function untukLokasi(Builder $query, LokasiKeluhan $lokasi, ?Kamar $kamar = null): void
    {
        if ($lokasi === LokasiKeluhan::AreaBersama) {
            $query->whereNull('fasilitas.kamar_id');
        } elseif ($kamar === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('fasilitas.kamar_id', $kamar->id);
        }

        $query->orderBy('fasilitas.nama_fasilitas')->orderBy('fasilitas.id');
    }

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
