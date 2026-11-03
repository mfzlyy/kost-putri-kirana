<?php

namespace Database\Factories;

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Penghuni;
use App\Models\TiketKeluhan;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TiketKeluhan>
 */
class TiketKeluhanFactory extends Factory
{
    protected $model = TiketKeluhan::class;

    /**
     * Nomor tiket diacak unik agar beberapa tiket dapat dibuat sekaligus;
     * nomor berurutan di data nyata berasal dari TiketKeluhan::nomorBerikutnya().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor_tiket' => sprintf('KLH-%04d', fake()->unique()->numberBetween(1, 9999)),
            'penghuni_id' => Penghuni::factory(),
            'fasilitas_id' => Fasilitas::factory(),
            'vendor_id' => null,
            'lokasi' => LokasiKeluhan::Kamar,
            'deskripsi' => fake('id_ID')->sentence(8),
            'jenis_kerusakan' => fake()->randomElement(['Tidak berfungsi', 'Bocor', 'Rusak fisik']),
            'catatan_instruksi' => null,
            'status_tiket' => StatusTiket::Diajukan,
            'biaya_perbaikan' => null,
            'tanggal_lapor' => now(),
        ];
    }

    public function ditugaskan(): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => Vendor::factory(),
            'status_tiket' => StatusTiket::Ditugaskan,
        ]);
    }

    public function dikerjakan(): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => Vendor::factory(),
            'status_tiket' => StatusTiket::Dikerjakan,
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => Vendor::factory(),
            'status_tiket' => StatusTiket::Selesai,
            'biaya_perbaikan' => fake()->numberBetween(2, 20) * 25000,
        ]);
    }
}
