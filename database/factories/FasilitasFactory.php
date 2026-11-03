<?php

namespace Database\Factories;

use App\Models\Fasilitas;
use App\Models\Kamar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fasilitas>
 */
class FasilitasFactory extends Factory
{
    protected $model = Fasilitas::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_fasilitas' => fake()->randomElement(['AC', 'Kasur', 'Lemari', 'Meja belajar']),
            'kamar_id' => Kamar::factory(),
            'lokasi' => fn (array $attributes) => 'Kamar '.Kamar::query()
                ->whereKey($attributes['kamar_id'])
                ->value('nomor_kamar'),
            'odoo_equipment_id' => null,
        ];
    }

    public function areaBersama(): static
    {
        return $this->state(fn (array $attributes) => [
            'nama_fasilitas' => fake()->randomElement(['Mesin cuci', 'Dapur bersama', 'Pompa air']),
            'kamar_id' => null,
            'lokasi' => 'Area bersama',
        ]);
    }
}
