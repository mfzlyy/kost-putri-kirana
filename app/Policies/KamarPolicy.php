<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\Kamar;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Data kamar (F-05): pemilik dan pengelola boleh melihat, hanya pengelola yang boleh mengelola.
 */
class KamarPolicy
{
    use MenentukanKepemilikan;

    public function viewAny(User $user): Response
    {
        return $this->izinkanBila($this->isStaf($user));
    }

    public function view(User $user, Kamar $kamar): Response
    {
        return $this->izinkanBila($this->isStaf($user));
    }

    public function create(User $user): Response
    {
        return $this->izinkanBila($this->adalahPengelola($user));
    }

    public function update(User $user, Kamar $kamar): Response
    {
        return $this->izinkanBila($this->adalahPengelola($user));
    }

    /**
     * Kamar hanya boleh dihapus bila belum punya kontrak (aktif maupun berakhir) dan belum punya fasilitas.
     * Pesan penolakan menyebut alasan yang bisa dipahami pengelola.
     */
    public function delete(User $user, Kamar $kamar): Response
    {
        if (! $this->adalahPengelola($user)) {
            return $this->tolak();
        }

        return $this->alasanTidakBisaDihapus($kamar);
    }

    private function adalahPengelola(User $user): bool
    {
        return $user->peran === PeranPengguna::Pengelola;
    }

    private function alasanTidakBisaDihapus(Kamar $kamar): Response
    {
        // Relasi dan hitungan yang sudah dimuat oleh daftar dipakai ulang, jadi tidak ada query per baris.
        $kamar->loadMissing('kontrakAktif.penghuni.user');

        if ($kamar->kontrakAktif !== null) {
            $nama = $kamar->kontrakAktif->penghuni?->user?->nama ?? 'penghuni';

            return Response::deny("Kamar {$kamar->nomor_kamar} masih punya kontrak aktif atas nama {$nama}, jadi belum bisa dihapus.");
        }

        $jumlahKontrak = $kamar->kontrak_count ?? $kamar->kontrak()->count();

        if ($jumlahKontrak > 0) {
            return Response::deny("Kamar {$kamar->nomor_kamar} punya riwayat kontrak, jadi tidak bisa dihapus agar riwayatnya tetap utuh.");
        }

        $jumlahFasilitas = $kamar->fasilitas_count ?? $kamar->fasilitas()->count();

        if ($jumlahFasilitas > 0) {
            return Response::deny("Kamar {$kamar->nomor_kamar} masih punya data fasilitas, jadi belum bisa dihapus.");
        }

        return Response::allow();
    }
}
