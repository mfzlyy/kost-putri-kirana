<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->vendor(),
            'nama_vendor' => fake('id_ID')->company(),
            'jenis_layanan' => fake()->randomElement(['Listrik dan air', 'Perabot', 'Pendingin ruangan', 'Kebersihan']),
            'no_telepon' => '08'.fake()->numerify('##########'),
            'odoo_partner_id' => null,
        ];
    }
}
