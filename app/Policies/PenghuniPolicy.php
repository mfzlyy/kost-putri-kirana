<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Enums\StatusPenghuni;
use App\Models\Penghuni;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Data penghuni (F-06): pengelola mengelola, pemilik hanya melihat, penghuni hanya melihat dirinya sendiri.
 */
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

    public function create(User $user): Response
    {
        return $this->izinkanBila($this->adalahPengelola($user));
    }

    public function update(User $user, Penghuni $penghuni): Response
    {
        return $this->izinkanBila($this->adalahPengelola($user));
    }

    /**
     * Menandai keluar hanya untuk penghuni aktif yang tidak punya kontrak aktif; kontrak diakhiri dulu (F-06, ATB-01).
     */
    public function nonaktifkan(User $user, Penghuni $penghuni): Response
    {
        if (! $this->adalahPengelola($user) || $penghuni->status_penghuni !== StatusPenghuni::Aktif) {
            return $this->tolak();
        }

        $penghuni->loadMissing(['user', 'kontrakAktif.kamar']);

        if ($penghuni->kontrakAktif !== null) {
            $nama = $penghuni->user?->nama ?? 'Penghuni';
            $nomor = $penghuni->kontrakAktif->kamar?->nomor_kamar ?? '-';

            return Response::deny("{$nama} masih punya kontrak aktif di Kamar {$nomor}. Akhiri kontraknya dulu sebelum menandai keluar.");
        }

        return Response::allow();
    }

    public function aktifkan(User $user, Penghuni $penghuni): Response
    {
        return $this->izinkanBila(
            $this->adalahPengelola($user) && $penghuni->status_penghuni === StatusPenghuni::Nonaktif
        );
    }

    /**
     * Nomor identitas utuh hanya untuk pemilik, pengelola, dan penghuni yang bersangkutan (F-06 butir 4, NFR-02).
     */
    public function lihatIdentitas(User $user, Penghuni $penghuni): Response
    {
        return $this->izinkanBila(
            $this->isStaf($user)
            || ($user->peran === PeranPengguna::Penghuni && $penghuni->user_id === $user->id)
        );
    }

    private function adalahPengelola(User $user): bool
    {
        return $user->peran === PeranPengguna::Pengelola;
    }
}
