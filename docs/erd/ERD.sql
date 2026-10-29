-- ERD Web Pengelolaan Kost Putri Kirana (PostgreSQL), versi 1.2, 29 Oktober 2026
-- Dihasilkan dari kost_putri_kirana.dbml. Jalankan erd_aturan_tambahan.sql sesudahnya.

CREATE TYPE "peran_pengguna" AS ENUM (
  'pemilik',
  'pengelola',
  'penghuni',
  'vendor'
);

CREATE TYPE "status_akun" AS ENUM (
  'aktif',
  'nonaktif'
);

CREATE TYPE "status_penghuni" AS ENUM (
  'aktif',
  'nonaktif'
);

CREATE TYPE "status_kontrak" AS ENUM (
  'aktif',
  'berakhir'
);

CREATE TYPE "status_tagihan" AS ENUM (
  'belum_dibayar',
  'menunggu_verifikasi',
  'lunas'
);

CREATE TYPE "status_verifikasi" AS ENUM (
  'menunggu',
  'diterima',
  'ditolak'
);

CREATE TYPE "lokasi_keluhan" AS ENUM (
  'kamar',
  'area_bersama'
);

CREATE TYPE "status_tiket" AS ENUM (
  'diajukan',
  'ditugaskan',
  'dikerjakan',
  'selesai'
);

CREATE TYPE "jenis_transaksi_erp" AS ENUM (
  'pembayaran',
  'biaya_perbaikan',
  'pemeliharaan'
);

CREATE TYPE "status_sinkronisasi" AS ENUM (
  'menunggu',
  'terkirim',
  'gagal'
);

CREATE TABLE "users" (
  "id" BIGSERIAL PRIMARY KEY,
  "nama" varchar(100) NOT NULL,
  "email" varchar(255) UNIQUE NOT NULL,
  "password_hash" varchar(255) NOT NULL,
  "peran" peran_pengguna NOT NULL,
  "status_akun" status_akun NOT NULL DEFAULT 'aktif'
);

CREATE TABLE "penghuni" (
  "id" BIGSERIAL PRIMARY KEY,
  "user_id" bigint UNIQUE NOT NULL,
  "no_identitas_enc" text NOT NULL,
  "no_telepon" varchar(20) NOT NULL,
  "status_penghuni" status_penghuni NOT NULL DEFAULT 'aktif'
);

CREATE TABLE "vendor" (
  "id" BIGSERIAL PRIMARY KEY,
  "user_id" bigint UNIQUE NOT NULL,
  "nama_vendor" varchar(120) NOT NULL,
  "jenis_layanan" varchar(80) NOT NULL,
  "no_telepon" varchar(20),
  "odoo_partner_id" integer
);

CREATE TABLE "pengaturan_operasional" (
  "id" smallint PRIMARY KEY DEFAULT 1,
  "tanggal_jatuh_tempo" smallint,
  "batas_peringatan_kontrak_hari" smallint,
  "diperbarui_oleh" bigint,
  CONSTRAINT "ck_pengaturan_satu_baris" CHECK (id = 1),
  CONSTRAINT "ck_pengaturan_jatuh_tempo" CHECK (tanggal_jatuh_tempo BETWEEN 1 AND 28),
  CONSTRAINT "ck_pengaturan_batas" CHECK (batas_peringatan_kontrak_hari > 0)
);

CREATE TABLE "kamar" (
  "id" BIGSERIAL PRIMARY KEY,
  "nomor_kamar" varchar(10) UNIQUE NOT NULL,
  "tarif_bulanan" numeric(12,2) NOT NULL,
  "keterangan" text,
  CONSTRAINT "ck_kamar_tarif" CHECK (tarif_bulanan > 0)
);

CREATE TABLE "kontrak" (
  "id" BIGSERIAL PRIMARY KEY,
  "penghuni_id" bigint NOT NULL,
  "kamar_id" bigint NOT NULL,
  "tanggal_mulai" date NOT NULL,
  "tanggal_berakhir" date NOT NULL,
  "tarif_bulanan" numeric(12,2) NOT NULL,
  "status_kontrak" status_kontrak NOT NULL DEFAULT 'aktif',
  CONSTRAINT "ck_kontrak_tanggal" CHECK (tanggal_berakhir > tanggal_mulai),
  CONSTRAINT "ck_kontrak_tarif" CHECK (tarif_bulanan > 0)
);

CREATE TABLE "tagihan" (
  "id" BIGSERIAL PRIMARY KEY,
  "kontrak_id" bigint NOT NULL,
  "periode" date NOT NULL,
  "nominal" numeric(12,2) NOT NULL,
  "jatuh_tempo" date NOT NULL,
  "status_tagihan" status_tagihan NOT NULL DEFAULT 'belum_dibayar',
  CONSTRAINT "ck_tagihan_nominal" CHECK (nominal > 0)
);

