<?php

namespace App\Models;

use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use Database\Factories\PenghuniFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property string $no_identitas_enc
 * @property string $no_telepon
 * @property StatusPenghuni $status_penghuni
 * @property-read string $no_identitas_tersamar
 */
class Penghuni extends Model
{
    /** @use HasFactory<PenghuniFactory> */
    use HasFactory;

    protected $table = 'penghuni';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'no_identitas_enc',
        'no_telepon',
    ];

    protected $hidden = [
        'no_identitas_enc',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'no_identitas_enc' => 'encrypted',
            'status_penghuni' => StatusPenghuni::class,
        ];
    }

    /**
     * Nomor identitas tersamar: dua digit pertama, tanda * di tengah, empat digit terakhir (NFR-02).
     */
    protected function noIdentitasTersamar(): Attribute
    {
        return Attribute::get(function (): string {
            $nomor = (string) $this->no_identitas_enc;
            $panjang = mb_strlen($nomor);

            if ($panjang <= 6) {
                return str_repeat('*', $panjang);
            }

            return mb_substr($nomor, 0, 2)
                .str_repeat('*', $panjang - 6)
                .mb_substr($nomor, -4);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kontrak(): HasMany
    {
        return $this->hasMany(Kontrak::class, 'penghuni_id');
    }

    public function kontrakAktif(): HasOne
    {
        return $this->hasOne(Kontrak::class, 'penghuni_id')
            ->where('status_kontrak', StatusKontrak::Aktif->value);
    }

    public function tiketKeluhan(): HasMany
    {
        return $this->hasMany(TiketKeluhan::class, 'penghuni_id');
    }

    /**
     * Menandai penghuni sudah keluar (F-06 butir 6). Data tidak dihapus (ATB-11) dan akun login tidak ikut berubah.
     */
    public function nonaktifkan(): void
    {
        DB::transaction(function () {
            $this->forceFill(['status_penghuni' => StatusPenghuni::Nonaktif])->save();
        });
    }

    public function aktifkan(): void
    {
        DB::transaction(function () {
            $this->forceFill(['status_penghuni' => StatusPenghuni::Aktif])->save();
        });
    }
}
