# Inventaris Teks Antarmuka

Disusun untuk Prompt 20D. Nomor baris merujuk berkas **sebelum** perubahan Prompt 20D (commit `1c22814`).

Aturan keputusan ada di `docs/design/DESIGN.md`, bagian Penulisan.

## Cara Membaca

- Halaman yang dipakai pengelola dan pemilik dicatat sekali di bagian Pengelola, bertanda "(juga Pemilik)". Bagian Pemilik hanya memuat teks yang cuma dilihat pemilik.
- Bagian Semua memuat teks yang dipakai lintas peran: tata letak, komponen, label enum, pesan dari kelas PHP, dan berkas `lang/`.
- Teks dinamis ditulis dengan placeholder dalam kurung kurawal. `[PRD]` menandai teks atau isi pesan yang dikunci PRD.
- Data dari basis data (nama, nomor kamar, nominal, tanggal) tidak dicatat sebagai baris tersendiri.
- Tidak dicatat karena tidak tampil di web: keluaran perintah artisan (`app/Console`, `app/Support/Migrasi`), isi data yang dikirim ke Odoo, pesan `InvalidArgumentException` untuk pengembang, dan log.
- Teks bawaan paket (misalnya Flux) tidak dicatat.
- PERSINGKAT juga dipakai bila istilah diganti; teks penggantinya ada di kolom Teks baru.

## Ringkasan

| Peran | TETAP | PERSINGKAT | HAPUS | Jumlah |
| --- | ---: | ---: | ---: | ---: |
| Pemilik | 8 | 0 | 1 | 9 |
| Pengelola | 419 | 8 | 8 | 435 |
| Penghuni | 109 | 0 | 0 | 109 |
| Vendor | 41 | 0 | 0 | 41 |
| Tamu/Masuk | 16 | 0 | 2 | 18 |
| Semua | 302 | 2 | 0 | 304 |
| **Jumlah** | **895** | **10** | **11** | **916** |

## Pemilik

| No | Halaman | Lokasi (file:baris) | Jenis | Teks lama | Keputusan | Teks baru | Alasan |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 1 | Pengaturan (hanya lihat) | `resources/views/pages/pengaturan/⚡index.blade.php:146` | judul kartu | Jatuh Tempo | TETAP |  | Label |
| 2 | Pengaturan (hanya lihat) | `resources/views/pages/pengaturan/⚡index.blade.php:148` | meta/keterangan kecil | Tanggal {n} | TETAP |  | Data |
| 3 | Pengaturan (hanya lihat) | `resources/views/pages/pengaturan/⚡index.blade.php:148, 157` | keadaan kosong | Belum diatur | TETAP |  | Keadaan kosong |
| 4 | Pengaturan (hanya lihat) | `resources/views/pages/pengaturan/⚡index.blade.php:151` | meta/keterangan kecil | setiap bulan | TETAP |  | Satuan nilai (bulanan) |
| 5 | Pengaturan (hanya lihat) | `resources/views/pages/pengaturan/⚡index.blade.php:155` | judul kartu | Batas Peringatan | TETAP |  | Label |
| 6 | Pengaturan (hanya lihat) | `resources/views/pages/pengaturan/⚡index.blade.php:157` | meta/keterangan kecil | {n} hari / Belum diatur | TETAP |  | Data |
| 7 | Kontrak | `resources/views/pages/kontrak/⚡index.blade.php:273` | kotak info/catatan | Pengelola belum mengatur batasnya di Pengaturan. | HAPUS |  | Mengulang "Batas peringatan belum diatur.", keputusan tim |
| 8 | Dasbor | `resources/views/pages/halaman-disiapkan.blade.php:4` | keadaan kosong | Halaman ini sedang disiapkan. | TETAP |  | Keadaan kosong |
| 9 | Dasbor | `routes/web.php:33` | judul | Dasbor | TETAP |  | Judul |

## Pengelola

