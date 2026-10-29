<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP TYPE IF EXISTS "status_sinkronisasi"');
        DB::statement('DROP TYPE IF EXISTS "jenis_transaksi_erp"');
        DB::statement('DROP TYPE IF EXISTS "status_tiket"');
        DB::statement('DROP TYPE IF EXISTS "lokasi_keluhan"');
        DB::statement('DROP TYPE IF EXISTS "status_verifikasi"');
        DB::statement('DROP TYPE IF EXISTS "status_tagihan"');
        DB::statement('DROP TYPE IF EXISTS "status_kontrak"');
        DB::statement('DROP TYPE IF EXISTS "status_penghuni"');
        DB::statement('DROP TYPE IF EXISTS "status_akun"');
        DB::statement('DROP TYPE IF EXISTS "peran_pengguna"');

        DB::statement("CREATE TYPE \"peran_pengguna\" AS ENUM ('pemilik', 'pengelola', 'penghuni', 'vendor')");
        DB::statement("CREATE TYPE \"status_akun\" AS ENUM ('aktif', 'nonaktif')");
        DB::statement("CREATE TYPE \"status_penghuni\" AS ENUM ('aktif', 'nonaktif')");
        DB::statement("CREATE TYPE \"status_kontrak\" AS ENUM ('aktif', 'berakhir')");
        DB::statement("CREATE TYPE \"status_tagihan\" AS ENUM ('belum_dibayar', 'menunggu_verifikasi', 'lunas')");
        DB::statement("CREATE TYPE \"status_verifikasi\" AS ENUM ('menunggu', 'diterima', 'ditolak')");
        DB::statement("CREATE TYPE \"lokasi_keluhan\" AS ENUM ('kamar', 'area_bersama')");
        DB::statement("CREATE TYPE \"status_tiket\" AS ENUM ('diajukan', 'ditugaskan', 'dikerjakan', 'selesai')");
        DB::statement("CREATE TYPE \"jenis_transaksi_erp\" AS ENUM ('pembayaran', 'biaya_perbaikan', 'pemeliharaan')");
        DB::statement("CREATE TYPE \"status_sinkronisasi\" AS ENUM ('menunggu', 'terkirim', 'gagal')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS "status_sinkronisasi"');
        DB::statement('DROP TYPE IF EXISTS "jenis_transaksi_erp"');
        DB::statement('DROP TYPE IF EXISTS "status_tiket"');
        DB::statement('DROP TYPE IF EXISTS "lokasi_keluhan"');
        DB::statement('DROP TYPE IF EXISTS "status_verifikasi"');
        DB::statement('DROP TYPE IF EXISTS "status_tagihan"');
        DB::statement('DROP TYPE IF EXISTS "status_kontrak"');
        DB::statement('DROP TYPE IF EXISTS "status_penghuni"');
        DB::statement('DROP TYPE IF EXISTS "status_akun"');
        DB::statement('DROP TYPE IF EXISTS "peran_pengguna"');
    }
};
