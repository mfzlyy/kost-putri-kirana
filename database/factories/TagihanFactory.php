<?php

namespace Database\Factories;

use App\Enums\StatusTagihan;
use App\Models\Kontrak;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tagihan>
 */
class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    /**
     * Periode selalu tanggal 1; jatuh tempo tanggal 10 bulan yang sama.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periode = now()->subMonth()->startOfMonth();

        return [
            'kontrak_id' => Kontrak::factory(),
            'periode' => $periode->toDateString(),
            'nominal' => fn (array $attributes) => Kontrak::query()
                ->whereKey($attributes['kontrak_id'])
                ->value('tarif_bulanan'),
            'jatuh_tempo' => $periode->copy()->day(10)->toDateString(),
            'status_tagihan' => StatusTagihan::BelumDibayar,
        ];
    }

    public function belumDibayar(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_tagihan' => StatusTagihan::BelumDibayar,
        ]);
    }

    public function menungguVerifikasi(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_tagihan' => StatusTagihan::MenungguVerifikasi,
        ]);
    }

    public function lunas(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_tagihan' => StatusTagihan::Lunas,
        ]);
    }
}
