<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Models\SinkronisasiErp;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Halaman Sinkronisasi (F-25): hanya pengelola berakun aktif. Pemilik, penghuni, dan vendor ditolak.
 */
class SinkronisasiErpPolicy
{
    use MenentukanKepemilikan;

    public function viewAny(User $user): Response
    {
        return $this->izinkanBila($this->pengelolaAktif($user));
    }

    /**
     * Hanya soal siapa yang boleh. Status barisnya diperiksa di SinkronisasiErp::kirimUlang(), di bawah kunci.
     */
    public function kirimUlang(User $user, SinkronisasiErp $sinkronisasi): Response
    {
        return $this->izinkanBila($this->pengelolaAktif($user));
    }

    private function pengelolaAktif(User $user): bool
    {
        return $user->peran === PeranPengguna::Pengelola && $user->status_akun === StatusAkun::Aktif;
    }
}
