<?php

namespace Database\Factories;

use App\Enums\StatusPenghuni;
use App\Models\Penghuni;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penghuni>
 */
class PenghuniFactory extends Factory
{
    protected $model = Penghuni::class;

    /**
     * Semua nilai fiktif; nomor identitas hanya deretan angka acak.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->penghuni(),
            'no_identitas_enc' => '32'.fake()->numerify('##############'),
            'no_telepon' => '08'.fake()->numerify('##########'),
            'status_penghuni' => StatusPenghuni::Aktif,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_penghuni' => StatusPenghuni::Nonaktif,
        ]);
    }
}
