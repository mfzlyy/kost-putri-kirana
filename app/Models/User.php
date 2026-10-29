<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nama
 * @property string $email
 * @property string $password_hash
 * @property PeranPengguna $peran
 * @property StatusAkun $status_akun
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    public $timestamps = false;

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    protected $fillable = [
        'nama',
        'email',
        'password_hash',
        'peran',
        'status_akun',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'peran' => PeranPengguna::class,
            'status_akun' => StatusAkun::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->nama, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function isPemilik(): bool
    {
        return $this->peran === PeranPengguna::Pemilik;
    }

    public function isPengelola(): bool
    {
        return $this->peran === PeranPengguna::Pengelola;
    }

    public function isPenghuni(): bool
    {
        return $this->peran === PeranPengguna::Penghuni;
    }

    public function isVendor(): bool
    {
        return $this->peran === PeranPengguna::Vendor;
    }

    public function isAktif(): bool
    {
        return $this->status_akun === StatusAkun::Aktif;
    }
}
