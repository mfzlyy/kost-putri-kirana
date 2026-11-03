<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Tagihan;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

class TagihanPolicy
{
    use MenentukanKepemilikan;

    /**
     * Pemilik, pengelola, dan penghuni boleh membuka daftar tagihan. Untuk penghuni,
     * query daftar wajib disaring dengan Tagihan::milikPenghuni() (ATB-10).
     */
    public function viewAny(User $user): Response
    {
        return $this->izinkanBila(
            $this->isStaf($user) || $user->peran === PeranPengguna::Penghuni
        );
    }

    /**
     * Penghuni hanya boleh melihat tagihan kontraknya sendiri; vendor selalu ditolak.
     */
    public function view(User $user, Tagihan $tagihan): Response
    {
        $idPenghuni = $this->idProfilPenghuni($user);

        return $this->izinkanBila(
            $this->isStaf($user)
            || ($idPenghuni !== null && $tagihan->kontrak->penghuni_id === $idPenghuni)
        );
    }
}