| No | Halaman | Lokasi (file:baris) | Jenis | Teks lama | Keputusan | Teks baru | Alasan |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 10 | Akun | `resources/views/pages/akun/⚡index.blade.php:19` | judul | Akun | TETAP |  | Judul |
| 11 | Akun | `resources/views/pages/akun/⚡index.blade.php:202` | toast | Akun berhasil dibuat. | TETAP |  | Toast hasil aksi |
| 12 | Akun | `resources/views/pages/akun/⚡index.blade.php:239` | toast | Akun dinonaktifkan. | TETAP |  | Toast hasil aksi |
| 13 | Akun | `resources/views/pages/akun/⚡index.blade.php:250` | toast | Akun diaktifkan. | TETAP |  | Toast hasil aksi |
| 14 | Akun | `resources/views/pages/akun/⚡index.blade.php:255` | judul | Akun | TETAP |  | Judul |
| 15 | Akun | `resources/views/pages/akun/⚡index.blade.php:258` | tombol | Tambah | TETAP |  | Tombol |
| 16 | Akun | `resources/views/pages/akun/⚡index.blade.php:264` | sr-only/aria-label | Saring peran | TETAP |  | Teks pembaca layar |
| 17 | Akun | `resources/views/pages/akun/⚡index.blade.php:265` | chip/status | Semua {n} | TETAP |  | Chip |
| 18 | Akun | `resources/views/pages/akun/⚡index.blade.php:266` | chip/status | Penghuni {n} | TETAP |  | Chip |
| 19 | Akun | `resources/views/pages/akun/⚡index.blade.php:267` | chip/status | Vendor {n} | TETAP |  | Chip |
| 20 | Akun | `resources/views/pages/akun/⚡index.blade.php:270` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 21 | Akun | `resources/views/pages/akun/⚡index.blade.php:271` | chip/status | Semua {n} | TETAP |  | Chip |
| 22 | Akun | `resources/views/pages/akun/⚡index.blade.php:272` | chip/status | Aktif {n} | TETAP |  | Chip |
| 23 | Akun | `resources/views/pages/akun/⚡index.blade.php:273` | chip/status | Nonaktif {n} | TETAP |  | Chip |
| 24 | Akun | `resources/views/pages/akun/⚡index.blade.php:280` | keadaan kosong | Tidak ada akun yang sesuai filter. | TETAP |  | Keadaan kosong |
| 25 | Akun | `resources/views/pages/akun/⚡index.blade.php:280` | keadaan kosong | Belum ada akun. | TETAP |  | Keadaan kosong |
| 26 | Akun | `resources/views/pages/akun/⚡index.blade.php:288` | judul | Nama | TETAP |  | Judul kolom |
| 27 | Akun | `resources/views/pages/akun/⚡index.blade.php:289` | judul | Email | TETAP |  | Judul kolom |
| 28 | Akun | `resources/views/pages/akun/⚡index.blade.php:290` | judul | Peran | TETAP |  | Judul kolom |
| 29 | Akun | `resources/views/pages/akun/⚡index.blade.php:291` | judul | Status | TETAP |  | Judul kolom |
| 30 | Akun | `resources/views/pages/akun/⚡index.blade.php:292` | sr-only/aria-label | Aksi | TETAP |  | Teks pembaca layar |
| 31 | Akun | `resources/views/pages/akun/⚡index.blade.php:310, 332` | tombol | Nonaktifkan | TETAP |  | Tombol |
| 32 | Akun | `resources/views/pages/akun/⚡index.blade.php:312, 335` | tombol | Aktifkan | TETAP |  | Tombol |
| 33 | Akun | `resources/views/pages/akun/⚡index.blade.php:348` | judul | Nonaktifkan Akun {nama}? | TETAP |  | Judul |
| 34 | Akun | `resources/views/pages/akun/⚡index.blade.php:349` | tombol | Nonaktifkan | TETAP |  | Tombol |
| 35 | Akun | `resources/views/pages/akun/⚡index.blade.php:354` | isi dialog | Email | TETAP |  | Label |
| 36 | Akun | `resources/views/pages/akun/⚡index.blade.php:356` | isi dialog | Peran | TETAP |  | Label |
| 37 | Akun | `resources/views/pages/akun/⚡index.blade.php:360` | isi dialog | Pemilik akun ini langsung keluar dan tidak bisa masuk. Anda bisa mengaktifkannya lagi. | PERSINGKAT | Akun ini langsung keluar dan tidak bisa masuk sampai diaktifkan lagi. | Keputusan tim |
| 38 | Akun | `resources/views/pages/akun/⚡index.blade.php:373` | judul | Tambah Akun | TETAP |  | Judul |
| 39 | Akun | `resources/views/pages/akun/⚡index.blade.php:376, 385` | sr-only/aria-label | Tutup | TETAP |  | Teks pembaca layar |
| 40 | Akun | `resources/views/pages/akun/⚡index.blade.php:389` | judul | Tambah Akun (ponsel) | TETAP |  | Judul |
| 41 | Akun | `resources/views/pages/akun/⚡index.blade.php:395` | label isian | Peran | TETAP |  | Label |
| 42 | Akun | `resources/views/pages/akun/⚡index.blade.php:397` | label isian | Penghuni, Vendor (pilihan peran) | TETAP |  | Label |
| 43 | Akun | `resources/views/pages/akun/⚡index.blade.php:422` | label isian | Nama | TETAP |  | Label |
| 44 | Akun | `resources/views/pages/akun/⚡index.blade.php:428` | label isian | Email | TETAP |  | Label |
| 45 | Akun | `resources/views/pages/akun/⚡index.blade.php:436` | judul kartu | Data Vendor | TETAP |  | Judul |
| 46 | Akun | `resources/views/pages/akun/⚡index.blade.php:439` | label isian | Nama Vendor | TETAP |  | Label |
| 47 | Akun | `resources/views/pages/akun/⚡index.blade.php:441` | bantuan isian | Awalnya diisi dari nama akun. | HAPUS |  | Menjelaskan cara kerja sistem; hasilnya terlihat di isian |
| 48 | Akun | `resources/views/pages/akun/⚡index.blade.php:447` | label isian | Jenis Layanan | TETAP |  | Label |
| 49 | Akun | `resources/views/pages/akun/⚡index.blade.php:453` | label isian | Telepon | TETAP |  | Label |
| 50 | Akun | `resources/views/pages/akun/⚡index.blade.php:464` | label isian | Password Awal | TETAP |  | Label |
| 51 | Akun | `resources/views/pages/akun/⚡index.blade.php:470` | label isian | Konfirmasi Password | TETAP |  | Label |
| 52 | Akun | `resources/views/pages/akun/⚡index.blade.php:475` | bantuan isian | Minimal 8 karakter, huruf dan angka. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 53 | Akun | `resources/views/pages/akun/⚡index.blade.php:482` | tombol | Batal | TETAP |  | Tombol |
| 54 | Akun | `resources/views/pages/akun/⚡index.blade.php:486` | tombol | Buat | TETAP |  | Tombol |
| 55 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:17` | judul | Verifikasi | TETAP |  | Judul |
| 56 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:136` | toast | Kamar {n} {bulan} (bagian toast) | TETAP |  | Data |
| 57 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:201` | toast | Pembayaran Kamar {n} {bulan} diterima. | TETAP |  | Toast hasil aksi |
| 58 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:223` | pesan galat | Isi alasan penolakan. | TETAP |  | Pesan galat |
| 59 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:224` | pesan galat | Alasan paling sedikit 5 karakter. | TETAP |  | Pesan galat |
| 60 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:225` | pesan galat | Alasan paling banyak 500 karakter. | TETAP |  | Pesan galat |
| 61 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:237` | toast | Bukti Kamar {n} {bulan} ditolak. | TETAP |  | Toast hasil aksi |
| 62 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:253` | judul | Verifikasi | TETAP |  | Judul |
| 63 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:256` | keadaan kosong | Tidak ada bukti yang menunggu. | TETAP |  | Keadaan kosong |
| 64 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:263` | sr-only/aria-label | Antrean bukti bayar | TETAP |  | Teks pembaca layar |
| 65 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:279` | meta/keterangan kecil | Kamar {n}, {nama depan} | TETAP |  | Data |
| 66 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:281` | meta/keterangan kecil | {bulan}, diunggah {waktu} | TETAP |  | Data |
| 67 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:284` | chip/status | Telat {n} Hari | TETAP |  | Status |
| 68 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:315` | sr-only/aria-label | Kembali | TETAP |  | Teks pembaca layar |
| 69 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:321` | judul kartu | Kamar {n}, Tagihan {bulan} | TETAP |  | Judul |
| 70 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:325` | chip/status | Menunggu | TETAP |  | Status |
| 71 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:332` | keadaan kosong | Bukti tidak tersedia. | TETAP |  | Keadaan kosong |
| 72 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:336` | meta/keterangan kecil | Berkas PDF | TETAP |  | Data |
| 73 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:340` | sr-only/aria-label | Bukti transfer Kamar {n} {bulan} | TETAP |  | Teks pembaca layar |
| 74 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:344` | meta/keterangan kecil | {EKSTENSI}, {ukuran} | TETAP |  | Data |
| 75 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:345` | tautan | Buka | TETAP |  | Navigasi |
| 76 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:351` | judul | Nominal | TETAP |  | Label |
| 77 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:355, 400` | judul | Tanggal Bayar | TETAP |  | Label |
| 78 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:358` | judul | Diunggah | TETAP |  | Label |
| 79 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:361` | judul | Jatuh Tempo | TETAP |  | Label |
| 80 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:365` | chip/status | Telat {n} Hari (panel) | TETAP |  | Status |
| 81 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:374` | tombol | Terima | TETAP |  | Tombol |
| 82 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:381` | tombol | Tolak | TETAP |  | Tombol |
| 83 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:389` | judul | Terima Pembayaran Kamar {n}? | TETAP |  | Judul |
| 84 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:390` | tombol | Terima | TETAP |  | Tombol |
| 85 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:396, 416` | isi dialog | Penghuni | TETAP |  | Label |
| 86 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:398, 418` | isi dialog | Nominal | TETAP |  | Label |
| 87 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:400` | isi dialog | Tanggal Bayar | TETAP |  | Label |
| 88 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:404` | isi dialog | Tagihan ini akan ditandai lunas dan dicatat ke pembukuan. Keputusan ini tidak bisa dibatalkan. | TETAP |  | Akibat tindakan |
| 89 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:409` | judul | Tolak Bukti Kamar {n}? | TETAP |  | Judul |
| 90 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:410` | tombol | Tolak | TETAP |  | Tombol |
| 91 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:422` | isi dialog | Penghuni akan melihat alasan ini dan bisa mengunggah bukti baru. | TETAP |  | Akibat tindakan |
| 92 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:426` | label isian | Alasan | TETAP |  | Label |
| 93 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php:438` | bantuan isian | Tampil ke penghuni. Contoh: Nominal kurang Rp100.000. | PERSINGKAT | Contoh: Nominal kurang Rp100.000. | Kalimat pertama mengulang isi dialog; contoh dipertahankan |
| 94 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:22` | judul | Tagihan | TETAP |  | Judul |
| 95 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:371` | pesan galat | Pilih periode yang valid. | TETAP |  | Pesan galat |
| 96 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:392` | toast | {n} tagihan {periode} diterbitkan. [PRD F-10] | TETAP |  | Teks dikunci PRD |
| 97 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:392` | toast | {n} dilewati karena sudah ada. [PRD F-10] | TETAP |  | Teks dikunci PRD |
| 98 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:395` | toast | Semua kontrak sudah punya tagihan {periode}. {n} dilewati. [PRD F-10] | TETAP |  | Teks dikunci PRD |
| 99 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:398` | toast | Tidak ada kontrak aktif pada {periode}. Tidak ada tagihan yang dibuat. [PRD F-10] | TETAP |  | Teks dikunci PRD |
| 100 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:430` | chip/status | Lunas | TETAP |  | Status |
| 101 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:431` | chip/status | Menunggu | TETAP |  | Status |
| 102 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:432` | chip/status | Belum Bayar | TETAP |  | Status |
| 103 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:437` | sr-only/aria-label | Terbitkan tagihan {periode} | TETAP |  | Teks pembaca layar |
| 104 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:461` | judul | Tagihan | TETAP |  | Judul |
| 105 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:466` | tombol | Terbitkan | TETAP |  | Tombol |
| 106 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:475` | kotak info/catatan | Atur tanggal jatuh tempo di Pengaturan dulu. | TETAP |  | Callout yang memblokir aksi |
| 107 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:477` | kotak info/catatan | Tanggal jatuh tempo belum diatur. (Pemilik) | TETAP |  | Callout yang memblokir aksi |
| 108 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:484` | sr-only/aria-label | Ringkasan {bulan} | TETAP |  | Teks pembaca layar |
| 109 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:486` | judul kartu | Total | TETAP |  | Label |
| 110 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:487` | judul kartu | Lunas | TETAP |  | Label |
| 111 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:488` | judul kartu | Menunggu | TETAP |  | Label |
| 112 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:489` | judul kartu | Belum Bayar | TETAP |  | Label |
| 113 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:490` | judul kartu | Tunggakan | TETAP |  | Label |
| 114 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:490` | meta/keterangan kecil | {n} tagihan, semua periode | PERSINGKAT | {n} tagihan | Keputusan tim |
| 115 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:494` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 116 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:495` | chip/status | Semua {n} | TETAP |  | Chip |
| 117 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:496` | chip/status | Telat {n} | TETAP |  | Chip |
| 118 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:497` | chip/status | Belum Bayar {n} | TETAP |  | Chip |
| 119 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:498` | chip/status | Menunggu {n} | TETAP |  | Chip |
| 120 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:499` | chip/status | Lunas {n} | TETAP |  | Chip |
| 121 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:506` | keadaan kosong | Tagihan {bulan} belum diterbitkan. | TETAP |  | Keadaan kosong |
| 122 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:508` | tombol | Terbitkan | TETAP |  | Tombol |
| 123 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:511` | keadaan kosong | Tidak ada tagihan yang sesuai filter. | TETAP |  | Keadaan kosong |
| 124 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:520` | judul | Kamar | TETAP |  | Judul kolom |
| 125 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:521` | judul | Penghuni | TETAP |  | Judul kolom |
| 126 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:522` | judul | Periode | TETAP |  | Judul kolom |
| 127 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:523` | judul | Jatuh Tempo | TETAP |  | Judul kolom |
| 128 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:524` | judul | Nominal | TETAP |  | Judul kolom |
| 129 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:525` | judul | Status | TETAP |  | Judul kolom |
| 130 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:529, 566` | judul | Telat Sebelumnya | TETAP |  | Judul |
| 131 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:542` | chip/status | {tanggal}, Lewat {n} Hari | TETAP |  | Status |
| 132 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:552, 588` | meta/keterangan kecil | Ditolak, {tanggal} | TETAP |  | Data |
| 133 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:581` | meta/keterangan kecil | {bulan}, Jatuh Tempo {tanggal} (ponsel) | TETAP |  | Data |
| 134 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:585` | chip/status | Lewat {n} Hari (ponsel) | TETAP |  | Status |
| 135 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:602` | judul | Terbitkan Tagihan {periode}? | TETAP |  | Judul |
| 136 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:603` | tombol | Terbitkan | TETAP |  | Tombol |
| 137 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:610` | isi dialog | Kontrak Ditagih | TETAP |  | Label |
| 138 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:612` | isi dialog | Sudah Ditagih | TETAP |  | Label |
| 139 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:614` | isi dialog | Jatuh Tempo | TETAP |  | Label |
| 140 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:616` | isi dialog | Total | TETAP |  | Label |
| 141 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:619` | isi dialog | Tidak Penuh Sebulan | TETAP |  | Label |
| 142 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:620` | isi dialog | {n} (dihitung prorata harian / tarif penuh) | TETAP |  | Akibat tindakan |
| 143 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:624` | isi dialog | {n} tagihan baru akan langsung tampil di Tagihan penghuni dan tidak bisa dibatalkan. Kontrak yang sudah ditagih dilewati. | TETAP |  | Akibat tindakan |
| 144 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:627` | isi dialog | Jatuh tempo {tanggal} sudah lewat, langsung terhitung tunggakan. | TETAP |  | Akibat tindakan |
| 145 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:632` | label isian | Periode | TETAP |  | Label |
| 146 | Tagihan (juga Pemilik) | `resources/views/pages/tagihan/⚡index.blade.php:642` | keadaan kosong | Tidak ada kontrak yang perlu ditagih untuk {periode}. | TETAP |  | Keadaan kosong |
| 147 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:12` | judul | Kamar | TETAP |  | Judul |
| 148 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:127` | toast | Kamar {n} dihapus. | TETAP |  | Toast hasil aksi |
| 149 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:132` | judul | Kamar | TETAP |  | Judul |
| 150 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:135` | tombol | Tambah | TETAP |  | Tombol |
| 151 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:146` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 152 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:147` | chip/status | Semua {n} | TETAP |  | Chip |
| 153 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:148` | chip/status | Terisi {n} | TETAP |  | Chip |
| 154 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:149` | chip/status | Kosong {n} | TETAP |  | Chip |
| 155 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:155` | keadaan kosong | Belum ada kamar. | TETAP |  | Keadaan kosong |
| 156 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:155` | keadaan kosong | Tidak ada kamar yang sesuai filter. | TETAP |  | Keadaan kosong |
| 157 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:157` | tombol | Tambah | TETAP |  | Tombol |
| 158 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:166` | judul | Kamar | TETAP |  | Judul kolom |
| 159 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:167` | judul | Penghuni | TETAP |  | Judul kolom |
| 160 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:168` | judul | Keterangan | TETAP |  | Judul kolom |
| 161 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:169` | judul | Tarif | TETAP |  | Judul kolom |
| 162 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:170` | judul | Status | TETAP |  | Judul kolom |
| 163 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:172` | sr-only/aria-label | Aksi | TETAP |  | Teks pembaca layar |
| 164 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:181` | keadaan kosong | Belum ada | TETAP |  | Keadaan kosong |
| 165 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:186, 218` | chip/status | Terisi | TETAP |  | Status |
| 166 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:188, 220` | chip/status | Kosong | TETAP |  | Status |
| 167 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:194, 229` | tombol | Ubah | TETAP |  | Tombol |
| 168 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:196, 231` | tombol | Hapus | TETAP |  | Tombol |
| 169 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:215` | keadaan kosong | Belum ada penghuni (ponsel) | TETAP |  | Keadaan kosong |
| 170 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:245` | judul | Hapus Kamar {n}? | TETAP |  | Judul |
| 171 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:246` | tombol | Hapus | TETAP |  | Tombol |
| 172 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:251` | isi dialog | Tarif | TETAP |  | Label |
| 173 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:253` | isi dialog | Keterangan | TETAP |  | Label |
| 174 | Kamar (juga Pemilik) | `resources/views/pages/kamar/⚡index.blade.php:257` | isi dialog | Kamar ini akan dihapus permanen. Tindakan ini tidak bisa dibatalkan. | TETAP |  | Akibat tindakan |
| 175 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:15` | judul | Kamar (judul tab) | TETAP |  | Judul |
| 176 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:82, 91` | pesan galat | Isi tarif dalam rupiah, lebih dari 0. | TETAP |  | Pesan galat |
| 177 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:88` | pesan galat | Isi nomor kamar. | TETAP |  | Pesan galat |
| 178 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:89` | pesan galat | Nomor kamar paling banyak 10 karakter. | TETAP |  | Pesan galat |
| 179 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:90` | pesan galat | Nomor kamar {n} sudah dipakai. | TETAP |  | Pesan galat |
| 180 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:92` | pesan galat | Keterangan paling banyak 255 karakter. | TETAP |  | Pesan galat |
| 181 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:111` | toast | Kamar {n} disimpan. | TETAP |  | Toast hasil aksi |
| 182 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:134` | toast | Kamar {n} dihapus. | TETAP |  | Toast hasil aksi |
| 183 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:142` | tautan | Kamar | TETAP |  | Navigasi |
| 184 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:143` | judul | Ubah Kamar {n} / Tambah Kamar | TETAP |  | Judul |
| 185 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:150` | label isian | Nomor Kamar | TETAP |  | Label |
| 186 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:152` | bantuan isian | Harus unik. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 187 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:157` | label isian | Tarif | TETAP |  | Label |
| 188 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:159` | label isian | Rp (awalan isian) | TETAP |  | Label |
| 189 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:176` | label isian | Keterangan | TETAP |  | Label |
| 190 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:178` | bantuan isian | Opsional. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 191 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:185` | kotak info/catatan | Kontrak {nama} tetap {tarif} sampai {tanggal}. | TETAP |  | Akibat menyimpan tarif baru (PRD F-05 butir 3) dan memuat data yang tidak ada di form |
| 192 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:191` | tombol | Simpan | TETAP |  | Tombol |
| 193 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:193` | tombol | Batal | TETAP |  | Tombol |
| 194 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:199` | judul kartu | Hapus | TETAP |  | Judul |
| 195 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:203, 206` | tombol | Hapus | TETAP |  | Tombol |
| 196 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:203` | meta/keterangan kecil | Masih punya fasilitas / Masih punya kontrak (title tombol nonaktif) | TETAP |  | Alasan tombol nonaktif |
| 197 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:203` | tombol | Hapus | TETAP |  | Tombol |
| 198 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:214` | tombol | Hapus | TETAP |  | Tombol |
| 199 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:213` | judul | Hapus Kamar {n}? | TETAP |  | Judul |
| 200 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:219` | isi dialog | Tarif | TETAP |  | Label |
| 201 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:221` | isi dialog | Keterangan | TETAP |  | Label |
| 202 | Tambah/Ubah Kamar | `resources/views/pages/kamar/⚡form.blade.php:225` | isi dialog | Kamar ini akan dihapus permanen. Tindakan ini tidak bisa dibatalkan. | TETAP |  | Akibat tindakan |
| 203 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:14` | judul | Penghuni | TETAP |  | Judul |
| 204 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:85` | judul | Penghuni | TETAP |  | Judul |
| 205 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:88` | tombol | Tambah | TETAP |  | Tombol |
| 206 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:93` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 207 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:94` | chip/status | Semua {n} | TETAP |  | Chip |
| 208 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:95` | chip/status | Aktif {n} | TETAP |  | Chip |
| 209 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:96` | chip/status | Keluar {n} | TETAP |  | Chip |
| 210 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:102` | keadaan kosong | Belum ada data penghuni. | TETAP |  | Keadaan kosong |
| 211 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:102` | keadaan kosong | Tidak ada penghuni yang sesuai filter. | TETAP |  | Keadaan kosong |
| 212 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:104` | tombol | Tambah | TETAP |  | Tombol |
| 213 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:114` | judul | Nama Lengkap | TETAP |  | Judul kolom |
| 214 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:115` | judul | Kamar | TETAP |  | Judul kolom |
| 215 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:116` | judul | Telepon | TETAP |  | Judul kolom |
| 216 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:117` | judul | Nomor Identitas | TETAP |  | Judul kolom |
| 217 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:118` | judul | Email | TETAP |  | Judul kolom |
| 218 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:119` | judul | Status | TETAP |  | Judul kolom |
| 219 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:121` | sr-only/aria-label | Aksi | TETAP |  | Teks pembaca layar |
| 220 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:138` | chip/status | Aktif | TETAP |  | Status |
| 221 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:140, 163` | chip/status | Nonaktif | TETAP |  | Status |
| 222 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:145, 179` | tombol | Ubah | TETAP |  | Tombol |
| 223 | Penghuni (juga Pemilik) | `resources/views/pages/penghuni/⚡index.blade.php:168` | meta/keterangan kecil | Kamar {n} (ponsel) | TETAP |  | Data |
| 224 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:170` | pesan galat | NIK harus 16 angka. Baru {n} angka. | TETAP |  | Pesan galat |
| 225 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:189, 190` | pesan galat | Pilih akun penghuni. | TETAP |  | Pesan galat |
| 226 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:191` | pesan galat | Akun ini sudah punya data penghuni. | TETAP |  | Pesan galat |
| 227 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:192` | pesan galat | Nama lengkap wajib diisi. | TETAP |  | Pesan galat |
| 228 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:193` | pesan galat | Nama lengkap paling banyak 100 karakter. | TETAP |  | Pesan galat |
| 229 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:194` | pesan galat | Nomor identitas wajib diisi. | TETAP |  | Pesan galat |
| 230 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:195` | pesan galat | NIK hanya berisi angka. | TETAP |  | Pesan galat |
| 231 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:196` | pesan galat | Nomor telepon wajib diisi. | TETAP |  | Pesan galat |
| 232 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:197` | pesan galat | Isi nomor yang diawali 0 atau +62, 10 sampai 14 angka. | TETAP |  | Pesan galat |
| 233 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:225` | toast | Data {nama} disimpan. | TETAP |  | Toast hasil aksi |
| 234 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:244` | toast | {nama} ditandai sudah keluar. | TETAP |  | Toast hasil aksi |
| 235 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:260` | toast | {nama} aktif kembali. | TETAP |  | Toast hasil aksi |
| 236 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:266` | pesan galat | Satu, Dua, Tiga, Empat (jumlah isian di ringkasan galat) | TETAP |  | Pesan galat |
| 237 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:274` | tautan | Penghuni | TETAP |  | Navigasi |
| 238 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:276` | sr-only/aria-label | Kembali ke penghuni | TETAP |  | Teks pembaca layar |
| 239 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:279` | judul | Ubah Penghuni / Tambah Penghuni | TETAP |  | Judul |
| 240 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:287` | pesan galat | {jumlah} isian perlu diperbaiki sebelum disimpan. | TETAP |  | Pesan galat |
| 241 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:295` | kotak info/catatan | Semua akun penghuni sudah punya data. Buat akun baru di Akun. | TETAP |  | Callout yang memblokir aksi |
| 242 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:300, 314` | label isian | Akun | TETAP |  | Label |
| 243 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:301` | placeholder | Pilih akun penghuni | TETAP |  | Label |
| 244 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:307` | bantuan isian | Hanya akun yang belum punya data. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 245 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:316` | bantuan isian | Akun tidak bisa diganti. | HAPUS |  | Keputusan tim: email tampil sebagai teks, bukan isian |
| 246 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:321` | label isian | Nama Lengkap | TETAP |  | Label |
| 247 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:328` | label isian | Nomor Identitas | TETAP |  | Label |
| 248 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:332` | meta/keterangan kecil | Tersimpan: {nomor}. | TETAP |  | Data |
| 249 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:334` | tombol | Sembunyikan | TETAP |  | Tombol |
| 250 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:336` | tombol | Tampilkan | TETAP |  | Tombol |
| 251 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:338` | bantuan isian | Kosongkan bila tidak berubah. | TETAP |  | Keputusan tim |
| 252 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:345` | label isian | Nomor Telepon | TETAP |  | Label |
| 253 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:353` | tombol | Simpan | TETAP |  | Tombol |
| 254 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:355` | tombol | Batal | TETAP |  | Tombol |
| 255 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:363` | judul kartu | Penghuni Keluar | TETAP |  | Judul |
| 256 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:367, 370` | tombol | Tandai Keluar | TETAP |  | Tombol |
| 257 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:376` | judul kartu | Sudah Keluar | TETAP |  | Judul |
| 258 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:377` | tombol | Aktifkan | TETAP |  | Tombol |
| 259 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:387` | judul | Tandai {nama} Keluar? | TETAP |  | Judul |
| 260 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:388` | tombol | Tandai Keluar | TETAP |  | Tombol |
| 261 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:393` | isi dialog | Email | TETAP |  | Label |
| 262 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:395` | isi dialog | Telepon | TETAP |  | Label |
| 263 | Tambah/Ubah Penghuni | `resources/views/pages/penghuni/⚡form.blade.php:399` | isi dialog | Penghuni ini akan ditandai sudah keluar tanpa menghapus riwayat dan akun loginnya. Anda bisa mengaktifkannya lagi. | TETAP |  | Akibat tindakan |
| 264 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:18` | judul | Kontrak | TETAP |  | Judul |
| 265 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:211` | pesan galat | Tanggal keluar harus setelah tanggal mulai kontrak. | TETAP |  | Pesan galat |
| 266 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:213` | pesan galat | Tanggal keluar tidak boleh setelah hari ini. | TETAP |  | Pesan galat |
| 267 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:215` | pesan galat | Tanggal keluar tidak boleh setelah tanggal berakhir kontrak. | TETAP |  | Pesan galat |
| 268 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:220` | pesan galat | Isi tanggal keluar. | TETAP |  | Pesan galat |
| 269 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:221` | pesan galat | Isi tanggal keluar yang valid. | TETAP |  | Pesan galat |
| 270 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:241` | toast | Kontrak Kamar {n} diakhiri. | TETAP |  | Toast hasil aksi |
| 271 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:254` | judul | Kontrak | TETAP |  | Judul |
| 272 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:257` | tombol | Buat | TETAP |  | Tombol |
| 273 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:266, 280` | judul kartu | Segera Berakhir | TETAP |  | Judul |
| 274 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:269` | kotak info/catatan | Batas peringatan belum diatur. | TETAP |  | Callout yang memblokir aksi |
| 275 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:271` | tautan | Atur di Pengaturan | TETAP |  | Navigasi |
| 276 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:284` | keadaan kosong | Tidak ada kontrak yang akan berakhir. [PRD F-08] | TETAP |  | Teks dikunci PRD |
| 277 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:293` | meta/keterangan kecil | Berakhir {tanggal} | TETAP |  | Data |
| 278 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:296` | chip/status | {n} Hari Lagi | TETAP |  | Status |
| 279 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:298` | chip/status | Berakhir Hari Ini | TETAP |  | Status |
| 280 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:300` | chip/status | Lewat {n} Hari | TETAP |  | Status |
| 281 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:304, 367, 399` | tombol | Perpanjang | TETAP |  | Tombol |
| 282 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:305, 368, 400` | tombol | Akhiri | TETAP |  | Tombol |
| 283 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:326` | tombol | Buat | TETAP |  | Tombol |
| 284 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:315` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 285 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:316` | chip/status | Semua {n} | TETAP |  | Chip |
| 286 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:317` | chip/status | Aktif {n} | TETAP |  | Chip |
| 287 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:318` | chip/status | Berakhir {n} | TETAP |  | Chip |
| 288 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:324` | keadaan kosong | Belum ada kontrak. | TETAP |  | Keadaan kosong |
| 289 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:324` | keadaan kosong | Tidak ada kontrak yang sesuai filter. | TETAP |  | Keadaan kosong |
| 290 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:336` | judul | Kamar | TETAP |  | Judul kolom |
| 291 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:337` | judul | Penghuni | TETAP |  | Judul kolom |
| 292 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:338` | judul | Mulai | TETAP |  | Judul kolom |
| 293 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:339` | judul | Berakhir | TETAP |  | Judul kolom |
| 294 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:340` | judul | Tarif | TETAP |  | Judul kolom |
| 295 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:341` | judul | Status | TETAP |  | Judul kolom |
| 296 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:343` | sr-only/aria-label | Aksi | TETAP |  | Teks pembaca layar |
| 297 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:358` | chip/status | Aktif | TETAP |  | Status |
| 298 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:360, 394` | chip/status | Berakhir | TETAP |  | Status |
| 299 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:392` | meta/keterangan kecil | {tanggal} sampai {tanggal} | TETAP |  | Data |
| 300 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:415` | judul | Akhiri Kontrak Kamar {n}? | TETAP |  | Judul |
| 301 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:416` | tombol | Akhiri | TETAP |  | Tombol |
| 302 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:421` | isi dialog | Penghuni | TETAP |  | Label |
| 303 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:423` | isi dialog | Masa Sewa | TETAP |  | Label |
| 304 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:425` | isi dialog | Tarif | TETAP |  | Label |
| 305 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:429` | isi dialog | Kamar {n} akan menjadi kosong, sedangkan tagihan dan status penghuni tetap. Kontrak yang diakhiri tidak bisa diaktifkan lagi. | TETAP |  | Akibat tindakan |
| 306 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:432` | isi dialog | Kontrak belum dimulai, tanggalnya tidak diubah. | TETAP |  | Akibat tindakan |
| 307 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:438` | label isian | Tanggal Keluar | TETAP |  | Label |
| 308 | Kontrak (juga Pemilik) | `resources/views/pages/kontrak/⚡index.blade.php:440` | isi dialog | Menggantikan tanggal berakhir. | TETAP |  | Akibat tindakan |
| 309 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:170, 251, 298` | pesan galat | Isi tarif dalam rupiah, lebih dari 0. | TETAP |  | Pesan galat |
| 310 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:204, 247` | pesan galat | Pilih penghuni. | TETAP |  | Pesan galat |
| 311 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:206` | pesan galat | Penghuni ini sudah punya kontrak aktif. | TETAP |  | Pesan galat |
| 312 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:217, 248` | pesan galat | Pilih kamar. | TETAP |  | Pesan galat |
| 313 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:219` | pesan galat | Kamar {n} sudah terisi. [PRD F-07] | TETAP |  | Teks dikunci PRD |
| 314 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:228` | pesan galat | Isi tanggal mulai yang valid. | TETAP |  | Pesan galat |
| 315 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:237, 289` | pesan galat | Isi tanggal berakhir yang valid. | TETAP |  | Pesan galat |
| 316 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:239, 291` | pesan galat | Harus setelah tanggal mulai. | TETAP |  | Pesan galat |
| 317 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:241` | pesan galat | Tanggal berakhir sudah lewat. Kontrak yang sudah selesai dicatat lewat migrasi data. | PERSINGKAT | Tanggal berakhir sudah lewat. | Catatan di belakang layar, keputusan tim |
| 318 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:249` | pesan galat | Isi tanggal mulai. | TETAP |  | Pesan galat |
| 319 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:250, 297` | pesan galat | Isi tanggal berakhir. | TETAP |  | Pesan galat |
| 320 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:272` | toast | Kontrak Kamar {n} untuk {nama} disimpan. | TETAP |  | Toast hasil aksi |
| 321 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:310` | toast | Kontrak Kamar {n} diperpanjang sampai {tanggal}. | TETAP |  | Toast hasil aksi |
| 322 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:319` | pesan galat | Satu, Dua, Tiga, Empat, Lima (jumlah isian di ringkasan galat) | TETAP |  | Pesan galat |
| 323 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:324` | judul | Perpanjang Kontrak / Buat Kontrak | TETAP |  | Judul |
| 324 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:329` | tautan | Kontrak | TETAP |  | Navigasi |
| 325 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:331` | sr-only/aria-label | Kembali ke kontrak | TETAP |  | Teks pembaca layar |
| 326 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:341` | pesan galat | {jumlah} isian perlu diperbaiki sebelum disimpan. | TETAP |  | Pesan galat |
| 327 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:349` | judul | Penghuni (ringkasan perpanjang) | TETAP |  | Label |
| 328 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:353` | judul | Kamar (ringkasan perpanjang) | TETAP |  | Label |
| 329 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:357` | judul | Mulai (ringkasan perpanjang) | TETAP |  | Label |
| 330 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:363` | kotak info/catatan | Kontrak lama ({tanggal} sampai {tanggal}) ditutup dan tersimpan di riwayat. | HAPUS |  | Keputusan tim |
| 331 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:368, 461` | label isian | Berakhir | TETAP |  | Label |
| 332 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:374, 467` | label isian | Tarif | TETAP |  | Label |
| 333 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:376, 469` | label isian | Rp (awalan isian) | TETAP |  | Label |
| 334 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:387` | meta/keterangan kecil | Tarif lama {nominal}. | TETAP |  | Data tarif lama yang tidak tampil di tempat lain |
| 335 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:394` | kotak info/catatan | Semua penghuni aktif sudah punya kontrak. Tambah penghuni dulu. | TETAP |  | Callout yang memblokir aksi |
| 336 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:398` | label isian | Penghuni | TETAP |  | Label |
| 337 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:399` | placeholder | Pilih penghuni | TETAP |  | Label |
| 338 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:404` | bantuan isian | Hanya penghuni tanpa kontrak aktif. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 339 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:411` | label isian | Kamar | TETAP |  | Label |
| 340 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:419` | chip/status | Kosong | TETAP |  | Status |
| 341 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:421` | chip/status | s.d. {tanggal} | TETAP |  | Status |
| 342 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:422` | chip/status | Terisi | TETAP |  | Status |
| 343 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:455` | label isian | Mulai | TETAP |  | Label |
| 344 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:481` | bantuan isian | Diisi dari tarif Kamar {n}. | HAPUS |  | Catatan di belakang layar; tarif sudah terlihat di isian |
| 345 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:481` | bantuan isian | Pilih kamar untuk mengisi tarif. | HAPUS |  | Menjelaskan cara kerja sistem |
| 346 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:490` | tombol | Simpan | TETAP |  | Tombol |
| 347 | Buat/Perpanjang Kontrak | `resources/views/pages/kontrak/⚡form.blade.php:492` | tombol | Batal | TETAP |  | Tombol |
| 348 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:20` | judul | Riwayat | TETAP |  | Judul |
| 349 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:328` | judul | Riwayat | TETAP |  | Judul |
| 350 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:332` | judul kartu | Pembayaran | TETAP |  | Label |
| 351 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:333` | judul kartu | Total | TETAP |  | Label |
| 352 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:342` | label isian | Penghuni | TETAP |  | Label |
| 353 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:344, 355` | label isian | Semua (pilihan filter Penghuni) | TETAP |  | Label |
| 354 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:346` | chip/status | (sudah keluar) di pilihan penghuni | TETAP |  | Status |
| 355 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:353` | label isian | Periode | TETAP |  | Label |
| 356 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:355` | label isian | Semua (pilihan filter Periode) | TETAP |  | Label |
| 357 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:369` | keadaan kosong | Tidak ada pembayaran untuk filter ini. | TETAP |  | Keadaan kosong |
| 358 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:369` | keadaan kosong | Belum ada pembayaran yang diterima. | TETAP |  | Keadaan kosong |
| 359 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:377` | judul | Periode, Penghuni, Kamar, Nominal, Dibayar, Diterima (judul kolom) | TETAP |  | Judul kolom |
| 360 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:377` | judul | Odoo (judul kolom status pembukuan) | PERSINGKAT | Pembukuan | Istilah teknis diganti, keputusan tim |
| 361 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:390` | chip/status | (sudah keluar) | TETAP |  | Status |
| 362 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:397` | meta/keterangan kecil | oleh {nama} | TETAP |  | Akuntabilitas penerimaan uang untuk pemilik, keputusan tim |
| 363 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:402, 435` | meta/keterangan kecil | Riwayat lama, tidak dikirim ke Odoo (title) | HAPUS |  | Catatan di belakang layar dan istilah Odoo di luar halaman Sinkronisasi |
| 364 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:402, 435` | chip/status | Data Migrasi | PERSINGKAT | Data Lama | Istilah teknis diganti, keputusan tim |
| 365 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:404, 437` | chip/status | Terkirim | TETAP |  | Status |
| 366 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:406, 439` | chip/status | Menunggu | TETAP |  | Status |
| 367 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:408, 410, 441, 443` | tautan | Gagal | TETAP |  | Status |
| 368 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:431` | meta/keterangan kecil | {periode}, Kamar {n}, Diterima {waktu} (ponsel) | TETAP |  | Data |
| 369 | Riwayat (juga Pemilik) | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:433` | meta/keterangan kecil | Odoo (label status di kartu ponsel) | PERSINGKAT | Pembukuan | Istilah teknis diganti, keputusan tim |
| 370 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:23` | judul | Keluhan | TETAP |  | Judul |
| 371 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:159` | meta/keterangan kecil | Area Bersama | TETAP |  | Data |
| 372 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:162` | meta/keterangan kecil | Kamar {n} | TETAP |  | Data |
| 373 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:238` | pesan galat | Pilih vendor. | TETAP |  | Pesan galat |
| 374 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:239, 240` | pesan galat | Vendor tidak ditemukan. | TETAP |  | Pesan galat |
| 375 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:241` | pesan galat | Isi jenis kerusakan. | TETAP |  | Pesan galat |
| 376 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:242` | pesan galat | Jenis kerusakan paling banyak 100 karakter. | TETAP |  | Pesan galat |
| 377 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:243` | pesan galat | Isi instruksi untuk vendor. | TETAP |  | Pesan galat |
| 378 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:244` | pesan galat | Instruksi paling banyak 1.000 karakter. | TETAP |  | Pesan galat |
| 379 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:258` | toast | {nomor} ditugaskan ke {vendor}. | TETAP |  | Toast hasil aksi |
| 380 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:275` | chip/status | Semua {n} | TETAP |  | Chip |
| 381 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:276` | chip/status | Perlu Ditangani {n} | TETAP |  | Chip |
| 382 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:277` | chip/status | Diajukan {n} | TETAP |  | Chip |
| 383 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:278` | chip/status | Ditugaskan {n} | TETAP |  | Chip |
| 384 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:279` | chip/status | Dikerjakan {n} | TETAP |  | Chip |
| 385 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:280` | chip/status | Selesai {n} | TETAP |  | Chip |
| 386 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:286` | judul | Keluhan | TETAP |  | Judul |
| 387 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:288` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 388 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:301` | keadaan kosong | Belum ada keluhan. | TETAP |  | Keadaan kosong |
| 389 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:301` | keadaan kosong | Tidak ada tiket yang sesuai filter. | TETAP |  | Keadaan kosong |
| 390 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:308` | sr-only/aria-label | Daftar tiket keluhan | TETAP |  | Teks pembaca layar |
| 391 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:327` | chip/status | Belum Ada Vendor | TETAP |  | Status |
| 392 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:355` | sr-only/aria-label | Kembali ke daftar tiket | TETAP |  | Teks pembaca layar |
| 393 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:372` | judul | Pelapor | TETAP |  | Label |
| 394 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:375` | judul | Diajukan | TETAP |  | Label |
| 395 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:378` | judul | Deskripsi | TETAP |  | Label |
| 396 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:382` | judul | Vendor | TETAP |  | Label |
| 397 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:385` | judul | Jenis Kerusakan | TETAP |  | Label |
| 398 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:388` | judul | Instruksi | TETAP |  | Label |
| 399 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:393` | judul | Biaya Perbaikan | TETAP |  | Label |
| 400 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:397` | judul | Durasi | TETAP |  | Label |
| 401 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:404` | judul kartu | Riwayat Status | TETAP |  | Judul |
| 402 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:410` | meta/keterangan kecil | {nama pengubah} di riwayat status | TETAP |  | Data riwayat status (F-18) |
| 403 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:422` | judul kartu | Tugaskan Vendor | TETAP |  | Judul |
| 404 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:425` | label isian | Vendor | TETAP |  | Label |
| 405 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:427` | keadaan kosong | Belum ada vendor aktif. Tambah di Akun. | TETAP |  | Keadaan kosong |
| 406 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:459` | label isian | Jenis Kerusakan | TETAP |  | Label |
| 407 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:464` | placeholder | Contoh: Freon habis | TETAP |  | Contoh format isian |
| 408 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:479` | label isian | Instruksi | TETAP |  | Label |
| 409 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:484` | placeholder | Contoh: Cek freon dan bersihkan filter | TETAP |  | Contoh format isian |
| 410 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:504` | tombol | Tugaskan | TETAP |  | Tombol |
| 411 | Keluhan (juga Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php:505` | tombol | Menugaskan… | TETAP |  | Keadaan memuat |
| 412 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:16` | judul | Sinkronisasi | TETAP |  | Judul |
| 413 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:101` | meta/keterangan kecil | {nama}, Kamar {n}, {periode} (sumber) | TETAP |  | Data |
| 414 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:151` | toast | {referensi} dikirim ulang. | TETAP |  | Toast hasil aksi |
| 415 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:241` | chip/status | Semua {n} | TETAP |  | Chip |
| 416 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:241` | keadaan kosong | Belum ada transaksi untuk Odoo. | TETAP |  | Keadaan kosong di halaman Sinkronisasi |
| 417 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:242` | chip/status | Gagal {n} | TETAP |  | Chip |
| 418 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:242` | keadaan kosong | Tidak ada transaksi yang gagal. | TETAP |  | Keadaan kosong |
| 419 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:243` | chip/status | Menunggu {n} | TETAP |  | Chip |
| 420 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:243` | keadaan kosong | Tidak ada transaksi yang menunggu. | TETAP |  | Keadaan kosong |
| 421 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:244` | chip/status | Terkirim {n} | TETAP |  | Chip |
| 422 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:244` | keadaan kosong | Belum ada transaksi yang terkirim. | TETAP |  | Keadaan kosong |
| 423 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:249` | judul | Sinkronisasi | TETAP |  | Judul |
| 424 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:251` | sr-only/aria-label | Saring status | TETAP |  | Teks pembaca layar |
| 425 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:277` | judul | Referensi, Jenis, Sumber, Status, Waktu (judul kolom) | TETAP |  | Judul kolom |
| 426 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:281` | sr-only/aria-label | Aksi | TETAP |  | Teks pembaca layar |
| 427 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:312, 351` | tombol | Kirim Ulang | TETAP |  | Tombol |
| 428 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:313, 352` | tombol | Mengirim… | TETAP |  | Keadaan memuat |
| 429 | Sinkronisasi | `resources/views/pages/sinkronisasi-erp/⚡index.blade.php:345` | meta/keterangan kecil | Waktu {waktu} (ponsel) | TETAP |  | Data |
| 430 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:10` | judul | Pengaturan | TETAP |  | Judul |
| 431 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:34` | pesan galat | Baris pengaturan_operasional (id 1) tidak ada. Jalankan PengaturanOperasionalSeeder. (log dan halaman galat teknis) | TETAP |  | Pesan galat |
| 432 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:51` | pesan galat | Pilih tanggal jatuh tempo antara 1 dan 28. | TETAP |  | Pesan galat |
| 433 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:52` | pesan galat | Isi batas antara 1 dan 365 hari. | TETAP |  | Pesan galat |
| 434 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:67` | toast | Pengaturan disimpan. | TETAP |  | Toast hasil aksi |
| 435 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:72` | judul | Pengaturan | TETAP |  | Judul |
| 436 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:77` | kotak info/catatan | Tanggal jatuh tempo belum diatur, tagihan belum bisa diterbitkan. | TETAP |  | Callout yang memblokir aksi |
| 437 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:83` | label isian | Tanggal Jatuh Tempo | TETAP |  | Label |
| 438 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:84` | bantuan isian | Tanggal 1 sampai 28, setiap bulan. | PERSINGKAT | Tanggal 1–28. | Keputusan tim |
| 439 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:111` | label isian | Batas Peringatan | TETAP |  | Label |
| 440 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:112` | bantuan isian | Jumlah hari sebelum kontrak berakhir. | HAPUS |  | Keputusan tim: diganti akhiran "hari" di dalam isian |
| 441 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:125` | label isian | hari (satuan, menjadi akhiran di dalam isian) | TETAP |  | Keputusan tim |
| 442 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:134` | tombol | Simpan | TETAP |  | Tombol |
| 443 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:135` | tombol | Menyimpan | TETAP |  | Keadaan memuat |
| 444 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php:139` | meta/keterangan kecil | Terakhir diubah oleh {nama} | HAPUS |  | Keputusan tim |

