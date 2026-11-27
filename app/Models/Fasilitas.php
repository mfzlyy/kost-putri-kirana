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
use Illuminate\Support\Str;

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
     * Lokasi fasilitas sebagaimana ditulis di Odoo: "Kamar <nomor>" atau "Area Bersama".
     */
    public function lokasiErp(): string
    {
        return $this->kamar_id === null ? 'Area Bersama' : 'Kamar '.Str::squish($this->kamar->nomor_kamar);
    }

    /**
     * Nama peralatan (maintenance.equipment) di Odoo untuk fasilitas ini, misalnya "AC Kamar 03" atau
     * "Pompa air Area Bersama". Lokasi yang sudah tertulis di nama fasilitas tidak ditambahkan lagi.
     * [ASUMSI] Format nama ini belum ditetapkan pemilik; peralatan di Odoo dibuat mengikuti hasil method ini.
     */
    public function namaPeralatanErp(): string
    {
        $nama = Str::squish($this->nama_fasilitas);
        $lokasi = $this->lokasiErp();

        // Batas kata: "Kamar 10" tidak dianggap memuat "Kamar 1".
        $sudahMemuat = preg_match('/(?<![\pL\pN])'.preg_quote($lokasi, '/').'(?![\pL\pN])/iu', $nama) === 1;

        return $sudahMemuat ? $nama : "{$nama} {$lokasi}";
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
