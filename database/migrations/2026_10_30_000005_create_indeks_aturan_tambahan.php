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
        DB::statement('CREATE UNIQUE INDEX uq_kontrak_aktif_per_kamar ON kontrak (kamar_id) WHERE status_kontrak = \'aktif\'');
        DB::statement('CREATE UNIQUE INDEX uq_kontrak_aktif_per_penghuni ON kontrak (penghuni_id) WHERE status_kontrak = \'aktif\'');
        DB::statement('CREATE UNIQUE INDEX uq_pembayaran_menunggu ON pembayaran (tagihan_id) WHERE status_verifikasi = \'menunggu\'');
        DB::statement('CREATE UNIQUE INDEX uq_pembayaran_diterima ON pembayaran (tagihan_id) WHERE status_verifikasi = \'diterima\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_pembayaran_diterima');
        DB::statement('DROP INDEX IF EXISTS uq_pembayaran_menunggu');
        DB::statement('DROP INDEX IF EXISTS uq_kontrak_aktif_per_penghuni');
        DB::statement('DROP INDEX IF EXISTS uq_kontrak_aktif_per_kamar');
    }
};
