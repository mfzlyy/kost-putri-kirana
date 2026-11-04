<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\PengaturanOperasional;
use App\Models\User;
use App\Policies\Concerns\MenentukanKepemilikan;
use Illuminate\Auth\Access\Response;

/**
 * Pengaturan operasional (F-04): pemilik dan pengelola boleh melihat, hanya pengelola yang boleh mengubah.
 */
class PengaturanOperasionalPolicy
{
    use MenentukanKepemilikan;

    public function view(User $user, PengaturanOperasional $pengaturan): Response
    {
        return $this->izinkanBila($this->isStaf($user));
    }

    public function update(User $user, PengaturanOperasional $pengaturan): Response
    {
        return $this->izinkanBila($user->peran === PeranPengguna::Pengelola);
    }
}
