<?php

namespace App\Support\Migrasi;

/**
 * Keluaran ValidatorMigrasi: baris valid per berkas, baris tertolak, dan seluruh temuan.
 */
final class HasilValidasi
{
    /**
     * @param  array<string, list<array{baris: int, data: array<string, string>}>>  $sumber  semua baris data per berkas
     * @param  array<string, array<string, mixed>>  $kamar  nomor_kamar => baris valid
     * @param  array<string, array<string, mixed>>  $penghuni  kode_penghuni => baris valid
     * @param  array<string, array<string, mixed>>  $kontrak  kode_kontrak => baris valid
     * @param  list<array<string, mixed>>  $pembayaran
     * @param  array<string, array<int, true>>  $ditolak  berkas => [nomor baris => true]
     * @param  list<Temuan>  $temuan
     */
    public function __construct(
        public readonly array $sumber,
        public readonly array $kamar,
        public readonly array $penghuni,
        public readonly array $kontrak,
        public readonly array $pembayaran,
        public readonly array $ditolak,
        public readonly array $temuan,
    ) {}

    public function jumlahDitolak(): int
    {
        return array_sum(array_map('count', $this->ditolak));
    }

    public function jumlahGalat(): int
    {
        return count(array_filter($this->temuan, fn (Temuan $t) => $t->jenis === Temuan::GALAT));
    }

    public function jumlahPeringatan(): int
    {
        return count(array_filter($this->temuan, fn (Temuan $t) => $t->jenis === Temuan::PERINGATAN));
    }
}
