<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Exceptions\TagihanTidakBisaDiberiBukti;
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
     * Menerbitkan tagihan satu periode (F-10): hanya pengelola. Pemilik hanya melihat.
     */
    public function terbitkan(User $user): Response
    {
        return $this->izinkanBila($user->peran === PeranPengguna::Pengelola);
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

    /**
     * Unggah bukti bayar (F-12 butir 1): hanya penghuni pemilik tagihan, dan hanya selagi tagihan belum dibayar.
     * Kepemilikan diperiksa lebih dulu supaya penghuni lain tidak tahu status tagihan orang lain.
     */
    public function unggahBukti(User $user, Tagihan $tagihan): Response
    {
        $idPenghuni = $this->idProfilPenghuni($user);

        if ($idPenghuni === null || $tagihan->kontrak->penghuni_id !== $idPenghuni) {
            return $this->tolak();
        }

        return $tagihan->status_tagihan === StatusTagihan::BelumDibayar
            ? Response::allow()
            : Response::deny(TagihanTidakBisaDiberiBukti::PESAN);
    }
}
