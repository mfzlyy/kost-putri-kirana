<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengaturanOperasionalSeeder extends Seeder
{
    /**
     * Pastikan baris pengaturan tunggal (id = 1) ada. Nilainya sengaja dibiarkan kosong
     * karena menunggu keputusan pemilik (ISU-01, ISU-02); baris yang sudah terisi tidak diubah.
     */
    public function run(): void
    {
        DB::table('pengaturan_operasional')->updateOrInsert(['id' => 1], []);
    }
}
