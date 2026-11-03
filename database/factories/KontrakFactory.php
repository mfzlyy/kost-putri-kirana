<?php

namespace Database\Factories;

use App\Enums\StatusKontrak;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kontrak>
 */
class KontrakFactory extends Factory
{
    protected $model = Kontrak::class;

    /**
     * Urutan kunci penting: tarif_bulanan menyalin tarif kamar yang sudah terbentuk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'penghuni_id' => Penghuni::factory(),
            'kamar_id' => Kamar::factory(),
            'tanggal_mulai' => now()->subMonths(3)->startOfMonth()->toDateString(),
            'tanggal_berakhir' => now()->addMonths(9)->endOfMonth()->toDateString(),
            'tarif_bulanan' => fn (array $attributes) => Kamar::query()
                ->whereKey($attributes['kamar_id'])
                ->value('tarif_bulanan'),
            'status_kontrak' => StatusKontrak::Aktif,
        ];
    }

    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kontrak' => StatusKontrak::Aktif,
        ]);
    }

    public function berakhir(): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_mulai' => now()->subMonths(15)->startOfMonth()->toDateString(),
            'tanggal_berakhir' => now()->subMonths(3)->endOfMonth()->toDateString(),
            'status_kontrak' => StatusKontrak::Berakhir,
        ]);
    }
}
