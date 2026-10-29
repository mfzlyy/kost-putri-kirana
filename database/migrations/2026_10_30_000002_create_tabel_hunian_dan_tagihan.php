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
            CREATE TABLE \"kamar\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"nomor_kamar\" varchar(10) UNIQUE NOT NULL,
              \"tarif_bulanan\" numeric(12,2) NOT NULL,
              \"keterangan\" text,
              CONSTRAINT \"ck_kamar_tarif\" CHECK (tarif_bulanan > 0)
            )
        ");

        DB::statement("COMMENT ON TABLE \"kamar\" IS 'Status terisi/kosong tidak disimpan; dihitung dari kontrak aktif (ATB-01, 3NF).'");

        DB::statement("
            CREATE TABLE \"kontrak\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"penghuni_id\" bigint NOT NULL,
              \"kamar_id\" bigint NOT NULL,
              \"tanggal_mulai\" date NOT NULL,
              \"tanggal_berakhir\" date NOT NULL,
              \"tarif_bulanan\" numeric(12,2) NOT NULL,
              \"status_kontrak\" status_kontrak NOT NULL DEFAULT 'aktif',
              CONSTRAINT \"ck_kontrak_tanggal\" CHECK (tanggal_berakhir > tanggal_mulai),
              CONSTRAINT \"ck_kontrak_tarif\" CHECK (tarif_bulanan > 0)
            )
        ");

        DB::statement("COMMENT ON TABLE \"kontrak\" IS 'Satu kamar dan satu penghuni hanya memiliki satu kontrak aktif (ATB-01, ASM-04).'");

        DB::statement("
            CREATE TABLE \"tagihan\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"kontrak_id\" bigint NOT NULL,
              \"periode\" date NOT NULL,
              \"nominal\" numeric(12,2) NOT NULL,
              \"jatuh_tempo\" date NOT NULL,
              \"status_tagihan\" status_tagihan NOT NULL DEFAULT 'belum_dibayar',
              CONSTRAINT \"ck_tagihan_nominal\" CHECK (nominal > 0)
            )
        ");

        DB::statement("COMMENT ON TABLE \"tagihan\" IS 'Satu tagihan per kontrak per periode (BR-06). Tunggakan dihitung dari jatuh_tempo dan pembayaran.diunggah_pada (ATB-05), tidak disimpan.'");
        DB::statement("COMMENT ON COLUMN \"tagihan\".\"periode\" IS 'Tanggal 1 pada bulan tagihan, misalnya 2026-11-01.'");

        DB::statement("
            CREATE TABLE \"pembayaran\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"tagihan_id\" bigint NOT NULL,
              \"tanggal_bayar\" date NOT NULL,
              \"nominal_bayar\" numeric(12,2) NOT NULL,
              \"bukti_bayar_path\" varchar(255) NOT NULL,
              \"diunggah_pada\" timestamptz NOT NULL DEFAULT (now()),
              \"status_verifikasi\" status_verifikasi NOT NULL DEFAULT 'menunggu',
              \"alasan_penolakan\" text,
              \"diverifikasi_oleh\" bigint,
              \"diverifikasi_pada\" timestamptz,
              CONSTRAINT \"ck_pembayaran_nominal\" CHECK (nominal_bayar > 0),
              CONSTRAINT \"ck_pembayaran_alasan\" CHECK (status_verifikasi <> 'ditolak' OR alasan_penolakan IS NOT NULL)
            )
        ");

        DB::statement("COMMENT ON TABLE \"pembayaran\" IS 'Setiap unggahan bukti bayar; satu tagihan dapat memiliki beberapa unggahan bila ada yang ditolak.'");
        DB::statement("COMMENT ON COLUMN \"pembayaran\".\"diunggah_pada\" IS 'Dipakai untuk aturan tunggakan ATB-05.'");

        DB::statement('ALTER TABLE "kontrak" ADD CONSTRAINT "fk_kontrak_penghuni" FOREIGN KEY ("penghuni_id") REFERENCES "penghuni" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "kontrak" ADD CONSTRAINT "fk_kontrak_kamar" FOREIGN KEY ("kamar_id") REFERENCES "kamar" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "tagihan" ADD CONSTRAINT "fk_tagihan_kontrak" FOREIGN KEY ("kontrak_id") REFERENCES "kontrak" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "pembayaran" ADD CONSTRAINT "fk_pembayaran_tagihan" FOREIGN KEY ("tagihan_id") REFERENCES "tagihan" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "pembayaran" ADD CONSTRAINT "fk_pembayaran_verifikator" FOREIGN KEY ("diverifikasi_oleh") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');

        DB::statement('CREATE UNIQUE INDEX "uq_tagihan_kontrak_periode" ON "tagihan" ("kontrak_id", "periode")');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS "uq_tagihan_kontrak_periode"');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('kontrak');
        Schema::dropIfExists('kamar');
    }
};
