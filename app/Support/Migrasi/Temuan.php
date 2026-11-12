<?php

namespace App\Support\Migrasi;

/**
 * Satu catatan galat atau peringatan dari validasi migrasi. Pesan tidak boleh memuat nilai data pribadi.
 */
final class Temuan
{
    public const GALAT = 'galat';

    public const PERINGATAN = 'peringatan';

    public function __construct(
        public readonly string $berkas,
        public readonly int $baris,
        public readonly string $kode,
        public readonly string $kolom,
        public readonly string $pesan,
        public readonly string $jenis = self::GALAT,
    ) {}
}
