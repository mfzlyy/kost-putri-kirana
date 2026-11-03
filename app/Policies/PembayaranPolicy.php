<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Pembayaran;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Dipakai juga untuk membuka berkas bukti bayar (disk privat).
 */
class PembayaranPolicy
{
    use MenentukanKepemilikan;

    /**
     * Penghuni membuka riwayat transaksinya lewat query yang disaring milik sendiri (ATB-10).
     */
    public function viewAny(User $user): Response
    {
        return $this->izinkanBila(
            $this->isStaf($user) || $user->peran === PeranPengguna::Penghuni
        );
    }

    /**
     * Penghuni hanya boleh melihat pembayaran atas tagihannya sendiri; vendor selalu ditolak.
     */
    public function view(User $user, Pembayaran $pembayaran): Response
    {
        $idPenghuni = $this->idProfilPenghuni($user);

        return $this->izinkanBila(
            $this->isStaf($user)
            || ($idPenghuni !== null && $pembayaran->tagihan->kontrak->penghuni_id === $idPenghuni)
        );
    }
}