CREATE TABLE "pembayaran" (
  "id" BIGSERIAL PRIMARY KEY,
  "tagihan_id" bigint NOT NULL,
  "tanggal_bayar" date NOT NULL,
  "nominal_bayar" numeric(12,2) NOT NULL,
  "bukti_bayar_path" varchar(255) NOT NULL,
  "diunggah_pada" timestamptz NOT NULL DEFAULT (now()),
  "status_verifikasi" status_verifikasi NOT NULL DEFAULT 'menunggu',
  "alasan_penolakan" text,
  "diverifikasi_oleh" bigint,
  "diverifikasi_pada" timestamptz,
  CONSTRAINT "ck_pembayaran_nominal" CHECK (nominal_bayar > 0),
  CONSTRAINT "ck_pembayaran_alasan" CHECK (status_verifikasi <> 'ditolak' OR alasan_penolakan IS NOT NULL)
);

CREATE TABLE "fasilitas" (
  "id" BIGSERIAL PRIMARY KEY,
  "nama_fasilitas" varchar(100) NOT NULL,
  "lokasi" varchar(60) NOT NULL,
  "kamar_id" bigint,
  "odoo_equipment_id" integer
);

CREATE TABLE "tiket_keluhan" (
  "id" BIGSERIAL PRIMARY KEY,
  "nomor_tiket" varchar(12) UNIQUE NOT NULL,
  "penghuni_id" bigint NOT NULL,
  "fasilitas_id" bigint NOT NULL,
  "vendor_id" bigint,
  "lokasi" lokasi_keluhan NOT NULL,
  "deskripsi" text NOT NULL,
  "jenis_kerusakan" varchar(100),
  "catatan_instruksi" text,
  "status_tiket" status_tiket NOT NULL DEFAULT 'diajukan',
  "biaya_perbaikan" numeric(12,2),
  "tanggal_lapor" timestamptz NOT NULL DEFAULT (now()),
  CONSTRAINT "ck_tiket_biaya" CHECK (biaya_perbaikan >= 0),
  CONSTRAINT "ck_tiket_selesai_biaya" CHECK (status_tiket <> 'selesai' OR biaya_perbaikan IS NOT NULL)
);

CREATE TABLE "riwayat_status_tiket" (
  "id" BIGSERIAL PRIMARY KEY,
  "tiket_id" bigint NOT NULL,
  "status_sebelum" status_tiket,
  "status_sesudah" status_tiket NOT NULL,
  "diubah_oleh" bigint NOT NULL,
  "waktu_perubahan" timestamptz NOT NULL DEFAULT (now())
);

CREATE TABLE "sinkronisasi_erp" (
  "id" BIGSERIAL PRIMARY KEY,
  "jenis_transaksi" jenis_transaksi_erp NOT NULL,
  "referensi_unik" varchar(40) UNIQUE NOT NULL,
  "pembayaran_id" bigint,
  "tiket_id" bigint,
  "status_sinkronisasi" status_sinkronisasi NOT NULL DEFAULT 'menunggu',
  "odoo_record_id" integer,
  "pesan_error" text,
  "waktu_kirim" timestamptz,
  CONSTRAINT "ck_sinkronisasi_sumber" CHECK ((jenis_transaksi = 'pembayaran' AND pembayaran_id IS NOT NULL AND tiket_id IS NULL) OR (jenis_transaksi <> 'pembayaran' AND tiket_id IS NOT NULL AND pembayaran_id IS NULL))
);

CREATE UNIQUE INDEX "uq_tagihan_kontrak_periode" ON "tagihan" ("kontrak_id", "periode");

CREATE UNIQUE INDEX "uq_sinkronisasi_pembayaran" ON "sinkronisasi_erp" ("jenis_transaksi", "pembayaran_id");

CREATE UNIQUE INDEX "uq_sinkronisasi_tiket" ON "sinkronisasi_erp" ("jenis_transaksi", "tiket_id");

COMMENT ON TABLE "users" IS 'Akun semua peran. Akun penghuni dan vendor dibuat pengelola (ASM-05).';

COMMENT ON TABLE "penghuni" IS 'Profil penghuni; satu akun berperan penghuni memiliki tepat satu profil (1 : 0..1 dari users).';

COMMENT ON COLUMN "penghuni"."no_identitas_enc" IS 'Nomor identitas terenkripsi; ditampilkan tersamar (NFR-02, UU 27/2022).';

COMMENT ON TABLE "vendor" IS 'Profil vendor; satu akun berperan vendor memiliki tepat satu profil (1 : 0..1 dari users).';

COMMENT ON COLUMN "vendor"."odoo_partner_id" IS 'ID partner vendor di Odoo; terisi setelah sinkronisasi pertama.';

COMMENT ON TABLE "pengaturan_operasional" IS 'Satu baris pengaturan (F-04). Tagihan tidak dapat diterbitkan selama tanggal_jatuh_tempo kosong.';

