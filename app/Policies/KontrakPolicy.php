<?php

namespace App\Policies;

use App\Models\Kontrak;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

class KontrakPolicy
{
    use MenentukanKepemilikan;

    /**
     * Daftar kontrak hanya untuk pemilik dan pengelola.
     */
    public function viewAny(User $user): Response
    {
        return $this->izinkanBila($this->isStaf($user));
    }

    /**
     * Penghuni hanya boleh melihat kontraknya sendiri; vendor selalu ditolak.
     */
    public function view(User $user, Kontrak $kontrak): Response
    {
        $idPenghuni = $this->idProfilPenghuni($user);

        return $this->izinkanBila(
            $this->isStaf($user)
            || ($idPenghuni !== null && $kontrak->penghuni_id === $idPenghuni)
        );
    }
}