## Penghuni

| No | Halaman | Lokasi (file:baris) | Jenis | Teks lama | Keputusan | Teks baru | Alasan |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 445 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:18` | judul | Tagihan | TETAP |  | Judul |
| 446 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:194, 201` | judul | Tagihan | TETAP |  | Judul |
| 447 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:195` | keadaan kosong | Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 448 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:213` | judul kartu | Tagihan {bulan} | TETAP |  | Judul |
| 449 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:217` | chip/status | Jatuh Tempo {tanggal}, Telat {n} Hari | TETAP |  | Status |
| 450 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:219` | meta/keterangan kecil | Jatuh Tempo Hari Ini | TETAP |  | Data |
| 451 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:221` | meta/keterangan kecil | Jatuh Tempo {tanggal}, {n} Hari Lagi | TETAP |  | Data |
| 452 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:226` | pesan galat | Ditolak, {tanggal}: {alasan} | TETAP |  | Alasan penolakan [PRD F-11] |
| 453 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:236` | tombol | Unggah Bukti | TETAP |  | Tombol |
| 454 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:238` | meta/keterangan kecil | {n} tagihan lain belum dibayar. | TETAP |  | Data |
| 455 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:243` | judul kartu | Bukti {bulan} sedang dicek pengelola. | TETAP |  | Status |
| 456 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:244` | chip/status | Menunggu | TETAP |  | Status |
| 457 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:246` | judul kartu | Semua tagihan sudah lunas. | TETAP |  | Status |
| 458 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:247` | chip/status | Lunas | TETAP |  | Status |
| 459 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:249` | keadaan kosong | Belum ada tagihan. [PRD F-11] | TETAP |  | Teks dikunci PRD |
| 460 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:256` | judul kartu | Riwayat Tagihan | TETAP |  | Judul |
| 461 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:263` | judul | Periode | TETAP |  | Judul kolom |
| 462 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:264` | judul | Jatuh Tempo | TETAP |  | Judul kolom |
| 463 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:265` | judul | Nominal | TETAP |  | Judul kolom |
| 464 | Tagihan | `resources/views/pages/tagihan-saya/⚡index.blade.php:266` | judul | Status | TETAP |  | Judul kolom |
| 465 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:15` | chip/status | Belum Bayar / Belum Bayar, {tanggal} | TETAP |  | Status |
| 466 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:16` | tombol | Unggah | TETAP |  | Tombol |
| 467 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:16` | sr-only/aria-label | {periode} (pelengkap tombol Unggah) | TETAP |  | Teks pembaca layar |
| 468 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:19` | pesan galat | Ditolak, {tanggal}: {alasan} | TETAP |  | Alasan penolakan [PRD F-11] |
| 469 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:22` | chip/status | Menunggu, {tanggal} | TETAP |  | Status |
| 470 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:24` | chip/status | Lunas, {tanggal} | TETAP |  | Status |
| 471 | Tagihan (status per tagihan) | `resources/views/components/status-tagihan.blade.php:28` | chip/status | Telat {n} Hari | TETAP |  | Status |
| 472 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:18` | judul | Unggah Bukti | TETAP |  | Judul |
| 473 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:23` | pesan galat | Berkas harus JPG, PNG, atau PDF dan paling besar 5 MB. [PRD F-12] | TETAP |  | Teks dikunci PRD |
| 474 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:116` | pesan galat | Pilih berkas bukti transfer. | TETAP |  | Pesan galat |
| 475 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:122` | pesan galat | Isi tanggal transfer. | TETAP |  | Pesan galat |
| 476 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:123` | pesan galat | Tanggal transfer tidak valid. | TETAP |  | Pesan galat |
| 477 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:124` | pesan galat | Tanggal transfer tidak boleh setelah hari ini. | TETAP |  | Pesan galat |
| 478 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:184` | pesan galat | Bukti belum terkirim karena ada gangguan. Pilih berkas lagi lalu coba kirim. | TETAP |  | Pesan galat |
| 479 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:191` | toast | Bukti {bulan} terkirim. Tunggu pengelola mengecek. | TETAP |  | Toast hasil aksi |
| 480 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:207` | sr-only/aria-label | Kembali | TETAP |  | Teks pembaca layar |
| 481 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:212` | tautan | Tagihan | TETAP |  | Navigasi |
| 482 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:213` | judul | Unggah Bukti | TETAP |  | Judul |
| 483 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:221` | judul kartu | Nominal | TETAP |  | Label |
| 484 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:232` | label isian | Bukti | TETAP |  | Label |
| 485 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:246` | keadaan kosong | Belum ada berkas | TETAP |  | Keadaan kosong |
| 486 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:248` | meta/keterangan kecil | Mengunggah… | TETAP |  | Keadaan memuat |
| 487 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:250` | tombol | Pilih / Ganti | TETAP |  | Tombol |
| 488 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:260` | bantuan isian | JPG, PNG, PDF. Maks 5 MB. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 489 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:267` | label isian | Tanggal Transfer | TETAP |  | Label |
| 490 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:292` | tombol | Kirim | TETAP |  | Tombol |
| 491 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:293` | tombol | Mengunggah… | TETAP |  | Keadaan memuat |
| 492 | Unggah Bukti | `resources/views/pages/tagihan-saya/⚡unggah.blade.php:294` | tombol | Mengirim… | TETAP |  | Keadaan memuat |
| 493 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:243, 252` | judul | Riwayat | TETAP |  | Judul |
| 494 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:244` | keadaan kosong | Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 495 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:257` | judul kartu | Sejak | TETAP |  | Label |
| 496 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:258` | judul kartu | Jumlah | TETAP |  | Label |
| 497 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:258` | meta/keterangan kecil | {n} kali | TETAP |  | Data |
| 498 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:259` | judul kartu | Total | TETAP |  | Label |
| 499 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:265` | keadaan kosong | Belum ada pembayaran yang diterima. | TETAP |  | Keadaan kosong |
| 500 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:272` | judul | Periode, Dibayar, Diterima, Nominal, Status (judul kolom) | TETAP |  | Judul kolom |
| 501 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:284` | chip/status | Lunas | TETAP |  | Status |
| 502 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:297` | meta/keterangan kecil | Dibayar {tanggal}, Diterima {tanggal} (ponsel) | TETAP |  | Data |
| 503 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:299` | sr-only/aria-label | Lunas | TETAP |  | Teks pembaca layar |
| 504 | Riwayat | `resources/views/pages/riwayat-transaksi/⚡index.blade.php:313` | tombol | Lainnya | TETAP |  | Tombol |
| 505 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:14` | judul | Keluhan | TETAP |  | Judul |
| 506 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:54` | meta/keterangan kecil | Area Bersama | TETAP |  | Data |
| 507 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:57` | meta/keterangan kecil | Kamar {n} | TETAP |  | Data |
| 508 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:75, 78` | judul | Keluhan | TETAP |  | Judul |
| 509 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:76` | keadaan kosong | Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 510 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:80` | tombol | Ajukan | TETAP |  | Tombol |
| 511 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:87` | toast | Keluhan terkirim dengan nomor {nomor}. [PRD F-16] | TETAP |  | Teks dikunci PRD |
| 512 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:96` | keadaan kosong | Belum ada keluhan. | TETAP |  | Keadaan kosong |
| 513 | Keluhan | `resources/views/pages/keluhan-saya/⚡index.blade.php:122` | judul kartu | Riwayat Status | TETAP |  | Judul |
| 514 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:17` | judul | Ajukan Keluhan | TETAP |  | Judul |
| 515 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:107, 108` | pesan galat | Pilih lokasi. | TETAP |  | Pesan galat |
| 516 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:109, 110` | pesan galat | Pilih fasilitas yang rusak. | TETAP |  | Pesan galat |
| 517 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:111` | pesan galat | Tulis deskripsi kerusakan. | TETAP |  | Pesan galat |
| 518 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:112` | pesan galat | Deskripsi paling banyak 1.000 karakter. | TETAP |  | Pesan galat |
| 519 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:172` | sr-only/aria-label | Kembali ke keluhan | TETAP |  | Teks pembaca layar |
| 520 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:177` | tautan | Keluhan | TETAP |  | Navigasi |
| 521 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:178` | judul | Ajukan Keluhan | TETAP |  | Judul |
| 522 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:182` | keadaan kosong | Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 523 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:198` | label isian | Lokasi | TETAP |  | Label |
| 524 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:205` | label isian | Kamar {n} / Kamar | TETAP |  | Label |
| 525 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:206` | label isian | Area Bersama | TETAP |  | Label |
| 526 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:223` | bantuan isian | Belum ada kontrak aktif. | TETAP |  | Alasan tombol nonaktif |
| 527 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:232` | label isian | Fasilitas | TETAP |  | Label |
| 528 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:234` | keadaan kosong | Belum ada fasilitas di lokasi ini. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 529 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:266` | label isian | Deskripsi | TETAP |  | Label |
| 530 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:271` | placeholder | Contoh: AC tidak dingin sejak kemarin malam | TETAP |  | Contoh format isian |
| 531 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:279` | bantuan isian | {n}/1.000 | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 532 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:291` | tombol | Kirim | TETAP |  | Tombol |
| 533 | Ajukan Keluhan | `resources/views/pages/keluhan-saya/⚡ajukan.blade.php:292` | tombol | Mengirim… | TETAP |  | Keadaan memuat |
| 534 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:10` | judul | Profil | TETAP |  | Judul |
| 535 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:76, 79` | judul | Profil | TETAP |  | Judul |
| 536 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:77` | keadaan kosong | Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 537 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:83` | judul kartu | Data Diri | TETAP |  | Judul |
| 538 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:85` | judul | Nama | TETAP |  | Label |
| 539 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:88` | judul | Email | TETAP |  | Label |
| 540 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:91` | judul | Telepon | TETAP |  | Label |
| 541 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:94` | judul | Nomor Identitas | TETAP |  | Label |
| 542 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:98` | tombol | Sembunyikan | TETAP |  | Tombol |
| 543 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:100` | tombol | Tampilkan | TETAP |  | Tombol |
| 544 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:107` | judul kartu | Kontrak | TETAP |  | Judul |
| 545 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:110` | judul | Kamar | TETAP |  | Label |
| 546 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:113` | judul | Masa Sewa | TETAP |  | Label |
| 547 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:114` | meta/keterangan kecil | {tanggal} sampai {tanggal} | TETAP |  | Data |
| 548 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:116` | judul | Tarif | TETAP |  | Label |
| 549 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:120` | keadaan kosong | Belum ada kontrak aktif. | TETAP |  | Keadaan kosong |
| 550 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:128` | sr-only/aria-label | Akun | TETAP |  | Teks pembaca layar |
| 551 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:130` | tautan | Ganti Password | TETAP |  | Navigasi |
| 552 | Profil | `resources/views/pages/profil-saya/⚡index.blade.php:135` | tombol | Keluar | TETAP |  | Tombol |
| 553 | Menu tab bawah | `resources/views/components/menu-tab-penghuni.blade.php:3` | sr-only/aria-label | Menu penghuni | TETAP |  | Teks pembaca layar |

## Vendor

| No | Halaman | Lokasi (file:baris) | Jenis | Teks lama | Keputusan | Teks baru | Alasan |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 554 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:16` | judul | Pekerjaan | TETAP |  | Judul |
| 555 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:104` | meta/keterangan kecil | Area Bersama | TETAP |  | Data |
| 556 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:107` | meta/keterangan kecil | Kamar {n} | TETAP |  | Data |
| 557 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:137` | toast | {nomor} mulai dikerjakan. | TETAP |  | Toast hasil aksi |
| 558 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:154` | judul | Pekerjaan | TETAP |  | Judul |
| 559 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:157` | keadaan kosong | Data vendor Anda belum dilengkapi pengelola. Hubungi pengelola kos. | TETAP |  | Keadaan kosong |
| 560 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:160` | judul kartu | Perlu Dikerjakan {n} | TETAP |  | Judul |
| 561 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:164` | keadaan kosong | Belum ada permintaan kerja. [PRD F-19] | TETAP |  | Teks dikunci PRD |
| 562 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:176` | meta/keterangan kecil | Ditugaskan {tanggal} | TETAP |  | Data |
| 563 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:199` | tombol | Mulai | TETAP |  | Tombol |
| 564 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:201` | tombol | Selesai | TETAP |  | Tombol |
| 565 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:212` | judul kartu | Selesai Bulan Ini | TETAP |  | Judul |
| 566 | Pekerjaan | `resources/views/pages/permintaan-kerja/⚡index.blade.php:223` | meta/keterangan kecil | Selesai {tanggal} | TETAP |  | Data |
| 567 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:15` | judul | Pekerjaan | TETAP |  | Judul |
| 568 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:49` | meta/keterangan kecil | Area Bersama | TETAP |  | Data |
| 569 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:52` | meta/keterangan kecil | Kamar {n} | TETAP |  | Data |
| 570 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:99` | pesan galat | Biaya harus angka rupiah, 0 atau lebih. | TETAP |  | Pesan galat |
| 571 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:101` | pesan galat | Biaya paling banyak Rp100.000.000. | TETAP |  | Pesan galat |
| 572 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:106` | pesan galat | Isi biaya perbaikan. | TETAP |  | Pesan galat |
| 573 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:130` | toast | {nomor} mulai dikerjakan. | TETAP |  | Toast hasil aksi |
| 574 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:184` | toast | {nomor} selesai. Biaya {nominal} tercatat. | TETAP |  | Toast hasil aksi |
| 575 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:208` | tautan | Pekerjaan | TETAP |  | Navigasi |
| 576 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:213` | sr-only/aria-label | Kembali ke pekerjaan | TETAP |  | Teks pembaca layar |
| 577 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:231` | judul | Lokasi | TETAP |  | Label |
| 578 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:234` | judul | Fasilitas | TETAP |  | Label |
| 579 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:237` | judul | Deskripsi | TETAP |  | Label |
| 580 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:240` | judul | Instruksi | TETAP |  | Label |
| 581 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:243` | judul | Ditugaskan | TETAP |  | Label |
| 582 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:247` | judul | Biaya Perbaikan | TETAP |  | Label |
| 583 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:250` | judul | Selesai | TETAP |  | Label |
| 584 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:256` | judul kartu | Riwayat Status | TETAP |  | Judul |
| 585 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:276` | tombol | Mulai | TETAP |  | Tombol |
| 586 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:280` | judul kartu | Selesaikan | TETAP |  | Judul |
| 587 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:283` | label isian | Biaya Perbaikan | TETAP |  | Label |
| 588 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:285` | label isian | Rp (awalan isian) | TETAP |  | Label |
| 589 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:297` | bantuan isian | Isi 0 bila tanpa biaya. | TETAP |  | Batasan isian yang tidak terlihat dari kontrol |
| 590 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:302` | tombol | Selesai | TETAP |  | Tombol |
| 591 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:308` | judul | Selesaikan {nomor}? | TETAP |  | Judul |
| 592 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:309` | tombol | Selesai | TETAP |  | Tombol |
| 593 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:315` | isi dialog | Biaya | TETAP |  | Label |
| 594 | Detail pekerjaan | `resources/views/pages/permintaan-kerja/⚡detail.blade.php:319` | isi dialog | Pekerjaan akan ditandai selesai dan biaya dicatat. Setelah ini tidak bisa diubah. | TETAP |  | Akibat tindakan |

