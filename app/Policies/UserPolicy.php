<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Kelola akun (F-02): hanya pengelola, dan hanya akun penghuni dan vendor.
 * Akun pemilik dan pengelola dikelola lewat seeder.
 */
class UserPolicy
{
    use MenentukanKepemilikan;

    public function viewAny(User $user): Response
    {
        return $this->izinkanBila($user->peran === PeranPengguna::Pengelola);
    }

    public function create(User $user): Response
    {
        return $this->izinkanBila($user->peran === PeranPengguna::Pengelola);
    }

    public function nonaktifkan(User $user, User $target): Response
    {
        return $this->izinkanBila($this->bolehMengelola($user, $target));
    }

    public function aktifkan(User $user, User $target): Response
    {
        return $this->izinkanBila($this->bolehMengelola($user, $target));
    }

    private function bolehMengelola(User $user, User $target): bool
    {
        return $user->peran === PeranPengguna::Pengelola
            && ! $user->is($target)
            && in_array($target->peran, [PeranPengguna::Penghuni, PeranPengguna::Vendor], true);
    }
}
