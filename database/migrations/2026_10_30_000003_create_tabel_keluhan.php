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
            CREATE TABLE \"fasilitas\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"nama_fasilitas\" varchar(100) NOT NULL,
              \"lokasi\" varchar(60) NOT NULL,
              \"kamar_id\" bigint,
              \"odoo_equipment_id\" integer
            )
        ");

        DB::statement("COMMENT ON TABLE \"fasilitas\" IS 'Daftar fasilitas acuan (DEP-P01). Riwayat perawatan disimpan di Odoo Maintenance, bukan di web.'");
        DB::statement("COMMENT ON COLUMN \"fasilitas\".\"lokasi\" IS 'Nama kamar atau area bersama; kamar_id kosong untuk area bersama.'");
        DB::statement("COMMENT ON COLUMN \"fasilitas\".\"odoo_equipment_id\" IS 'ID peralatan (equipment) di Odoo Maintenance.'");

        DB::statement("
            CREATE TABLE \"tiket_keluhan\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"nomor_tiket\" varchar(12) UNIQUE NOT NULL,
              \"penghuni_id\" bigint NOT NULL,
              \"fasilitas_id\" bigint NOT NULL,
              \"vendor_id\" bigint,
              \"lokasi\" lokasi_keluhan NOT NULL,
              \"deskripsi\" text NOT NULL,
              \"jenis_kerusakan\" varchar(100),
              \"catatan_instruksi\" text,
              \"status_tiket\" status_tiket NOT NULL DEFAULT 'diajukan',
              \"biaya_perbaikan\" numeric(12,2),
              \"tanggal_lapor\" timestamptz NOT NULL DEFAULT (now()),
              CONSTRAINT \"ck_tiket_biaya\" CHECK (biaya_perbaikan >= 0),
              CONSTRAINT \"ck_tiket_selesai_biaya\" CHECK (status_tiket <> 'selesai' OR biaya_perbaikan IS NOT NULL)
            )
        ");

        DB::statement("COMMENT ON TABLE \"tiket_keluhan\" IS 'Status berubah berurutan: diajukan, ditugaskan, dikerjakan, selesai (ATB-07).'");
        DB::statement("COMMENT ON COLUMN \"tiket_keluhan\".\"nomor_tiket\" IS 'Format KLH-0001.'");

        DB::statement("
            CREATE TABLE \"riwayat_status_tiket\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"tiket_id\" bigint NOT NULL,
              \"status_sebelum\" status_tiket,
              \"status_sesudah\" status_tiket NOT NULL,
              \"diubah_oleh\" bigint NOT NULL,
              \"waktu_perubahan\" timestamptz NOT NULL DEFAULT (now())
            )
        ");

        DB::statement("COMMENT ON TABLE \"riwayat_status_tiket\" IS 'Jejak setiap perubahan status tiket untuk menghitung durasi penanganan (F-18).'");

        DB::statement('ALTER TABLE "fasilitas" ADD CONSTRAINT "fk_fasilitas_kamar" FOREIGN KEY ("kamar_id") REFERENCES "kamar" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "tiket_keluhan" ADD CONSTRAINT "fk_tiket_penghuni" FOREIGN KEY ("penghuni_id") REFERENCES "penghuni" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "tiket_keluhan" ADD CONSTRAINT "fk_tiket_fasilitas" FOREIGN KEY ("fasilitas_id") REFERENCES "fasilitas" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "tiket_keluhan" ADD CONSTRAINT "fk_tiket_vendor" FOREIGN KEY ("vendor_id") REFERENCES "vendor" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "riwayat_status_tiket" ADD CONSTRAINT "fk_riwayat_tiket" FOREIGN KEY ("tiket_id") REFERENCES "tiket_keluhan" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "riwayat_status_tiket" ADD CONSTRAINT "fk_riwayat_pengubah" FOREIGN KEY ("diubah_oleh") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_status_tiket');
        Schema::dropIfExists('tiket_keluhan');
        Schema::dropIfExists('fasilitas');
    }
};
