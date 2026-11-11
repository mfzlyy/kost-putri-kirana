<?php

namespace App\Console\Commands;

use App\Exceptions\KontrakSudahBerakhir;
use App\Models\Kontrak;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Mengakhiri kontrak aktif yang tanggal berakhirnya sudah lewat (F-07 butir 5).
 * Kontrak yang berakhir hari ini tetap aktif sampai hari ini selesai. Tanggal kontrak tidak diubah.
 */
class AkhiriKontrakKedaluwarsa extends Command
{
    protected $signature = 'kontrak:akhiri-kedaluwarsa';

    protected $description = 'Akhiri kontrak aktif yang tanggal berakhirnya sudah lewat';

    public function handle(): int
    {
        $jumlah = 0;

        // get() lebih dulu: status baris berubah selama perulangan, jadi tidak boleh memakai chunk berbasis offset.
        Kontrak::query()->kedaluwarsa()->orderBy('id')->get()->each(function (Kontrak $kontrak) use (&$jumlah) {
            try {
                $kontrak->akhiri();
                $jumlah++;
            } catch (KontrakSudahBerakhir) {
                // Sudah diakhiri pengelola di sela-sela proses ini; dilewati.
            }
        });

        $this->info("{$jumlah} kontrak diakhiri otomatis.");

        // Hanya jumlah; tanpa nama atau data penghuni.
        Log::info("Kontrak kedaluwarsa diakhiri otomatis: {$jumlah}.");

        return self::SUCCESS;
    }
}
