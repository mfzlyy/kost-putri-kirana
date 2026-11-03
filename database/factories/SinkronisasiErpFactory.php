<?php

namespace Database\Factories;

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Models\Pembayaran;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * @extends Factory<SinkronisasiErp>
 */
class SinkronisasiErpFactory extends Factory
{
    protected $model = SinkronisasiErp::class;

    /**
     * Bawaan: sinkronisasi pembayaran dengan referensi KPK-PAY-000123.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_transaksi' => JenisTransaksiErp::Pembayaran,
            'pembayaran_id' => Pembayaran::factory(),
            'tiket_id' => null,
            'referensi_unik' => fn (array $attributes) => sprintf('KPK-PAY-%06d', $attributes['pembayaran_id']),
            'status_sinkronisasi' => StatusSinkronisasi::Menunggu,
            'odoo_record_id' => null,
            'pesan_error' => null,
            'waktu_kirim' => null,
        ];
    }

    public function untukPembayaran(Pembayaran $pembayaran): static
    {
        return $this->state(fn (array $attributes) => [
            'jenis_transaksi' => JenisTransaksiErp::Pembayaran,
            'pembayaran_id' => $pembayaran->id,
            'tiket_id' => null,
            'referensi_unik' => sprintf('KPK-PAY-%06d', $pembayaran->id),
        ]);
    }

    /**
     * Referensi mengikuti nomor tiket: KLH-0001 menjadi KPK-TKT-0001-B atau KPK-TKT-0001-M.
     */
    public function untukTiket(TiketKeluhan $tiket, JenisTransaksiErp $jenis): static
    {
        $akhiran = match ($jenis) {
            JenisTransaksiErp::BiayaPerbaikan => 'B',
            JenisTransaksiErp::Pemeliharaan => 'M',
            default => throw new InvalidArgumentException('Jenis transaksi tiket harus biaya_perbaikan atau pemeliharaan.'),
        };

        return $this->state(fn (array $attributes) => [
            'jenis_transaksi' => $jenis,
            'pembayaran_id' => null,
            'tiket_id' => $tiket->id,
            'referensi_unik' => 'KPK-TKT-'.substr($tiket->nomor_tiket, 4).'-'.$akhiran,
        ]);
    }

    public function terkirim(int $odooRecordId = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'status_sinkronisasi' => StatusSinkronisasi::Terkirim,
            'odoo_record_id' => $odooRecordId,
            'pesan_error' => null,
            'waktu_kirim' => now(),
        ]);
    }

    public function gagal(string $pesan = 'Odoo tidak dapat dihubungi.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status_sinkronisasi' => StatusSinkronisasi::Gagal,
            'pesan_error' => $pesan,
            'waktu_kirim' => now(),
        ]);
    }
}
