<?php

namespace Database\Factories;

use App\Models\Kamar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kamar>
 */
class KamarFactory extends Factory
{
    protected $model = Kamar::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor_kamar' => 'T-'.fake()->unique()->numerify('#####'),
            'tarif_bulanan' => fake()->numberBetween(24, 30) * 50000,
            'keterangan' => null,
        ];
    }
}
