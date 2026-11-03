<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Penghuni;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

class PenghuniPolicy
{
    use MenentukanKepemilikan;

    /**
     * Daftar penghuni hanya untuk pemilik dan pengelola.
     */
    public function viewAny(User $user): Response
    {
        return $this->izinkanBila($this->isStaf($user));
    }

    /**
     * Penghuni hanya boleh melihat profilnya sendiri; vendor selalu ditolak.
     */
    public function view(User $user, Penghuni $penghuni): Response
    {
        return $this->izinkanBila(
            $this->isStaf($user)
            || ($user->peran === PeranPengguna::Penghuni && $penghuni->user_id === $user->id)
        );
    }
}