## Tamu/Masuk

| No | Halaman | Lokasi (file:baris) | Jenis | Teks lama | Keputusan | Teks baru | Alasan |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 595 | Masuk | `resources/views/pages/auth/login.blade.php:17` | judul | Masuk (judul tab) | TETAP |  | Judul |
| 596 | Masuk | `resources/views/pages/auth/login.blade.php:22` | judul | Masuk | TETAP |  | Judul |
| 597 | Masuk | `resources/views/pages/auth/login.blade.php:34` | label isian | Email | TETAP |  | Label |
| 598 | Masuk | `resources/views/pages/auth/login.blade.php:42` | placeholder | nama@contoh.com | TETAP |  | Contoh format isian |
| 599 | Masuk | `resources/views/pages/auth/login.blade.php:51` | label isian | Password | TETAP |  | Label |
| 600 | Masuk | `resources/views/pages/auth/login.blade.php:63` | tombol | Masuk | TETAP |  | Tombol |
| 601 | Masuk | `resources/views/pages/auth/login.blade.php:66` | meta/keterangan kecil | Lupa password? Hubungi pengelola kos. | TETAP |  | Satu-satunya jalan keluar bila lupa password |
| 602 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:13, 44` | sr-only/aria-label | Kost Putri Kirana | TETAP |  | Teks pembaca layar |
| 603 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:15, 46` | judul | Kost Putri / Kirana (wordmark) | TETAP |  | Merek |
| 604 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:16, 47` | judul | Kirana (wordmark) | TETAP |  | Merek |
| 605 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:33` | judul | Delapan kamar, satu catatan. | TETAP |  | Judul panel desktop |
| 606 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:34` | meta/keterangan kecil | Tagihan, bukti bayar, dan laporan kerusakan kos di Cikarang Utara tercatat di sini. | HAPUS |  | Menjelaskan cara kerja sistem |
| 607 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:37` | meta/keterangan kecil | Akun dibuat oleh pengelola kos. | HAPUS |  | Menjelaskan cara kerja sistem |
| 608 | Masuk (panel merek) | `resources/views/layouts/auth/simple.blade.php:50` | judul | Cek tagihan dan lapor kerusakan dari ponsel. | TETAP |  | Judul panel ponsel |
| 609 | Masuk (lang/id/auth.php) | `lang/id/auth.php:11` | pesan galat | Email atau password salah. [PRD F-01] | TETAP |  | Teks dikunci PRD |
| 610 | Masuk (lang/id/auth.php) | `lang/id/auth.php:12` | pesan galat | Password yang Anda masukkan salah. | TETAP |  | Pesan galat |
| 611 | Masuk (lang/id/auth.php) | `lang/id/auth.php:13` | pesan galat | Terlalu banyak percobaan masuk. Coba lagi dalam {n} detik. | TETAP |  | Pesan galat |
| 612 | Masuk (lang/id/auth.php) | `lang/id/auth.php:14` | pesan galat | Akun Anda nonaktif. Hubungi pengelola kos. | TETAP |  | Pesan galat |

