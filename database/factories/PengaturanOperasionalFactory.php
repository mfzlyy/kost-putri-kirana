<?php

namespace Database\Factories;

use App\Models\PengaturanOperasional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengaturanOperasional>
 */
class PengaturanOperasionalFactory extends Factory
{
    protected $model = PengaturanOperasional::class;

    /**
     * Pengaturan kosong, sama seperti kondisi awal sebelum pemilik memutuskan ISU-01 dan ISU-02.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => PengaturanOperasional::ID_TETAP,
            'tanggal_jatuh_tempo' => null,
            'batas_peringatan_kontrak_hari' => null,
            'diperbarui_oleh' => null,
        ];
    }

    public function lengkap(int $tanggalJatuhTempo = 10, int $batasPeringatanHari = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
            'batas_peringatan_kontrak_hari' => $batasPeringatanHari,
        ]);
    }
}
