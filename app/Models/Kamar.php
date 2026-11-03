<?php

namespace App\Models;

use App\Enums\StatusKontrak;
use Database\Factories\KamarFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $nomor_kamar
 * @property string $tarif_bulanan
 * @property string|null $keterangan
 * @property-read string $status_hunian
 */
class Kamar extends Model
{
    /** @use HasFactory<KamarFactory> */
    use HasFactory;

    public const HUNIAN_TERISI = 'terisi';

    public const HUNIAN_KOSONG = 'kosong';

    protected $table = 'kamar';

    public $timestamps = false;

    protected $fillable = [
        'nomor_kamar',
        'tarif_bulanan',
        'keterangan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarif_bulanan' => 'decimal:2',
        ];
    }

    /**
     * Status hunian dihitung dari kontrak aktif, tidak disimpan (ATB-01).
     */
    protected function statusHunian(): Attribute
    {
        return Attribute::get(function (): string {
            $terisi = $this->relationLoaded('kontrakAktif')
                ? $this->kontrakAktif !== null
                : $this->kontrakAktif()->exists();

            return $terisi ? self::HUNIAN_TERISI : self::HUNIAN_KOSONG;
        });
    }

    #[Scope]
    protected function terisi(Builder $query): void
    {
        $query->whereHas('kontrakAktif');
    }

    #[Scope]
    protected function kosong(Builder $query): void
    {
        $query->whereDoesntHave('kontrakAktif');
    }

    public function kontrak(): HasMany
    {
        return $this->hasMany(Kontrak::class, 'kamar_id');
    }

    public function kontrakAktif(): HasOne
    {
        return $this->hasOne(Kontrak::class, 'kamar_id')
            ->where('status_kontrak', StatusKontrak::Aktif->value);
    }

    public function fasilitas(): HasMany
    {
        return $this->hasMany(Fasilitas::class, 'kamar_id');
    }
}
