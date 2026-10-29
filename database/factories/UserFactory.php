<?php

namespace Database\Factories;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake('id_ID')->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= 'password',
            'peran' => PeranPengguna::Pengelola,
            'status_akun' => StatusAkun::Aktif,
        ];
    }

    public function pemilik(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => PeranPengguna::Pemilik,
        ]);
    }

    public function pengelola(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => PeranPengguna::Pengelola,
        ]);
    }

    public function penghuni(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => PeranPengguna::Penghuni,
        ]);
    }

    public function vendor(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => PeranPengguna::Vendor,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_akun' => StatusAkun::Nonaktif,
        ]);
    }
}

