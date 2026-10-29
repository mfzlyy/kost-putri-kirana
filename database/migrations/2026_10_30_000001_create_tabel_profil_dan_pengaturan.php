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
            CREATE TABLE \"penghuni\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"user_id\" bigint UNIQUE NOT NULL,
              \"no_identitas_enc\" text NOT NULL,
              \"no_telepon\" varchar(20) NOT NULL,
              \"status_penghuni\" status_penghuni NOT NULL DEFAULT 'aktif'
            )
        ");

        DB::statement("COMMENT ON TABLE \"penghuni\" IS 'Profil penghuni; satu akun berperan penghuni memiliki tepat satu profil (1 : 0..1 dari users).'");
        DB::statement("COMMENT ON COLUMN \"penghuni\".\"no_identitas_enc\" IS 'Nomor identitas terenkripsi; ditampilkan tersamar (NFR-02, UU 27/2022).'");

        DB::statement("
            CREATE TABLE \"vendor\" (
              \"id\" BIGSERIAL PRIMARY KEY,
              \"user_id\" bigint UNIQUE NOT NULL,
              \"nama_vendor\" varchar(120) NOT NULL,
              \"jenis_layanan\" varchar(80) NOT NULL,
              \"no_telepon\" varchar(20),
              \"odoo_partner_id\" integer
            )
        ");

        DB::statement("COMMENT ON TABLE \"vendor\" IS 'Profil vendor; satu akun berperan vendor memiliki tepat satu profil (1 : 0..1 dari users).'");
        DB::statement("COMMENT ON COLUMN \"vendor\".\"odoo_partner_id\" IS 'ID partner vendor di Odoo; terisi setelah sinkronisasi pertama.'");

        DB::statement("
            CREATE TABLE \"pengaturan_operasional\" (
              \"id\" smallint PRIMARY KEY DEFAULT 1,
              \"tanggal_jatuh_tempo\" smallint,
              \"batas_peringatan_kontrak_hari\" smallint,
              \"diperbarui_oleh\" bigint,
              CONSTRAINT \"ck_pengaturan_satu_baris\" CHECK (id = 1),
              CONSTRAINT \"ck_pengaturan_jatuh_tempo\" CHECK (tanggal_jatuh_tempo BETWEEN 1 AND 28),
              CONSTRAINT \"ck_pengaturan_batas\" CHECK (batas_peringatan_kontrak_hari > 0)
            )
        ");

        DB::statement("COMMENT ON TABLE \"pengaturan_operasional\" IS 'Satu baris pengaturan (F-04). Tagihan tidak dapat diterbitkan selama tanggal_jatuh_tempo kosong.'");

        DB::statement('ALTER TABLE "penghuni" ADD CONSTRAINT "fk_penghuni_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "vendor" ADD CONSTRAINT "fk_vendor_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE "pengaturan_operasional" ADD CONSTRAINT "fk_pengaturan_user" FOREIGN KEY ("diperbarui_oleh") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_operasional');
        Schema::dropIfExists('vendor');
        Schema::dropIfExists('penghuni');
    }
};