## Semua

| No | Halaman | Lokasi (file:baris) | Jenis | Teks lama | Keputusan | Teks baru | Alasan |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 613 | Judul tab | `resources/views/partials/head.blade.php:6` | judul | {judul} - Kost Putri Kirana | TETAP |  | Judul |
| 614 | Akses ditolak (403) | `resources/views/errors/403.blade.php:5` | judul | Akses ditolak (judul tab) | TETAP |  | Judul |
| 615 | Akses ditolak (403) | `resources/views/errors/403.blade.php:16` | judul | Akses ditolak | TETAP |  | Judul |
| 616 | Akses ditolak (403) | `resources/views/errors/403.blade.php:17` | pesan galat | Anda tidak memiliki akses ke halaman ini. [PRD F-03] | TETAP |  | Teks dikunci PRD |
| 617 | Akses ditolak (403) | `resources/views/errors/403.blade.php:20` | tombol | Beranda | TETAP |  | Tombol |
| 618 | Akses ditolak (403) | `resources/views/errors/403.blade.php:23` | pesan galat | Kode 403 | TETAP |  | Pesan galat |
| 619 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:10` | judul | Ganti Password | TETAP |  | Judul |
| 620 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:37` | toast | Password berhasil diganti. | TETAP |  | Toast hasil aksi |
| 621 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:42` | judul | Ganti Password | TETAP |  | Judul |
| 622 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:47` | label isian | Password Lama | TETAP |  | Label |
| 623 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:55` | label isian | Password Baru | TETAP |  | Label |
| 624 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:63` | label isian | Konfirmasi Password | TETAP |  | Label |
| 625 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:72` | tombol | Simpan | TETAP |  | Tombol |
| 626 | Ganti Password | `resources/views/pages/settings/⚡security.blade.php:74` | tombol | Batal | TETAP |  | Tombol |
| 627 | Sidebar dan bilah atas | `resources/views/layouts/app/sidebar.blade.php:18` | sr-only/aria-label | Menu | TETAP |  | Teks pembaca layar |
| 628 | Sidebar dan bilah atas | `resources/views/layouts/app/sidebar.blade.php:28` | sr-only/aria-label | Tutup menu | TETAP |  | Teks pembaca layar |
| 629 | Sidebar dan bilah atas | `resources/views/layouts/app/sidebar.blade.php:36` | sr-only/aria-label | Menu utama | TETAP |  | Teks pembaca layar |
| 630 | Sidebar dan bilah atas | `resources/views/layouts/app/sidebar.blade.php:59` | sr-only/aria-label | Buka menu | TETAP |  | Teks pembaca layar |
| 631 | Logo | `resources/views/components/app-logo.blade.php:21` | sr-only/aria-label | Kost Putri Kirana, ke beranda | TETAP |  | Teks pembaca layar |
| 632 | Logo | `resources/views/components/app-logo.blade.php:24` | judul | Kost Putri (wordmark) | TETAP |  | Merek |
| 633 | Logo | `resources/views/components/app-logo.blade.php:31` | judul | Kirana (wordmark) | TETAP |  | Merek |
| 634 | Logo | `resources/views/components/app-logo-icon.blade.php:13` | sr-only/aria-label | Kost Putri Kirana | TETAP |  | Teks pembaca layar |
| 635 | Blok pengguna | `resources/views/components/menu-samping/pengguna.blade.php:9` | tautan | Ganti Password | TETAP |  | Navigasi |
| 636 | Blok pengguna | `resources/views/components/menu-samping/pengguna.blade.php:13` | tombol | Keluar | TETAP |  | Tombol |
| 637 | Item menu | `resources/views/components/menu-samping/item.blade.php:9` | sr-only/aria-label | {n} bukti menunggu | TETAP |  | Teks pembaca layar |
| 638 | Dialog konfirmasi | `resources/views/components/dialog-konfirmasi.blade.php:43` | sr-only/aria-label | Tutup | TETAP |  | Teks pembaca layar |
| 639 | Dialog konfirmasi | `resources/views/components/dialog-konfirmasi.blade.php:76` | tombol | Batal | TETAP |  | Tombol |
| 640 | Segmen periode (komponen diganti) | `resources/views/components/segmen-periode.blade.php:12` | sr-only/aria-label | Periode (pindah ke x-pemilih-periode) | TETAP |  | Teks pembaca layar |
| 641 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:69` | pesan galat | Passkeys are not supported in this browser. | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 642 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:79` | tombol | Add passkey | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 643 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:87` | label isian | Passkey name | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 644 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:89` | placeholder | e.g., MacBook Pro, iPhone | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 645 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:94` | bantuan isian | Give this passkey a name to help you identify it later. | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 646 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:104` | tombol | Register passkey | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 647 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:105` | tombol | Registering... | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 648 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:111` | tombol | Cancel | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 649 | passkey-registration (tidak dirender) | `resources/views/components/passkey-registration.blade.php:34` | placeholder | {browser} on {sistem operasi} | TETAP |  | Komponen sisa starter kit, tidak dirender halaman mana pun |
| 650 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:24` | tautan | Dasbor | TETAP |  | Navigasi |
| 651 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:34` | tautan | Verifikasi | TETAP |  | Navigasi |
| 652 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:25, 36` | tautan | Kamar | TETAP |  | Navigasi |
| 653 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:26, 37` | tautan | Penghuni | TETAP |  | Navigasi |
| 654 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:27, 38` | tautan | Kontrak | TETAP |  | Navigasi |
| 655 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:28, 35, 46` | tautan | Tagihan | TETAP |  | Navigasi |
| 656 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:29, 39, 47` | tautan | Riwayat | TETAP |  | Navigasi |
| 657 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:30, 40, 48` | tautan | Keluhan | TETAP |  | Navigasi |
| 658 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:41` | tautan | Sinkronisasi | TETAP |  | Navigasi |
| 659 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:42` | tautan | Akun | TETAP |  | Navigasi |
| 660 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:31, 43` | tautan | Pengaturan | TETAP |  | Navigasi |
| 661 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:49` | tautan | Profil | TETAP |  | Navigasi |
| 662 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:52` | tautan | Pekerjaan | TETAP |  | Navigasi |
| 663 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:77` | sr-only/aria-label | {n} bukti menunggu | TETAP |  | Teks pembaca layar |
| 664 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:83` | sr-only/aria-label | {n} sinkronisasi gagal | TETAP |  | Teks pembaca layar |
| 665 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:90` | sr-only/aria-label | {n} keluhan baru | TETAP |  | Teks pembaca layar |
| 666 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:107` | tautan | Tagihan (tab) | TETAP |  | Navigasi |
| 667 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:108` | tautan | Riwayat (tab) | TETAP |  | Navigasi |
| 668 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:109` | tautan | Keluhan (tab) | TETAP |  | Navigasi |
| 669 | Menu (label = judul halaman) | `app/Support/MenuNavigasi.php:110` | tautan | Profil (tab) | TETAP |  | Navigasi |
| 670 | Label enum JenisTransaksiErp | `app/Enums/JenisTransaksiErp.php:14` | chip/status | Pembayaran | TETAP |  | Status |
| 671 | Label enum JenisTransaksiErp | `app/Enums/JenisTransaksiErp.php:15` | chip/status | Biaya perbaikan | TETAP |  | Status |
| 672 | Label enum JenisTransaksiErp | `app/Enums/JenisTransaksiErp.php:16` | chip/status | Pemeliharaan | TETAP |  | Status |
| 673 | Label enum LokasiKeluhan | `app/Enums/LokasiKeluhan.php:13` | chip/status | Kamar | TETAP |  | Status |
| 674 | Label enum LokasiKeluhan | `app/Enums/LokasiKeluhan.php:14` | chip/status | Area bersama | TETAP |  | Status |
| 675 | Label enum PeranPengguna | `app/Enums/PeranPengguna.php:15` | chip/status | Pemilik | TETAP |  | Status |
| 676 | Label enum PeranPengguna | `app/Enums/PeranPengguna.php:16` | chip/status | Pengelola | TETAP |  | Status |
| 677 | Label enum PeranPengguna | `app/Enums/PeranPengguna.php:17` | chip/status | Penghuni | TETAP |  | Status |
| 678 | Label enum PeranPengguna | `app/Enums/PeranPengguna.php:18` | chip/status | Vendor | TETAP |  | Status |
| 679 | Label enum StatusAkun | `app/Enums/StatusAkun.php:13` | chip/status | Aktif | TETAP |  | Status |
| 680 | Label enum StatusAkun | `app/Enums/StatusAkun.php:14` | chip/status | Nonaktif | TETAP |  | Status |
| 681 | Label enum StatusKontrak | `app/Enums/StatusKontrak.php:13` | chip/status | Aktif | TETAP |  | Status |
| 682 | Label enum StatusKontrak | `app/Enums/StatusKontrak.php:14` | chip/status | Berakhir | TETAP |  | Status |
| 683 | Label enum StatusPenghuni | `app/Enums/StatusPenghuni.php:13` | chip/status | Aktif | TETAP |  | Status |
| 684 | Label enum StatusPenghuni | `app/Enums/StatusPenghuni.php:14` | chip/status | Nonaktif | TETAP |  | Status |
| 685 | Label enum StatusSinkronisasi | `app/Enums/StatusSinkronisasi.php:14` | chip/status | Menunggu | TETAP |  | Status |
| 686 | Label enum StatusSinkronisasi | `app/Enums/StatusSinkronisasi.php:15` | chip/status | Terkirim | TETAP |  | Status |
| 687 | Label enum StatusSinkronisasi | `app/Enums/StatusSinkronisasi.php:16` | chip/status | Gagal | TETAP |  | Status |
| 688 | Label enum StatusTagihan | `app/Enums/StatusTagihan.php:14` | chip/status | Belum dibayar | TETAP |  | Status |
| 689 | Label enum StatusTagihan | `app/Enums/StatusTagihan.php:15` | chip/status | Menunggu verifikasi | TETAP |  | Status |
| 690 | Label enum StatusTagihan | `app/Enums/StatusTagihan.php:16` | chip/status | Lunas | TETAP |  | Status |
| 691 | Label enum StatusTiket | `app/Enums/StatusTiket.php:15` | chip/status | Diajukan | TETAP |  | Status |
| 692 | Label enum StatusTiket | `app/Enums/StatusTiket.php:16` | chip/status | Ditugaskan | TETAP |  | Status |
| 693 | Label enum StatusTiket | `app/Enums/StatusTiket.php:17` | chip/status | Dikerjakan | TETAP |  | Status |
| 694 | Label enum StatusTiket | `app/Enums/StatusTiket.php:18` | chip/status | Selesai | TETAP |  | Status |
| 695 | Label enum StatusVerifikasi | `app/Enums/StatusVerifikasi.php:14` | chip/status | Menunggu | TETAP |  | Status |
| 696 | Label enum StatusVerifikasi | `app/Enums/StatusVerifikasi.php:15` | chip/status | Diterima | TETAP |  | Status |
| 697 | Label enum StatusVerifikasi | `app/Enums/StatusVerifikasi.php:16` | chip/status | Ditolak | TETAP |  | Status |
| 698 | Format tampilan | `app/Support/Format.php:20` | meta/keterangan kecil | - (nilai kosong) | TETAP |  | Data |
| 699 | Format tampilan | `app/Support/Format.php:42` | meta/keterangan kecil | hari ini {jam} | TETAP |  | Data |
| 700 | Format tampilan | `app/Support/Format.php:43` | meta/keterangan kecil | kemarin {jam} | TETAP |  | Data |
| 701 | Format tampilan | `app/Support/Format.php:69` | meta/keterangan kecil | {n} hari {n} jam (durasi) | TETAP |  | Data |
| 702 | Format tampilan | `app/Support/Format.php:73` | meta/keterangan kecil | Kurang dari 1 jam | TETAP |  | Data |
| 703 | Pesan dari kelas PHP | `app/Exceptions/KeluhanGagalDikirim.php:12` | pesan galat | Keluhan gagal dikirim. Coba lagi. | TETAP |  | Pesan galat |
| 704 | Pesan dari kelas PHP | `app/Exceptions/KontrakSudahBerakhir.php:12` | pesan galat | Kontrak ini sudah berakhir. | TETAP |  | Pesan galat |
| 705 | Pesan dari kelas PHP | `app/Policies/KontrakPolicy.php:19` | pesan galat | Kontrak ini sudah berakhir. | TETAP |  | Pesan galat |
| 706 | Pesan dari kelas PHP | `app/Exceptions/LokasiKeluhanTidakValid.php:13` | pesan galat | Belum ada kontrak aktif. Pilih Area Bersama. | TETAP |  | Pesan galat |
| 707 | Pesan dari kelas PHP | `app/Exceptions/LokasiKeluhanTidakValid.php:15` | pesan galat | Fasilitas tidak ada di lokasi ini. | TETAP |  | Pesan galat |
| 708 | Pesan dari kelas PHP | `app/Exceptions/PembayaranSudahDiputuskan.php:13` | pesan galat | Bukti ini sudah diputuskan. Daftar sudah diperbarui. | TETAP |  | Pesan galat |
| 709 | Pesan dari kelas PHP | `app/Exceptions/PenerbitanTagihanDitolak.php:14` | pesan galat | Atur tanggal jatuh tempo di Pengaturan dulu. | TETAP |  | Pesan galat |
| 710 | Pesan dari kelas PHP | `app/Exceptions/PenerbitanTagihanDitolak.php:19` | pesan galat | Periode di luar rentang: pilih dari 12 bulan lalu sampai bulan depan. | TETAP |  | Pesan galat |
| 711 | Pesan dari kelas PHP | `app/Exceptions/SinkronisasiTidakBisaDikirimUlang.php:13` | pesan galat | Status transaksi ini sudah berubah. Daftar sudah diperbarui. | TETAP |  | Pesan galat |
| 712 | Pesan dari kelas PHP | `app/Exceptions/StatusTiketBerubah.php:13` | pesan galat | Status tiket ini sudah berubah. Halaman sudah diperbarui. | TETAP |  | Pesan galat |
| 713 | Pesan dari kelas PHP | `app/Exceptions/TagihanTidakBisaDiberiBukti.php:13` | pesan galat | Tagihan ini sedang dicek atau sudah lunas, jadi tidak bisa diberi bukti lain. | TETAP |  | Pesan galat |
| 714 | Pesan dari kelas PHP | `app/Exceptions/TiketSudahDitugaskan.php:13` | pesan galat | Tiket ini sudah ditugaskan. Daftar sudah diperbarui. | TETAP |  | Pesan galat |
| 715 | Pesan dari kelas PHP | `app/Exceptions/VendorTidakAktif.php:12` | pesan galat | Akun vendor ini nonaktif. Pilih vendor lain. | TETAP |  | Pesan galat |
| 716 | Pesan dari kelas PHP | `app/Exceptions/KonfigurasiTagihanTidakValid.php:16` | pesan galat | TAGIHAN_PARSIAL "{nilai}" tidak dikenal. Isi dengan "penuh" atau "prorata" di .env. | TETAP |  | Pesan galat |
| 717 | Pesan dari kelas PHP | `app/Models/Kontrak.php:197` | pesan galat | Kamar {n} sudah terisi. [PRD F-07] | TETAP |  | Teks dikunci PRD |
| 718 | Pesan dari kelas PHP | `app/Models/Kontrak.php:226` | pesan galat | Kamar {n} sudah terisi. [PRD F-07] (permintaan bersamaan) | TETAP |  | Teks dikunci PRD |
| 719 | Pesan dari kelas PHP | `app/Models/Kontrak.php:201, 228` | pesan galat | Penghuni ini sudah punya kontrak aktif. | TETAP |  | Pesan galat |
| 720 | Pesan dari kelas PHP | `app/Policies/KamarPolicy.php:64` | pesan galat | Kamar {n} masih punya kontrak aktif atas nama {nama}, jadi belum bisa dihapus. | TETAP |  | Pesan galat |
| 721 | Pesan dari kelas PHP | `app/Policies/KamarPolicy.php:70` | pesan galat | Kamar {n} punya riwayat kontrak, jadi tidak bisa dihapus agar riwayatnya tetap utuh. | TETAP |  | Pesan galat |
| 722 | Pesan dari kelas PHP | `app/Policies/KamarPolicy.php:76` | pesan galat | Kamar {n} masih punya data fasilitas, jadi belum bisa dihapus. | TETAP |  | Pesan galat |
| 723 | Pesan dari kelas PHP | `app/Policies/PenghuniPolicy.php:63` | pesan galat | {nama} masih punya kontrak aktif di Kamar {n}. Akhiri kontraknya dulu sebelum menandai keluar. | TETAP |  | Pesan galat |
| 724 | Pesan dari kelas PHP | `app/Http/Middleware/PastikanPeran.php:15` | pesan galat | Anda tidak memiliki akses ke halaman ini. [PRD F-03] | TETAP |  | Teks dikunci PRD |
| 725 | Lokasi fasilitas (sumber di Sinkronisasi) | `app/Models/Fasilitas.php:61` | meta/keterangan kecil | Area Bersama / Kamar {n} | TETAP |  | Data |
| 726 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:31` | pesan galat | Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 727 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:33` | pesan galat | API key Odoo ditolak. Periksa ODOO_API_KEY dan masa berlakunya. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 728 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:35` | pesan galat | Tiket belum berstatus selesai. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 729 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:37` | pesan galat | Tiket tidak punya vendor. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 730 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:39` | pesan galat | Fasilitas belum ditautkan ke peralatan Odoo. Jalankan php artisan erp:cocokkan-peralatan. | PERSINGKAT | Fasilitas belum ditautkan ke peralatan Odoo. | Perintah teknis dibuang, keputusan tim |
| 731 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:41` | pesan galat | Tahap perawatan selesai tidak ditemukan di Odoo. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 732 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:141` | pesan galat | Pembayaran tidak berstatus diterima. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 733 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:148` | pesan galat | Nominal pembayaran berbeda dengan nominal tagihan. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 734 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:189` | pesan galat | Faktur {ref} baru dibayar sebagian di Odoo. Periksa manual. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 735 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:199` | pesan galat | Faktur {ref} sudah dibuat tetapi belum lunas di Odoo (status {status}). | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 736 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:266` | pesan galat | Tagihan vendor {ref} sudah dibuat tetapi belum posted di Odoo. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 737 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:311` | pesan galat | Permintaan perawatan {ref} sudah dibuat tetapi belum di tahap selesai di Odoo. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 738 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:371` | pesan galat | Ada lebih dari satu partner {ref} di Odoo. Periksa manual. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 739 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:428` | pesan galat | Ada lebih dari satu {jenis} {ref} di Odoo. Periksa manual. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 740 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:434` | pesan galat | {jenis} {ref} dibatalkan di Odoo. Periksa manual. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 741 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:470` | pesan galat | {rincian tidak cocok} Kemungkinan sisa data uji sebelum reset basis data. Periksa manual. | PERSINGKAT | {rincian tidak cocok} Periksa manual di Odoo. | Kalimat tentang sisa data uji dibuang, keputusan tim |
| 742 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:558` | pesan galat | Jurnal bank tidak ditemukan di Odoo. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 743 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:581` | pesan galat | Ada lebih dari satu permintaan perawatan {ref} di Odoo. Periksa manual. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 744 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:678` | pesan galat | Odoo menolak: {pesan Odoo} | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 745 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/OdooClient.php:35` | pesan galat | Konfigurasi Odoo belum lengkap. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 746 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/OdooClient.php:60` | pesan galat | Odoo tidak dapat dihubungi: {rincian} | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 747 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/OdooClient.php:160` | pesan galat | Tidak ada pesan galat dari Odoo. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 748 | Sinkronisasi (pesan galat tersimpan) | `app/Jobs/KirimSinkronisasiErp.php:30` | pesan galat | Kesalahan tak terduga saat mengirim ke Odoo. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |
| 749 | Paginasi | `lang/id.json:2` | meta/keterangan kecil | Menampilkan | TETAP |  | Data |
| 750 | Paginasi | `lang/id.json:3` | meta/keterangan kecil | – | TETAP |  | Data |
| 751 | Paginasi | `lang/id.json:4` | meta/keterangan kecil | dari | TETAP |  | Data |
| 752 | Paginasi | `lang/id.json:5` | meta/keterangan kecil | data | TETAP |  | Data |
| 753 | Paginasi | `lang/id.json:6` | sr-only/aria-label | Ke halaman {n} | TETAP |  | Teks pembaca layar |
| 754 | Paginasi | `lang/id/pagination.php:11` | tombol | Sebelumnya | TETAP |  | Tombol |
| 755 | Paginasi | `lang/id/pagination.php:12` | tombol | Berikutnya | TETAP |  | Tombol |
| 756 | lang/id/passwords.php | `lang/id/passwords.php:15` | toast | Password Anda telah diatur ulang. | TETAP |  | Pesan bawaan framework; reset password mandiri dimatikan |
| 757 | lang/id/passwords.php | `lang/id/passwords.php:16` | toast | Tautan pengaturan ulang password telah dikirim. | TETAP |  | Pesan bawaan framework; reset password mandiri dimatikan |
| 758 | lang/id/passwords.php | `lang/id/passwords.php:17` | pesan galat | Mohon tunggu sebelum mencoba lagi. | TETAP |  | Pesan bawaan framework; reset password mandiri dimatikan |
| 759 | lang/id/passwords.php | `lang/id/passwords.php:18` | pesan galat | Token pengaturan ulang password tidak valid. | TETAP |  | Pesan bawaan framework; reset password mandiri dimatikan |
| 760 | lang/id/passwords.php | `lang/id/passwords.php:19` | pesan galat | Pengguna dengan email tersebut tidak ditemukan. | TETAP |  | Pesan bawaan framework; reset password mandiri dimatikan |
| 761 | Validasi: accepted | `lang/id/validation.php:15` | pesan galat | :Attribute harus diterima. | TETAP |  | Pesan galat |
| 762 | Validasi: accepted_if | `lang/id/validation.php:16` | pesan galat | :Attribute harus diterima bila :other bernilai :value. | TETAP |  | Pesan galat |
| 763 | Validasi: active_url | `lang/id/validation.php:17` | pesan galat | :Attribute harus berupa URL yang valid. | TETAP |  | Pesan galat |
| 764 | Validasi: after | `lang/id/validation.php:18` | pesan galat | :Attribute harus berupa tanggal setelah :date. | TETAP |  | Pesan galat |
| 765 | Validasi: after_or_equal | `lang/id/validation.php:19` | pesan galat | :Attribute harus berupa tanggal setelah atau sama dengan :date. | TETAP |  | Pesan galat |
| 766 | Validasi: alpha | `lang/id/validation.php:20` | pesan galat | :Attribute hanya boleh berisi huruf. | TETAP |  | Pesan galat |
| 767 | Validasi: alpha_dash | `lang/id/validation.php:21` | pesan galat | :Attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah. | TETAP |  | Pesan galat |
| 768 | Validasi: alpha_num | `lang/id/validation.php:22` | pesan galat | :Attribute hanya boleh berisi huruf dan angka. | TETAP |  | Pesan galat |
| 769 | Validasi: any_of | `lang/id/validation.php:23` | pesan galat | :Attribute tidak valid. | TETAP |  | Pesan galat |
| 770 | Validasi: array | `lang/id/validation.php:24` | pesan galat | :Attribute harus berupa larik. | TETAP |  | Pesan galat |
| 771 | Validasi: array_keys | `lang/id/validation.php:25` | pesan galat | :Attribute hanya boleh berisi kunci berikut: :values. | TETAP |  | Pesan galat |
| 772 | Validasi: ascii | `lang/id/validation.php:26` | pesan galat | :Attribute hanya boleh berisi karakter alfanumerik dan simbol satu byte. | TETAP |  | Pesan galat |
| 773 | Validasi: base64 | `lang/id/validation.php:27` | pesan galat | :Attribute harus berupa string Base64 yang valid. | TETAP |  | Pesan galat |
| 774 | Validasi: before | `lang/id/validation.php:28` | pesan galat | :Attribute harus berupa tanggal sebelum :date. | TETAP |  | Pesan galat |
| 775 | Validasi: before_or_equal | `lang/id/validation.php:29` | pesan galat | :Attribute harus berupa tanggal sebelum atau sama dengan :date. | TETAP |  | Pesan galat |
| 776 | Validasi: between.array | `lang/id/validation.php:31` | pesan galat | :Attribute harus memiliki antara :min dan :max item. | TETAP |  | Pesan galat |
| 777 | Validasi: between.file | `lang/id/validation.php:32` | pesan galat | :Attribute harus berukuran antara :min dan :max kilobita. | TETAP |  | Pesan galat |
| 778 | Validasi: between.numeric | `lang/id/validation.php:33` | pesan galat | :Attribute harus bernilai antara :min dan :max. | TETAP |  | Pesan galat |
| 779 | Validasi: between.string | `lang/id/validation.php:34` | pesan galat | :Attribute harus terdiri dari :min sampai :max karakter. | TETAP |  | Pesan galat |
| 780 | Validasi: boolean | `lang/id/validation.php:36` | pesan galat | :Attribute harus bernilai benar atau salah. | TETAP |  | Pesan galat |
| 781 | Validasi: can | `lang/id/validation.php:37` | pesan galat | :Attribute berisi nilai yang tidak diizinkan. | TETAP |  | Pesan galat |
| 782 | Validasi: confirmed | `lang/id/validation.php:38` | pesan galat | Konfirmasi :attribute tidak sesuai. | TETAP |  | Pesan galat |
| 783 | Validasi: contains | `lang/id/validation.php:39` | pesan galat | :Attribute tidak memuat nilai yang diperlukan. | TETAP |  | Pesan galat |
| 784 | Validasi: current_password | `lang/id/validation.php:40` | pesan galat | Password lama salah. | TETAP |  | Pesan galat |
| 785 | Validasi: date | `lang/id/validation.php:41` | pesan galat | :Attribute harus berupa tanggal yang valid. | TETAP |  | Pesan galat |
| 786 | Validasi: date_equals | `lang/id/validation.php:42` | pesan galat | :Attribute harus berupa tanggal yang sama dengan :date. | TETAP |  | Pesan galat |
| 787 | Validasi: date_format | `lang/id/validation.php:43` | pesan galat | :Attribute harus sesuai format :format. | TETAP |  | Pesan galat |
| 788 | Validasi: decimal | `lang/id/validation.php:44` | pesan galat | :Attribute harus memiliki :decimal angka desimal. | TETAP |  | Pesan galat |
| 789 | Validasi: declined | `lang/id/validation.php:45` | pesan galat | :Attribute harus ditolak. | TETAP |  | Pesan galat |
| 790 | Validasi: declined_if | `lang/id/validation.php:46` | pesan galat | :Attribute harus ditolak bila :other bernilai :value. | TETAP |  | Pesan galat |
| 791 | Validasi: different | `lang/id/validation.php:47` | pesan galat | :Attribute dan :other harus berbeda. | TETAP |  | Pesan galat |
| 792 | Validasi: digits | `lang/id/validation.php:48` | pesan galat | :Attribute harus terdiri dari :digits digit. | TETAP |  | Pesan galat |
| 793 | Validasi: digits_between | `lang/id/validation.php:49` | pesan galat | :Attribute harus terdiri dari :min sampai :max digit. | TETAP |  | Pesan galat |
| 794 | Validasi: dimensions | `lang/id/validation.php:50` | pesan galat | :Attribute memiliki dimensi gambar yang tidak valid. | TETAP |  | Pesan galat |
| 795 | Validasi: distinct | `lang/id/validation.php:51` | pesan galat | :Attribute memiliki nilai ganda. | TETAP |  | Pesan galat |
| 796 | Validasi: doesnt_contain | `lang/id/validation.php:52` | pesan galat | :Attribute tidak boleh memuat salah satu dari: :values. | TETAP |  | Pesan galat |
| 797 | Validasi: doesnt_end_with | `lang/id/validation.php:53` | pesan galat | :Attribute tidak boleh diakhiri salah satu dari: :values. | TETAP |  | Pesan galat |
| 798 | Validasi: doesnt_start_with | `lang/id/validation.php:54` | pesan galat | :Attribute tidak boleh diawali salah satu dari: :values. | TETAP |  | Pesan galat |
| 799 | Validasi: email | `lang/id/validation.php:55` | pesan galat | :Attribute harus berupa alamat email yang valid. | TETAP |  | Pesan galat |
| 800 | Validasi: encoding | `lang/id/validation.php:56` | pesan galat | :Attribute harus berenkode :encoding. | TETAP |  | Pesan galat |
| 801 | Validasi: ends_with | `lang/id/validation.php:57` | pesan galat | :Attribute harus diakhiri salah satu dari: :values. | TETAP |  | Pesan galat |
| 802 | Validasi: enum | `lang/id/validation.php:58` | pesan galat | :Attribute yang dipilih tidak valid. | TETAP |  | Pesan galat |
| 803 | Validasi: exists | `lang/id/validation.php:59` | pesan galat | :Attribute yang dipilih tidak valid. | TETAP |  | Pesan galat |
| 804 | Validasi: extensions | `lang/id/validation.php:60` | pesan galat | :Attribute harus memiliki salah satu ekstensi berikut: :values. | TETAP |  | Pesan galat |
| 805 | Validasi: file | `lang/id/validation.php:61` | pesan galat | :Attribute harus berupa berkas. | TETAP |  | Pesan galat |
| 806 | Validasi: filled | `lang/id/validation.php:62` | pesan galat | :Attribute harus memiliki nilai. | TETAP |  | Pesan galat |
| 807 | Validasi: gt.array | `lang/id/validation.php:64` | pesan galat | :Attribute harus memiliki lebih dari :value item. | TETAP |  | Pesan galat |
| 808 | Validasi: gt.file | `lang/id/validation.php:65` | pesan galat | :Attribute harus berukuran lebih dari :value kilobita. | TETAP |  | Pesan galat |
| 809 | Validasi: gt.numeric | `lang/id/validation.php:66` | pesan galat | :Attribute harus bernilai lebih dari :value. | TETAP |  | Pesan galat |
| 810 | Validasi: gt.string | `lang/id/validation.php:67` | pesan galat | :Attribute harus terdiri dari lebih dari :value karakter. | TETAP |  | Pesan galat |
| 811 | Validasi: gte.array | `lang/id/validation.php:70` | pesan galat | :Attribute harus memiliki :value item atau lebih. | TETAP |  | Pesan galat |
| 812 | Validasi: gte.file | `lang/id/validation.php:71` | pesan galat | :Attribute harus berukuran minimal :value kilobita. | TETAP |  | Pesan galat |
| 813 | Validasi: gte.numeric | `lang/id/validation.php:72` | pesan galat | :Attribute harus bernilai minimal :value. | TETAP |  | Pesan galat |
| 814 | Validasi: gte.string | `lang/id/validation.php:73` | pesan galat | :Attribute harus terdiri dari minimal :value karakter. | TETAP |  | Pesan galat |
| 815 | Validasi: hex_color | `lang/id/validation.php:75` | pesan galat | :Attribute harus berupa warna heksadesimal yang valid. | TETAP |  | Pesan galat |
| 816 | Validasi: image | `lang/id/validation.php:76` | pesan galat | :Attribute harus berupa gambar. | TETAP |  | Pesan galat |
| 817 | Validasi: in | `lang/id/validation.php:77` | pesan galat | :Attribute yang dipilih tidak valid. | TETAP |  | Pesan galat |
| 818 | Validasi: in_array | `lang/id/validation.php:78` | pesan galat | :Attribute harus ada di dalam :other. | TETAP |  | Pesan galat |
| 819 | Validasi: in_array_keys | `lang/id/validation.php:79` | pesan galat | :Attribute harus memuat sedikitnya salah satu kunci berikut: :values. | TETAP |  | Pesan galat |
| 820 | Validasi: integer | `lang/id/validation.php:80` | pesan galat | :Attribute harus berupa bilangan bulat. | TETAP |  | Pesan galat |
| 821 | Validasi: ip | `lang/id/validation.php:81` | pesan galat | :Attribute harus berupa alamat IP yang valid. | TETAP |  | Pesan galat |
| 822 | Validasi: ipv4 | `lang/id/validation.php:82` | pesan galat | :Attribute harus berupa alamat IPv4 yang valid. | TETAP |  | Pesan galat |
| 823 | Validasi: ipv6 | `lang/id/validation.php:83` | pesan galat | :Attribute harus berupa alamat IPv6 yang valid. | TETAP |  | Pesan galat |
| 824 | Validasi: json | `lang/id/validation.php:84` | pesan galat | :Attribute harus berupa string JSON yang valid. | TETAP |  | Pesan galat |
| 825 | Validasi: list | `lang/id/validation.php:85` | pesan galat | :Attribute harus berupa daftar. | TETAP |  | Pesan galat |
| 826 | Validasi: lowercase | `lang/id/validation.php:86` | pesan galat | :Attribute harus berupa huruf kecil. | TETAP |  | Pesan galat |
| 827 | Validasi: lt.array | `lang/id/validation.php:88` | pesan galat | :Attribute harus memiliki kurang dari :value item. | TETAP |  | Pesan galat |
| 828 | Validasi: lt.file | `lang/id/validation.php:89` | pesan galat | :Attribute harus berukuran kurang dari :value kilobita. | TETAP |  | Pesan galat |
| 829 | Validasi: lt.numeric | `lang/id/validation.php:90` | pesan galat | :Attribute harus bernilai kurang dari :value. | TETAP |  | Pesan galat |
| 830 | Validasi: lt.string | `lang/id/validation.php:91` | pesan galat | :Attribute harus terdiri dari kurang dari :value karakter. | TETAP |  | Pesan galat |
| 831 | Validasi: lte.array | `lang/id/validation.php:94` | pesan galat | :Attribute tidak boleh memiliki lebih dari :value item. | TETAP |  | Pesan galat |
| 832 | Validasi: lte.file | `lang/id/validation.php:95` | pesan galat | :Attribute harus berukuran maksimal :value kilobita. | TETAP |  | Pesan galat |
| 833 | Validasi: lte.numeric | `lang/id/validation.php:96` | pesan galat | :Attribute harus bernilai maksimal :value. | TETAP |  | Pesan galat |
| 834 | Validasi: lte.string | `lang/id/validation.php:97` | pesan galat | :Attribute harus terdiri dari maksimal :value karakter. | TETAP |  | Pesan galat |
| 835 | Validasi: mac_address | `lang/id/validation.php:99` | pesan galat | :Attribute harus berupa alamat MAC yang valid. | TETAP |  | Pesan galat |
| 836 | Validasi: max.array | `lang/id/validation.php:101` | pesan galat | :Attribute tidak boleh memiliki lebih dari :max item. | TETAP |  | Pesan galat |
| 837 | Validasi: max.file | `lang/id/validation.php:102` | pesan galat | :Attribute tidak boleh berukuran lebih dari :max kilobita. | TETAP |  | Pesan galat |
| 838 | Validasi: max.numeric | `lang/id/validation.php:103` | pesan galat | :Attribute tidak boleh lebih dari :max. | TETAP |  | Pesan galat |
| 839 | Validasi: max.string | `lang/id/validation.php:104` | pesan galat | :Attribute tidak boleh lebih dari :max karakter. | TETAP |  | Pesan galat |
| 840 | Validasi: max_digits | `lang/id/validation.php:106` | pesan galat | :Attribute tidak boleh lebih dari :max digit. | TETAP |  | Pesan galat |
| 841 | Validasi: mimes | `lang/id/validation.php:107` | pesan galat | :Attribute harus berupa berkas bertipe: :values. | TETAP |  | Pesan galat |
| 842 | Validasi: mimetypes | `lang/id/validation.php:108` | pesan galat | :Attribute harus berupa berkas bertipe: :values. | TETAP |  | Pesan galat |
| 843 | Validasi: min.array | `lang/id/validation.php:110` | pesan galat | :Attribute harus memiliki sedikitnya :min item. | TETAP |  | Pesan galat |
| 844 | Validasi: min.file | `lang/id/validation.php:111` | pesan galat | :Attribute harus berukuran minimal :min kilobita. | TETAP |  | Pesan galat |
| 845 | Validasi: min.numeric | `lang/id/validation.php:112` | pesan galat | :Attribute harus bernilai minimal :min. | TETAP |  | Pesan galat |
| 846 | Validasi: min.string | `lang/id/validation.php:113` | pesan galat | :Attribute harus terdiri dari minimal :min karakter. | TETAP |  | Pesan galat |
| 847 | Validasi: min_digits | `lang/id/validation.php:115` | pesan galat | :Attribute harus terdiri dari minimal :min digit. | TETAP |  | Pesan galat |
| 848 | Validasi: missing | `lang/id/validation.php:116` | pesan galat | :Attribute harus tidak ada. | TETAP |  | Pesan galat |
| 849 | Validasi: missing_if | `lang/id/validation.php:117` | pesan galat | :Attribute harus tidak ada bila :other bernilai :value. | TETAP |  | Pesan galat |
| 850 | Validasi: missing_unless | `lang/id/validation.php:118` | pesan galat | :Attribute harus tidak ada kecuali :other bernilai :value. | TETAP |  | Pesan galat |
| 851 | Validasi: missing_with | `lang/id/validation.php:119` | pesan galat | :Attribute harus tidak ada bila :values ada. | TETAP |  | Pesan galat |
| 852 | Validasi: missing_with_all | `lang/id/validation.php:120` | pesan galat | :Attribute harus tidak ada bila :values ada. | TETAP |  | Pesan galat |
| 853 | Validasi: multiple_of | `lang/id/validation.php:121` | pesan galat | :Attribute harus kelipatan :value. | TETAP |  | Pesan galat |
| 854 | Validasi: not_in | `lang/id/validation.php:122` | pesan galat | :Attribute yang dipilih tidak valid. | TETAP |  | Pesan galat |
| 855 | Validasi: not_regex | `lang/id/validation.php:123` | pesan galat | Format :attribute tidak valid. | TETAP |  | Pesan galat |
| 856 | Validasi: numeric | `lang/id/validation.php:124` | pesan galat | :Attribute harus berupa angka. | TETAP |  | Pesan galat |
| 857 | Validasi: password.letters | `lang/id/validation.php:126` | pesan galat | :Attribute harus mengandung sedikitnya satu huruf. | TETAP |  | Pesan galat |
| 858 | Validasi: password.mixed | `lang/id/validation.php:127` | pesan galat | :Attribute harus mengandung sedikitnya satu huruf besar dan satu huruf kecil. | TETAP |  | Pesan galat |
| 859 | Validasi: password.numbers | `lang/id/validation.php:128` | pesan galat | :Attribute harus mengandung sedikitnya satu angka. | TETAP |  | Pesan galat |
| 860 | Validasi: password.symbols | `lang/id/validation.php:129` | pesan galat | :Attribute harus mengandung sedikitnya satu simbol. | TETAP |  | Pesan galat |
| 861 | Validasi: password.uncompromised | `lang/id/validation.php:130` | pesan galat | :Attribute yang diberikan pernah bocor dalam kebocoran data. Pilih :attribute lain. | TETAP |  | Pesan galat |
| 862 | Validasi: present | `lang/id/validation.php:132` | pesan galat | :Attribute harus ada. | TETAP |  | Pesan galat |
| 863 | Validasi: present_if | `lang/id/validation.php:133` | pesan galat | :Attribute harus ada bila :other bernilai :value. | TETAP |  | Pesan galat |
| 864 | Validasi: present_unless | `lang/id/validation.php:134` | pesan galat | :Attribute harus ada kecuali :other bernilai :value. | TETAP |  | Pesan galat |
| 865 | Validasi: present_with | `lang/id/validation.php:135` | pesan galat | :Attribute harus ada bila :values ada. | TETAP |  | Pesan galat |
| 866 | Validasi: present_with_all | `lang/id/validation.php:136` | pesan galat | :Attribute harus ada bila :values ada. | TETAP |  | Pesan galat |
| 867 | Validasi: prohibited | `lang/id/validation.php:137` | pesan galat | :Attribute tidak diperbolehkan. | TETAP |  | Pesan galat |
| 868 | Validasi: prohibited_if | `lang/id/validation.php:138` | pesan galat | :Attribute tidak diperbolehkan bila :other bernilai :value. | TETAP |  | Pesan galat |
| 869 | Validasi: prohibited_if_accepted | `lang/id/validation.php:139` | pesan galat | :Attribute tidak diperbolehkan bila :other diterima. | TETAP |  | Pesan galat |
| 870 | Validasi: prohibited_if_declined | `lang/id/validation.php:140` | pesan galat | :Attribute tidak diperbolehkan bila :other ditolak. | TETAP |  | Pesan galat |
| 871 | Validasi: prohibited_unless | `lang/id/validation.php:141` | pesan galat | :Attribute tidak diperbolehkan kecuali :other termasuk dalam :values. | TETAP |  | Pesan galat |
| 872 | Validasi: prohibits | `lang/id/validation.php:142` | pesan galat | :Attribute melarang :other untuk ada. | TETAP |  | Pesan galat |
| 873 | Validasi: regex | `lang/id/validation.php:143` | pesan galat | Format :attribute tidak valid. | TETAP |  | Pesan galat |
| 874 | Validasi: required | `lang/id/validation.php:144` | pesan galat | :Attribute wajib diisi. | TETAP |  | Pesan galat |
| 875 | Validasi: required_array_keys | `lang/id/validation.php:145` | pesan galat | :Attribute harus memuat entri untuk: :values. | TETAP |  | Pesan galat |
| 876 | Validasi: required_if | `lang/id/validation.php:146` | pesan galat | :Attribute wajib diisi bila :other bernilai :value. | TETAP |  | Pesan galat |
| 877 | Validasi: required_if_accepted | `lang/id/validation.php:147` | pesan galat | :Attribute wajib diisi bila :other diterima. | TETAP |  | Pesan galat |
| 878 | Validasi: required_if_declined | `lang/id/validation.php:148` | pesan galat | :Attribute wajib diisi bila :other ditolak. | TETAP |  | Pesan galat |
| 879 | Validasi: required_unless | `lang/id/validation.php:149` | pesan galat | :Attribute wajib diisi kecuali :other termasuk dalam :values. | TETAP |  | Pesan galat |
| 880 | Validasi: required_with | `lang/id/validation.php:150` | pesan galat | :Attribute wajib diisi bila :values ada. | TETAP |  | Pesan galat |
| 881 | Validasi: required_with_all | `lang/id/validation.php:151` | pesan galat | :Attribute wajib diisi bila :values ada. | TETAP |  | Pesan galat |
| 882 | Validasi: required_without | `lang/id/validation.php:152` | pesan galat | :Attribute wajib diisi bila :values tidak ada. | TETAP |  | Pesan galat |
| 883 | Validasi: required_without_all | `lang/id/validation.php:153` | pesan galat | :Attribute wajib diisi bila tidak ada satu pun dari :values. | TETAP |  | Pesan galat |
| 884 | Validasi: same | `lang/id/validation.php:154` | pesan galat | :Attribute harus sama dengan :other. | TETAP |  | Pesan galat |
| 885 | Validasi: size.array | `lang/id/validation.php:156` | pesan galat | :Attribute harus memuat :size item. | TETAP |  | Pesan galat |
| 886 | Validasi: size.file | `lang/id/validation.php:157` | pesan galat | :Attribute harus berukuran :size kilobita. | TETAP |  | Pesan galat |
| 887 | Validasi: size.numeric | `lang/id/validation.php:158` | pesan galat | :Attribute harus bernilai :size. | TETAP |  | Pesan galat |
| 888 | Validasi: size.string | `lang/id/validation.php:159` | pesan galat | :Attribute harus terdiri dari :size karakter. | TETAP |  | Pesan galat |
| 889 | Validasi: starts_with | `lang/id/validation.php:161` | pesan galat | :Attribute harus diawali salah satu dari: :values. | TETAP |  | Pesan galat |
| 890 | Validasi: string | `lang/id/validation.php:162` | pesan galat | :Attribute harus berupa teks. | TETAP |  | Pesan galat |
| 891 | Validasi: timezone | `lang/id/validation.php:163` | pesan galat | :Attribute harus berupa zona waktu yang valid. | TETAP |  | Pesan galat |
| 892 | Validasi: unique | `lang/id/validation.php:164` | pesan galat | :Attribute sudah terdaftar. | TETAP |  | Pesan galat |
| 893 | Validasi: uploaded | `lang/id/validation.php:165` | pesan galat | :Attribute gagal diunggah. | TETAP |  | Pesan galat |
| 894 | Validasi: uppercase | `lang/id/validation.php:166` | pesan galat | :Attribute harus berupa huruf kapital. | TETAP |  | Pesan galat |
| 895 | Validasi: url | `lang/id/validation.php:167` | pesan galat | :Attribute harus berupa URL yang valid. | TETAP |  | Pesan galat |
| 896 | Validasi: ulid | `lang/id/validation.php:168` | pesan galat | :Attribute harus berupa ULID yang valid. | TETAP |  | Pesan galat |
| 897 | Validasi: uuid | `lang/id/validation.php:169` | pesan galat | :Attribute harus berupa UUID yang valid. | TETAP |  | Pesan galat |
| 898 | Validasi: nama isian | `lang/id/validation.php:190` | pesan galat | nama (nama) | TETAP |  | Nama isian di pesan galat |
| 899 | Validasi: nama isian | `lang/id/validation.php:191` | pesan galat | email (email) | TETAP |  | Nama isian di pesan galat |
| 900 | Validasi: nama isian | `lang/id/validation.php:192` | pesan galat | password (password) | TETAP |  | Nama isian di pesan galat |
| 901 | Validasi: nama isian | `lang/id/validation.php:193` | pesan galat | password lama (current_password) | TETAP |  | Nama isian di pesan galat |
| 902 | Validasi: nama isian | `lang/id/validation.php:194` | pesan galat | peran (peran) | TETAP |  | Nama isian di pesan galat |
| 903 | Validasi: nama isian | `lang/id/validation.php:195` | pesan galat | nama vendor (nama_vendor) | TETAP |  | Nama isian di pesan galat |
| 904 | Validasi: nama isian | `lang/id/validation.php:196` | pesan galat | jenis layanan (jenis_layanan) | TETAP |  | Nama isian di pesan galat |
| 905 | Validasi: nama isian | `lang/id/validation.php:197` | pesan galat | nomor telepon (no_telepon) | TETAP |  | Nama isian di pesan galat |
| 906 | Validasi: nama isian | `lang/id/validation.php:198` | pesan galat | nomor identitas (no_identitas) | TETAP |  | Nama isian di pesan galat |
| 907 | Validasi: nama isian | `lang/id/validation.php:199` | pesan galat | nomor identitas (nik) | TETAP |  | Nama isian di pesan galat |
| 908 | Validasi: nama isian | `lang/id/validation.php:200` | pesan galat | akun penghuni (user_id) | TETAP |  | Nama isian di pesan galat |
| 909 | Validasi: nama isian | `lang/id/validation.php:201` | pesan galat | penghuni (penghuni_id) | TETAP |  | Nama isian di pesan galat |
| 910 | Validasi: nama isian | `lang/id/validation.php:202` | pesan galat | kamar (kamar_id) | TETAP |  | Nama isian di pesan galat |
| 911 | Validasi: nama isian | `lang/id/validation.php:203` | pesan galat | tanggal mulai (tanggal_mulai) | TETAP |  | Nama isian di pesan galat |
| 912 | Validasi: nama isian | `lang/id/validation.php:204` | pesan galat | tanggal berakhir (tanggal_berakhir) | TETAP |  | Nama isian di pesan galat |
| 913 | Validasi: nama isian | `lang/id/validation.php:205` | pesan galat | tarif per bulan (tarif_bulanan) | TETAP |  | Nama isian di pesan galat |
| 914 | Validasi: nama isian | `lang/id/validation.php:206` | pesan galat | berkas bukti (bukti) | TETAP |  | Nama isian di pesan galat |
| 915 | Validasi: nama isian | `lang/id/validation.php:207` | pesan galat | tanggal transfer (tanggal_bayar) | TETAP |  | Nama isian di pesan galat |
| 916 | Sinkronisasi (pesan galat tersimpan) | `app/Services/Odoo/IntegrasiErpService.php:607` | pesan galat | Peralatan Odoo #{id} untuk fasilitas ini tidak ditemukan. Jalankan php artisan erp:cocokkan-peralatan. | TETAP |  | Pesan galat (tampil di Sinkronisasi) |

