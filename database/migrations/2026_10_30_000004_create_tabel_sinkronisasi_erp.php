<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE TABLE \"sinkronisasi_erp\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"jenis_transaksi\" jenis_transaksi_erp NOT NULL,
              \"referensi_unik\" varchar(40) UNIQUE NOT NULL,
              \"pembayaran_id\" bigint,
              \"tiket_id\" bigint,
              \"status_sinkronisasi\" status_sinkronisasi NOT NULL DEFAULT 'menunggu',
              \"odoo_record_id\" integer,
              \"pesan_error\" text,
              \"waktu_kirim\" timestamptz,
              CONSTRAINT \"ck_sinkronisasi_sumber\" CHECK ((jenis_transaksi = 'pembayaran' AND pembayaran_id IS NOT NULL AND tiket_id IS NULL) OR (jenis_transaksi <> 'pembayaran' AND tiket_id IS NOT NULL AND pembayaran_id IS NULL))
            )
        ");

        DB::statement("COMMENT ON TABLE \"sinkronisasi_erp\" IS 'Status pengiriman ke Odoo Community 19 melalui JSON-2 API (F-25).'");
        DB::statement("COMMENT ON COLUMN \"sinkronisasi_erp\".\"referensi_unik\" IS 'Contoh KPK-PAY-000123; mencegah catatan ganda saat dikirim ulang.'");
        DB::statement("COMMENT ON COLUMN \"sinkronisasi_erp\".\"odoo_record_id\" IS 'ID catatan di Odoo yang dikembalikan sebagai status transaksi (proposal III.2).'");

        DB::statement('ALTER TABLE "sinkronisasi_erp" ADD CONSTRAINT "fk_sinkronisasi_pembayaran" FOREIGN KEY ("pembayaran_id") REFERENCES "pembayaran" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "sinkronisasi_erp" ADD CONSTRAINT "fk_sinkronisasi_tiket" FOREIGN KEY ("tiket_id") REFERENCES "tiket_keluhan" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');

        DB::statement('CREATE UNIQUE INDEX "uq_sinkronisasi_pembayaran" ON "sinkronisasi_erp" ("jenis_transaksi", "pembayaran_id")');
        DB::statement('CREATE UNIQUE INDEX "uq_sinkronisasi_tiket" ON "sinkronisasi_erp" ("jenis_transaksi", "tiket_id")');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS "uq_sinkronisasi_tiket"');
        DB::statement('DROP INDEX IF EXISTS "uq_sinkronisasi_pembayaran"');
        Schema::dropIfExists('sinkronisasi_erp');
    }
};
