<?php

namespace Database\Factories;

use App\Enums\StatusTiket;
use App\Models\RiwayatStatusTiket;
use App\Models\TiketKeluhan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatStatusTiket>
 */
class RiwayatStatusTiketFactory extends Factory
{
    protected $model = RiwayatStatusTiket::class;

    /**
     * Catatan pembuatan tiket: tanpa status sebelum, status sesudah diajukan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tiket_id' => TiketKeluhan::factory(),
            'status_sebelum' => null,
            'status_sesudah' => StatusTiket::Diajukan,
            'diubah_oleh' => User::factory()->penghuni(),
            'waktu_perubahan' => now(),
        ];
    }

    public function perubahan(?StatusTiket $sebelum, StatusTiket $sesudah): static
    {
        return $this->state(fn (array $attributes) => [
            'status_sebelum' => $sebelum,
            'status_sesudah' => $sesudah,
        ]);
    }
}
