-- Aturan tambahan ERD Kost Putri Kirana (khusus PostgreSQL)
-- Versi 1.2, 29 Oktober 2026. Jalankan SETELAH erd_kost_putri_kirana.sql.
-- File ini tidak diimpor ke drawDB: parser drawDB membuang klausa WHERE sehingga
-- indeks parsial akan tampil sebagai indeks unik biasa dan maknanya berubah.

-- ATB-01: satu kamar hanya memiliki satu kontrak aktif.
CREATE UNIQUE INDEX uq_kontrak_aktif_per_kamar ON kontrak (kamar_id) WHERE status_kontrak = 'aktif';

-- ASM-04: satu penghuni hanya memiliki satu kontrak aktif.
CREATE UNIQUE INDEX uq_kontrak_aktif_per_penghuni ON kontrak (penghuni_id) WHERE status_kontrak = 'aktif';

-- ATB-03: satu tagihan hanya memiliki satu bukti yang menunggu verifikasi dan satu bukti yang diterima.
CREATE UNIQUE INDEX uq_pembayaran_menunggu ON pembayaran (tagihan_id) WHERE status_verifikasi = 'menunggu';
CREATE UNIQUE INDEX uq_pembayaran_diterima ON pembayaran (tagihan_id) WHERE status_verifikasi = 'diterima';