COMMENT ON TABLE "kamar" IS 'Status terisi/kosong tidak disimpan; dihitung dari kontrak aktif (ATB-01, 3NF).';

COMMENT ON TABLE "kontrak" IS 'Satu kamar dan satu penghuni hanya memiliki satu kontrak aktif (ATB-01, ASM-04).';

COMMENT ON TABLE "tagihan" IS 'Satu tagihan per kontrak per periode (BR-06). Tunggakan dihitung dari jatuh_tempo dan pembayaran.diunggah_pada (ATB-05), tidak disimpan.';

COMMENT ON COLUMN "tagihan"."periode" IS 'Tanggal 1 pada bulan tagihan, misalnya 2026-11-01.';

COMMENT ON TABLE "pembayaran" IS 'Setiap unggahan bukti bayar; satu tagihan dapat memiliki beberapa unggahan bila ada yang ditolak.';

COMMENT ON COLUMN "pembayaran"."diunggah_pada" IS 'Dipakai untuk aturan tunggakan ATB-05.';

COMMENT ON TABLE "fasilitas" IS 'Daftar fasilitas acuan (DEP-P01). Riwayat perawatan disimpan di Odoo Maintenance, bukan di web.';

COMMENT ON COLUMN "fasilitas"."lokasi" IS 'Nama kamar atau area bersama; kamar_id kosong untuk area bersama.';

COMMENT ON COLUMN "fasilitas"."odoo_equipment_id" IS 'ID peralatan (equipment) di Odoo Maintenance.';

COMMENT ON TABLE "tiket_keluhan" IS 'Status berubah berurutan: diajukan, ditugaskan, dikerjakan, selesai (ATB-07).';

COMMENT ON COLUMN "tiket_keluhan"."nomor_tiket" IS 'Format KLH-0001.';

COMMENT ON TABLE "riwayat_status_tiket" IS 'Jejak setiap perubahan status tiket untuk menghitung durasi penanganan (F-18).';

COMMENT ON TABLE "sinkronisasi_erp" IS 'Status pengiriman ke Odoo Community 19 melalui JSON-2 API (F-25).';

COMMENT ON COLUMN "sinkronisasi_erp"."referensi_unik" IS 'Contoh KPK-PAY-000123; mencegah catatan ganda saat dikirim ulang.';

COMMENT ON COLUMN "sinkronisasi_erp"."odoo_record_id" IS 'ID catatan di Odoo yang dikembalikan sebagai status transaksi (proposal III.2).';

ALTER TABLE "penghuni" ADD CONSTRAINT "fk_penghuni_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "vendor" ADD CONSTRAINT "fk_vendor_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "pengaturan_operasional" ADD CONSTRAINT "fk_pengaturan_user" FOREIGN KEY ("diperbarui_oleh") REFERENCES "users" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "kontrak" ADD CONSTRAINT "fk_kontrak_penghuni" FOREIGN KEY ("penghuni_id") REFERENCES "penghuni" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "kontrak" ADD CONSTRAINT "fk_kontrak_kamar" FOREIGN KEY ("kamar_id") REFERENCES "kamar" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "tagihan" ADD CONSTRAINT "fk_tagihan_kontrak" FOREIGN KEY ("kontrak_id") REFERENCES "kontrak" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "pembayaran" ADD CONSTRAINT "fk_pembayaran_tagihan" FOREIGN KEY ("tagihan_id") REFERENCES "tagihan" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "pembayaran" ADD CONSTRAINT "fk_pembayaran_verifikator" FOREIGN KEY ("diverifikasi_oleh") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "fasilitas" ADD CONSTRAINT "fk_fasilitas_kamar" FOREIGN KEY ("kamar_id") REFERENCES "kamar" ("id") ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "tiket_keluhan" ADD CONSTRAINT "fk_tiket_penghuni" FOREIGN KEY ("penghuni_id") REFERENCES "penghuni" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "tiket_keluhan" ADD CONSTRAINT "fk_tiket_fasilitas" FOREIGN KEY ("fasilitas_id") REFERENCES "fasilitas" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "tiket_keluhan" ADD CONSTRAINT "fk_tiket_vendor" FOREIGN KEY ("vendor_id") REFERENCES "vendor" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "riwayat_status_tiket" ADD CONSTRAINT "fk_riwayat_tiket" FOREIGN KEY ("tiket_id") REFERENCES "tiket_keluhan" ("id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "riwayat_status_tiket" ADD CONSTRAINT "fk_riwayat_pengubah" FOREIGN KEY ("diubah_oleh") REFERENCES "users" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "sinkronisasi_erp" ADD CONSTRAINT "fk_sinkronisasi_pembayaran" FOREIGN KEY ("pembayaran_id") REFERENCES "pembayaran" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "sinkronisasi_erp" ADD CONSTRAINT "fk_sinkronisasi_tiket" FOREIGN KEY ("tiket_id") REFERENCES "tiket_keluhan" ("id") ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE;
