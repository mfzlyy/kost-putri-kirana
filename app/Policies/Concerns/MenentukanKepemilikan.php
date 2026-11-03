<?php

namespace App\Policies\Concerns;

use App\Enums\PeranPengguna;
use App\Http\Middleware\PastikanPeran;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Bantuan bersama untuk Policy kepemilikan data (ATB-08, ATB-10).
 */
trait MenentukanKepemilikan
{
    protected function tolak(): Response
    {
        return Response::deny(PastikanPeran::PESAN_DITOLAK);
    }

    protected function izinkanBila(bool $diizinkan): Response
    {
        return $diizinkan ? Response::allow() : $this->tolak();
    }

    /**
     * Pemilik dan pengelola boleh melihat seluruh data.
     */
    protected function isStaf(User $user): bool
    {
        return in_array($user->peran, [PeranPengguna::Pemilik, PeranPengguna::Pengelola], true);
    }

    /**
     * ID profil penghuni milik pengguna, atau null bila bukan penghuni.
     */
    protected function idProfilPenghuni(User $user): ?int
    {
        if ($user->peran !== PeranPengguna::Penghuni) {
            return null;
        }

        return $user->penghuni?->id;
    }

    /**
     * ID profil vendor milik pengguna, atau null bila bukan vendor.
     */
    protected function idProfilVendor(User $user): ?int
    {
        if ($user->peran !== PeranPengguna::Vendor) {
            return null;
        }

        return $user->vendor?->id;
    }
}
