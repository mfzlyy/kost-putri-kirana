<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Enums\StatusKontrak;
use App\Models\Kontrak;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Kontrak (F-07): pengelola mengelola, pemilik hanya melihat, penghuni hanya melihat kontraknya sendiri.
 */
class KontrakPolicy
{
    use MenentukanKepemilikan;

    public const PESAN_SUDAH_BERAKHIR = 'Kontrak ini sudah berakhir.';

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

    public function create(User $user): Response
    {
        return $this->izinkanBila($this->adalahPengelola($user));
    }

    /**
     * Mengakhiri lebih awal hanya untuk kontrak aktif.
     */
    public function akhiri(User $user, Kontrak $kontrak): Response
    {
        return $this->izinkanUntukKontrakAktif($user, $kontrak);
    }

    /**
     * Perpanjangan hanya untuk kontrak aktif.
     */
    public function perpanjang(User $user, Kontrak $kontrak): Response
    {
        return $this->izinkanUntukKontrakAktif($user, $kontrak);
    }

    private function adalahPengelola(User $user): bool
    {
        return $user->peran === PeranPengguna::Pengelola;
    }

    private function izinkanUntukKontrakAktif(User $user, Kontrak $kontrak): Response
    {
        if (! $this->adalahPengelola($user)) {
            return $this->tolak();
        }

        return $kontrak->status_kontrak === StatusKontrak::Aktif
            ? Response::allow()
            : Response::deny(self::PESAN_SUDAH_BERAKHIR);
    }
}
