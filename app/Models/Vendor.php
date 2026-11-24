<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $nama_vendor
 * @property string $jenis_layanan
 * @property string|null $no_telepon
 * @property int|null $odoo_partner_id
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $table = 'vendor';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'nama_vendor',
        'jenis_layanan',
        'no_telepon',
    ];

    /**
     * Vendor yang akunnya aktif (peran vendor), urut nama; dasar pilihan penugasan tiket (F-17).
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->whereHas('user', fn (Builder $user) => $user
            ->where('status_akun', StatusAkun::Aktif->value)
            ->where('peran', PeranPengguna::Vendor->value))
            ->orderBy('vendor.nama_vendor')
            ->orderBy('vendor.id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tiketKeluhan(): HasMany
    {
        return $this->hasMany(TiketKeluhan::class, 'vendor_id');
    }
}