## Teks Baru dari Prompt 20D

| No | Halaman | Berkas | Jenis | Teks |
| ---: | --- | --- | --- | --- |
| 1 | Tagihan (Pengelola, Pemilik) | `resources/views/components/pemilih-periode.blade.php` | sr-only/aria-label | Periode |
| 2 | Tagihan (Pengelola, Pemilik) | `resources/views/components/pemilih-periode.blade.php` | sr-only/aria-label | Bulan sebelumnya |
| 3 | Tagihan (Pengelola, Pemilik) | `resources/views/components/pemilih-periode.blade.php` | sr-only/aria-label | Bulan berikutnya |
| 4 | Tagihan (Pengelola, Pemilik) | `resources/views/components/pemilih-periode.blade.php` | sr-only/aria-label | Pilih bulan, {bulan tahun} |
| 5 | Tagihan (Pengelola, Pemilik) | `resources/views/components/pemilih-periode.blade.php` | tombol | {bulan tahun} (label tengah dan daftar bulan) |
| 6 | Verifikasi | `resources/views/pages/verifikasi-pembayaran/⚡index.blade.php` | sr-only/aria-label | Tutup detail |
| 7 | Keluhan (Pengelola, Pemilik) | `resources/views/pages/tiket-keluhan/⚡index.blade.php` | sr-only/aria-label | Tutup detail |
| 8 | Pengaturan | `resources/views/pages/pengaturan/⚡index.blade.php` | label isian | hari (akhiran di dalam isian Batas Peringatan) |
