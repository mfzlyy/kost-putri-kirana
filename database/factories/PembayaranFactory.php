<?php

namespace Database\Factories;

use App\Enums\StatusVerifikasi;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    /**
     * Pembayaran baru berstatus menunggu. Status tagihan tidak diubah di sini.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'tanggal_bayar' => now()->toDateString(),
            'nominal_bayar' => fn (array $attributes) => Tagihan::query()
                ->whereKey($attributes['tagihan_id'])
                ->value('nominal'),
            'bukti_bayar_path' => 'bukti-bayar/contoh.pdf',
            'diunggah_pada' => now(),
            'status_verifikasi' => StatusVerifikasi::Menunggu,
            'alasan_penolakan' => null,
            'diverifikasi_oleh' => null,
            'diverifikasi_pada' => null,
        ];
    }

    public function menunggu(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_verifikasi' => StatusVerifikasi::Menunggu,
            'alasan_penolakan' => null,
            'diverifikasi_oleh' => null,
            'diverifikasi_pada' => null,
        ]);
    }

    public function diterima(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_verifikasi' => StatusVerifikasi::Diterima,
            'alasan_penolakan' => null,
            'diverifikasi_oleh' => User::factory()->pengelola(),
            'diverifikasi_pada' => now(),
        ]);
    }

    public function ditolak(?string $alasan = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status_verifikasi' => StatusVerifikasi::Ditolak,
            'alasan_penolakan' => $alasan ?? 'Bukti bayar tidak terbaca, mohon unggah ulang.',
            'diverifikasi_oleh' => User::factory()->pengelola(),
            'diverifikasi_pada' => now(),
        ]);
    }
}
