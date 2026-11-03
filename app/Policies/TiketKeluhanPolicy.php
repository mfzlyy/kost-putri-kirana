<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

class TiketKeluhanPolicy
{
    use MenentukanKepemilikan;

    /**
     * Semua peran punya daftar tiketnya sendiri. Query daftar wajib disaring: penghuni lewat
     * penghuni_id (ATB-10), vendor lewat TiketKeluhan::ditugaskanKe() (ATB-08).
     */
    public function viewAny(User $user): Response
    {
        return $this->izinkanBila(
            $this->isStaf($user)
            || in_array($user->peran, [PeranPengguna::Penghuni, PeranPengguna::Vendor], true)
        );
    }

    /**
     * Penghuni hanya tiket miliknya; vendor hanya tiket yang ditugaskan kepadanya (ATB-08).
     */
    public function view(User $user, TiketKeluhan $tiket): Response
    {
        $idPenghuni = $this->idProfilPenghuni($user);
        $idVendor = $this->idProfilVendor($user);

        return $this->izinkanBila(
            $this->isStaf($user)
            || ($idPenghuni !== null && $tiket->penghuni_id === $idPenghuni)
            || ($idVendor !== null && $tiket->vendor_id === $idVendor)
        );
    }
}
