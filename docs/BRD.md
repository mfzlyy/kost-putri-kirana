# **Business Requirements Document (BRD)**

Web Pengelolaan Kos Terintegrasi ERP — Kost Putri Kirana

Muhammad Fazly Radithya

Nadia Miranda

Kirana Amelia Maharani

Versi 1.2 · 29 Oktober 2026

## **Kontrol Dokumen**

<table><thead><tr><th><p>Versi</p></th><th><p>Tanggal</p></th><th><p>Penulis</p></th><th><p>Ringkasan perubahan</p></th></tr></thead><tbody><tr><td><p>1.2</p></td><td><p>29 Oktober 2026</p></td><td><p>Tim Nasi Goreng Adul Enak</p></td><td><p>Penyelarasan dengan proposal dan PRD: aturan tunggakan (ATB-05), penerbitan tagihan (BR-06), platform ERP Odoo Community 19 dengan JSON-2 API, register isu bertanggal, nilai awal tunggakan, pemetaan modul Odoo, dan activity diagram final</p></td></tr><tr><td><p>1.1</p></td><td><p>23 Oktober 2026</p></td><td><p>Tim Nasi Goreng Adul Enak</p></td><td><p>Penambahan register asumsi, dependensi, dan isu terbuka; penambahan dokumen acuan; penyelarasan istilah dengan PRD</p></td></tr><tr><td><p>1.0</p></td><td><p>23 Oktober 2026</p></td><td><p>Tim Nasi Goreng Adul Enak</p></td><td><p>Draf awal</p></td></tr></tbody></table>

**Persetujuan**

<table><thead><tr><th><p>Nama</p></th><th><p>Peran</p></th><th><p>Keputusan</p></th><th><p>Tanggal</p></th></tr></thead><tbody><tr><td><p>Muhammad Fazly Radithya</p></td><td><p>Anggota Tim</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Nadia Miranda</p></td><td><p>Anggota Tim</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Kirana Amelia Maharani</p></td><td><p>Anggota Tim</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Pemilik Kost Putri Kirana</p></td><td><p>Problem owner</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Dosen Pembimbing</p></td><td><p>Pembimbing Capstone</p></td><td><p>Menunggu</p></td><td></td></tr></tbody></table>

## **1. Ringkasan Eksekutif**

Proyek ini membangun web pengelolaan kos untuk Kost Putri Kirana yang terintegrasi dengan ERP. Penghuni melihat tagihan, mengunggah bukti bayar, dan melapor keluhan. Pengelola memverifikasi pembayaran dan menugaskan vendor. Vendor memperbarui pekerjaan dan biaya perbaikan, sedangkan pemilik memantau hunian dan tunggakan. Transaksi keuangan diteruskan ke ERP melalui JSON-2 API Odoo.

<table><thead><tr><th><p>Aspek</p></th><th><p>Ringkasan</p></th></tr></thead><tbody><tr><td><p>Jenis produk</p></td><td><p>Web pengelolaan kos sebagai sistem inti, dengan ERP sebagai back-office (Alternatif B)</p></td></tr><tr><td><p>Objek</p></td><td><p>Kost Putri Kirana, Cikarang Utara, Kabupaten Bekasi (8 kamar, 8 penghuni)</p></td></tr><tr><td><p>Peran pengguna</p></td><td><p>Pemilik, Pengelola, Penghuni, dan Vendor</p></td></tr><tr><td><p>Modul web</p></td><td><p>Penghuni dan kontrak, pembayaran, keluhan fasilitas, serta layanan vendor dan pemilik</p></td></tr><tr><td><p>Modul ERP</p></td><td><p>Keuangan dan Akuntansi; Pengadaan dan Hutang Vendor; Aset dan Pemeliharaan (pemetaan ke aplikasi Odoo pada Bagian 8)</p></td></tr><tr><td><p>Integrasi</p></td><td><p>API HTTP berformat JSON (JSON-2 API Odoo) dengan API key sebagai bearer token; status transaksi dikembalikan ke web</p></td></tr><tr><td><p>Metode</p></td><td><p>Agile Scrum yang disesuaikan dalam SDLC: pra-sprint dan 4 sprint dua mingguan (minggu 5–14)</p></td></tr><tr><td><p>Teknologi</p></td><td><p>ERP: Odoo Community 19, dipasang mandiri dengan Docker. Web: Laravel atau Node.js dengan PostgreSQL, ditetapkan melalui ISU-09</p></td></tr><tr><td><p>Tim</p></td><td><p>3 orang (Tim Nasi Goreng Adul Enak)</p></td></tr></tbody></table>

## **2. Latar Belakang & Masalah Bisnis**

Kost Putri Kirana adalah usaha kos di Cikarang Utara, Kabupaten Bekasi, dengan 8 kamar yang hampir selalu terisi oleh penghuni berprofil karyawan. Kebutuhan hunian sewa di sekitarnya tinggi karena Kabupaten Bekasi merupakan kawasan industri.

Pencatatan data dan kontrak penghuni, penagihan sewa, penanganan keluhan fasilitas, dan pencatatan biaya masih dilakukan manual lewat Google Spreadsheet dan WhatsApp, sehingga muncul lima masalah:

- Tunggakan tidak terpantau. Saat ini ada satu penghuni yang menunggak, dan statusnya hanya bisa diketahui pemilik dari catatan Spreadsheet dan pesan WhatsApp pengelola.
- Keluhan fasilitas tidak terlacak. Keluhan masuk lewat chat tanpa nomor dan status, sehingga perkembangannya sulit dipantau penghuni maupun pemilik.
- Kontrak habis terlambat diketahui. Data kontrak dan riwayat pembayaran tersimpan terpisah, sehingga satu kontrak baru diketahui berakhir setelah masa kontraknya lewat.
- Laporan keuangan lambat dan kurang transparan. Rekap pembayaran dan laporan bulanan disusun manual, dan biaya vendor tidak terekap.
- Data pribadi penghuni berisiko tersebar. Identitas, kontrak, dan riwayat pembayaran tersimpan di chat dan Spreadsheet, padahal UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi mewajibkan pengendaliannya.

**Nilai awal masalah**

Nilai berikut diperoleh dari wawancara dengan pemilik pada 2 Oktober 2026, observasi awal, dan catatan Google Spreadsheet. Nilai ini dikonfirmasi ulang pada pra-sprint dan menjadi pembanding saat evaluasi.

<table><thead><tr><th><p>Indikator</p></th><th><p>Nilai awal</p></th></tr></thead><tbody><tr><td><p>Jumlah kamar dan penghuni aktif</p></td><td><p>8 kamar dan 8 penghuni</p></td></tr><tr><td><p>Tingkat hunian rata-rata 6 bulan terakhir</p></td><td><p>99%</p></td></tr><tr><td><p>Lama kamar kosong rata-rata</p></td><td><p>15 hari</p></td></tr><tr><td><p>Jumlah penghuni menunggak dan total tunggakan</p></td><td><p>1 penghuni; nominal total tunggakan dikonfirmasi pada pengumpulan data (ASM-02)</p></td></tr><tr><td><p>Rata-rata keterlambatan pembayaran</p></td><td><p>1 hari</p></td></tr><tr><td><p>Waktu rekap pembayaran per bulan</p></td><td><p>1 jam</p></td></tr><tr><td><p>Jumlah keluhan fasilitas per bulan</p></td><td><p>1 keluhan</p></td></tr><tr><td><p>Rata-rata waktu dari lapor sampai selesai</p></td><td><p>5 hari</p></td></tr><tr><td><p>Keluhan berulang atau terlewat</p></td><td><p>1 dari 6 keluhan (6 bulan terakhir)</p></td></tr><tr><td><p>Kontrak habis yang terlambat diketahui</p></td><td><p>1 kontrak (6 bulan terakhir)</p></td></tr><tr><td><p>Waktu penyusunan laporan keuangan bulanan</p></td><td><p>1 jam</p></td></tr></tbody></table>

**Akar masalah**

Analisis fishbone dan 5 Why menghasilkan tiga akar masalah prioritas. AM-03 adalah penyebab mendasar dua lainnya, karena tanpa data terpusat tagihan dan perbaikan tidak dapat saling terhubung.

<table><thead><tr><th><p>Kode</p></th><th><p>Akar masalah</p></th><th><p>Pihak terdampak</p></th></tr></thead><tbody><tr><td><p>AM-01</p></td><td><p>Penagihan dan pemantauan tunggakan belum terintegrasi dengan kontrak dan pembayaran</p></td><td><p>Pemilik, pengelola, penghuni</p></td></tr><tr><td><p>AM-02</p></td><td><p>Penanganan keluhan fasilitas belum memiliki alur, status, dan pelacakan yang terhubung dengan vendor</p></td><td><p>Penghuni, pengelola, vendor</p></td></tr><tr><td><p>AM-03</p></td><td><p>Data hunian, kontrak, dan keuangan tersebar dan tidak terpusat, termasuk data pribadi penghuni</p></td><td><p>Pemilik, pengelola, penghuni</p></td></tr></tbody></table>

## **3. Tujuan Bisnis & Indikator Keberhasilan**

Tujuan utamanya adalah data hunian, kontrak, tagihan, dan keluhan tercatat di satu tempat, sehingga pemilik dapat memantau usahanya tanpa menunggu rekap manual pengelola. Sistem dinyatakan layak diterima jika seluruh indikator di bawah mencapai target pada evaluasi Sprint 4 (minggu 13–14).

<table><thead><tr><th><p>Kode</p></th><th><p>Tujuan bisnis</p></th><th><p>Indikator keberhasilan dan target</p></th><th><p>Metode uji</p></th></tr></thead><tbody><tr><td><p>TB-01</p></td><td><p>Fungsi utama berjalan sesuai kebutuhan</p></td><td><p>100% skenario kritis lulus dan minimal 90% seluruh skenario lulus (login dan hak akses, penghuni dan kontrak, tagihan dan pembayaran, tiket keluhan, dashboard pemilik)</p></td><td><p>Black-box testing tiap sprint; UAT oleh pengelola, penghuni, vendor, dan pemilik</p></td></tr><tr><td><p>TB-02</p></td><td><p>Data web dan ERP konsisten</p></td><td><p>100% dari minimal 10 transaksi uji tercatat di ERP dengan nominal, tanggal, dan status yang sama</p></td><td><p>Integration testing dan pencocokan data web dengan ERP</p></td></tr><tr><td><p>TB-03</p></td><td><p>Rekap keuangan akurat</p></td><td><p>Selisih 0 antara rekap tagihan, pembayaran, dan tunggakan dengan data sumber pada satu periode uji</p></td><td><p>Pencocokan dengan Google Spreadsheet periode yang sama</p></td></tr><tr><td><p>TB-04</p></td><td><p>Data penghuni terlindungi</p></td><td><p>100% skenario uji hak akses lulus; identitas dan kontrak penghuni hanya dapat dibuka pemilik dan pengelola (serta penghuni untuk datanya sendiri)</p></td><td><p>Pengujian hak akses per peran oleh tim</p></td></tr><tr><td><p>TB-05</p></td><td><p>Kerja administrasi lebih efisien</p></td><td><p>Waktu rekap pembayaran dan penyusunan laporan keuangan bulanan masing-masing turun minimal 50% dari nilai awal 1 jam</p></td><td><p>Pengukuran waktu pada uji coba dengan data anonim satu periode</p></td></tr><tr><td><p>TB-06</p></td><td><p>Web mudah digunakan</p></td><td><p>Skor System Usability Scale rata-rata minimal 68</p></td><td><p>SUS, seluruh pengguna uji</p></td></tr><tr><td><p>TB-07</p></td><td><p>Web diterima pengguna</p></td><td><p>Persentase skor persepsi kemanfaatan dan kemudahan penggunaan minimal 80%</p></td><td><p>Kuesioner TAM skala 1–5, seluruh pengguna uji</p></td></tr><tr><td><p>TB-08</p></td><td><p>Solusi disetujui problem owner</p></td><td><p>100% item backlog berprioritas tinggi diterima pemilik, dibuktikan berita acara</p></td><td><p>Sprint review akhir dan validasi pemilik</p></td></tr></tbody></table>

Indikator yang belum tercapai dicatat sebagai rekomendasi perbaikan. Karena responden terbatas pada pemilik, pengelola, dan hingga 8 penghuni, hasilnya dibaca sebagai kelayakan untuk Kost Putri Kirana, bukan generalisasi.

### **Biaya & Manfaat**

Anggaran proyek ditetapkan melalui ISU-08. Komponen biaya berikut menjadi acuan saat memilih platform ERP pada minggu 6.

<table><thead><tr><th><p>Komponen</p></th><th><p>Biaya</p></th><th><p>Keterangan</p></th></tr></thead><tbody><tr><td><p>Pengembangan web</p></td><td><p>Tenaga tim</p></td><td><p>Dikembangkan sendiri oleh 3 anggota tim</p></td></tr><tr><td><p>Platform ERP</p></td><td><p>Rp0 lisensi</p></td><td><p>Odoo Community 19 berlisensi LGPLv3 dan dipasang mandiri dengan Docker; biaya server mengikuti ISU-08</p></td></tr><tr><td><p>Payment gateway</p></td><td><p>Tidak ada</p></td><td><p>Di luar cakupan; pembayaran diverifikasi manual oleh pengelola</p></td></tr><tr><td><p>Lingkungan uji coba</p></td><td><p>Belum ditetapkan</p></td><td><p>Proyek tidak mencakup implementasi produksi penuh</p></td></tr><tr><td><p>Waktu problem owner</p></td><td><p>Wawancara, sprint review, UAT</p></td><td><p>Pemilik dan pengelola terlibat setiap sprint; review dapat dilakukan asinkron</p></td></tr></tbody></table>

**Manfaat yang diharapkan**

- Pemilik: informasi hunian, tunggakan, dan keuangan yang lebih cepat dan transparan untuk mengambil keputusan.
- Pengelola: pencatatan terpusat yang mengurangi kerja manual dan salah catat.
- Penghuni: tagihan yang jelas, keluhan yang terlacak, dan data pribadi yang lebih aman.
- Vendor: permintaan kerja yang terstruktur dan biaya yang tercatat, sehingga pembayaran lebih tepat waktu.

## **4. Ruang Lingkup**

Cakupan dibatasi pada satu objek, yaitu Kost Putri Kirana, dengan dua komponen: web pengelolaan kos sebagai sistem operasional harian dan ERP sebagai back-office.

**Termasuk dalam cakupan: web pengelolaan kos**

- Login dan pembatasan akses berbasis peran untuk pemilik, pengelola, penghuni, dan vendor.
- Pengelolaan penghuni dan kontrak: data penghuni, informasi kamar, periode kontrak, dan status hunian.
- Pengelolaan pembayaran: informasi tagihan sewa, pengiriman bukti pembayaran, verifikasi pembayaran, dan riwayat transaksi penghuni.
- Pengelolaan keluhan fasilitas: pengajuan keluhan, pembuatan tiket layanan, dan pemantauan status penyelesaian.
- Layanan vendor: melihat permintaan kerja, memperbarui status pekerjaan, dan mengisi biaya perbaikan.
- Dashboard pemilik berisi ringkasan hunian dan tunggakan.
- Migrasi data awal dari Google Spreadsheet.

**Termasuk dalam cakupan: ERP dan integrasi**

- Modul Keuangan dan Akuntansi: pencatatan pemasukan dan pengeluaran, transaksi pembayaran, dan laporan keuangan sederhana.
- Modul Pengadaan dan Hutang Vendor: pencatatan biaya perbaikan, informasi vendor, dan riwayat pekerjaan.
- Modul Aset dan Pemeliharaan: pencatatan fasilitas, riwayat perawatan, dan status pemeliharaan.
- Integrasi web ke ERP melalui JSON-2 API Odoo, dengan status transaksi dikembalikan ke web.
- Dokumentasi konfigurasi ERP dan integrasi API.

**Di luar cakupan**

- Aplikasi mobile.
- Integrasi pembayaran otomatis (payment gateway); pembayaran tetap diverifikasi manual oleh pengelola.
- Sistem akuntansi kompleks; ERP hanya menyajikan laporan keuangan sederhana.
- Implementasi produksi penuh.
- Pembangunan ERP dari awal; ERP yang sudah ada hanya dikonfigurasi dan dikustomisasi sebatas kebutuhan.
- Usaha kos selain Kost Putri Kirana.

## **5. Pemangku Kepentingan & Peran Pengguna**

Web dipakai oleh empat peran. Pemilik sekaligus menjadi problem owner yang memvalidasi hasil setiap sprint.

<table><thead><tr><th><p>Pihak</p></th><th><p>Peran</p></th><th><p>Kepentingan / hak akses</p></th></tr></thead><tbody><tr><td><p>Pemilik</p></td><td><p>Pengguna web, problem owner</p></td><td><p>Memantau hunian, tunggakan, dan laporan keuangan; menetapkan bobot kriteria SAW; memvalidasi backlog dan menandatangani berita acara</p></td></tr><tr><td><p>Pengelola</p></td><td><p>Pengguna web</p></td><td><p>Mengelola penghuni, kamar, dan kontrak; menerbitkan tagihan dan memverifikasi pembayaran; meninjau tiket keluhan dan menugaskan vendor</p></td></tr><tr><td><p>Penghuni</p></td><td><p>Pengguna web</p></td><td><p>Melihat tagihan dan riwayat transaksi miliknya; mengunggah bukti bayar; mengajukan dan memantau keluhan</p></td></tr><tr><td><p>Vendor</p></td><td><p>Pengguna web</p></td><td><p>Melihat permintaan kerja yang ditugaskan kepadanya; memperbarui status pekerjaan; mengisi biaya perbaikan</p></td></tr><tr><td><p>ERP (Odoo Community 19)</p></td><td><p>Sistem pendukung</p></td><td><p>Menerima transaksi pembayaran, biaya perbaikan, dan pemeliharaan; menyajikan laporan keuangan sederhana</p></td></tr><tr><td><p>Tim Capstone (3 orang)</p></td><td><p>Pelaksana</p></td><td><p>Menjalankan peran Product Owner, Scrum Master, dan Developers: analisis, perancangan, pengembangan, pengujian, dan dokumentasi</p></td></tr><tr><td><p>Dosen Pembimbing</p></td><td><p>Pembimbing</p></td><td><p>Kesesuaian proyek dengan kaidah capstone dan kelengkapan deliverable</p></td></tr></tbody></table>

Akun penghuni dan vendor dibuat oleh pengelola; tidak ada registrasi mandiri (ASM-05).

### **Matriks Hak Akses Awal**

Matriks ini menjadi dasar rancangan hak akses pada Sprint 1 dan divalidasi bersama pemilik. Sel bertanda “Dikonfirmasi” ditetapkan melalui ISU-05.

<table><thead><tr><th><p>Data / fungsi</p></th><th><p>Pemilik</p></th><th><p>Pengelola</p></th><th><p>Penghuni</p></th><th><p>Vendor</p></th></tr></thead><tbody><tr><td><p>Akun pengguna</p></td><td><p>Akun sendiri</p></td><td><p>Kelola</p></td><td><p>Akun sendiri</p></td><td><p>Akun sendiri</p></td></tr><tr><td><p>Identitas dan kontrak penghuni</p></td><td><p>Lihat</p></td><td><p>Kelola</p></td><td><p>Milik sendiri</p></td><td><p>—</p></td></tr><tr><td><p>Kamar dan status hunian</p></td><td><p>Lihat</p></td><td><p>Kelola</p></td><td><p>Kamar sendiri</p></td><td><p>—</p></td></tr><tr><td><p>Tagihan dan pembayaran</p></td><td><p>Lihat</p></td><td><p>Terbitkan, verifikasi</p></td><td><p>Milik sendiri, unggah bukti</p></td><td><p>—</p></td></tr><tr><td><p>Tiket keluhan</p></td><td><p>Lihat</p></td><td><p>Tinjau, tugaskan</p></td><td><p>Ajukan, pantau milik sendiri</p></td><td><p>Yang ditugaskan</p></td></tr><tr><td><p>Status dan biaya perbaikan</p></td><td><p>Lihat</p></td><td><p>Lihat</p></td><td><p>Status tiket sendiri</p></td><td><p>Perbarui, isi biaya</p></td></tr><tr><td><p>Dashboard hunian dan tunggakan</p></td><td><p>Lihat</p></td><td><p>Dikonfirmasi</p></td><td><p>—</p></td><td><p>—</p></td></tr><tr><td><p>Laporan keuangan (ERP)</p></td><td><p>Lihat</p></td><td><p>Dikonfirmasi</p></td><td><p>—</p></td><td><p>—</p></td></tr></tbody></table>

## **6. Kebutuhan Bisnis**

Setiap kebutuhan diberi kode BR-xx dan dipetakan ke akar masalah (kolom “Dasar”, lihat Bagian 2). Kode ini dirujuk kembali di product backlog, model UML, dan skenario pengujian.

<table><thead><tr><th><p>Kode</p></th><th><p>Kebutuhan bisnis</p></th><th><p>Peran</p></th><th><p>Dasar</p></th></tr></thead><tbody><tr><td><p>BR-01</p></td><td><p>Setiap pengguna wajib login, dan menu serta data yang tampil dibatasi sesuai perannya</p></td><td><p>Semua</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-02</p></td><td><p>Pengelola mencatat dan memperbarui data penghuni (identitas dan kontak)</p></td><td><p>Pengelola</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-03</p></td><td><p>Pengelola mengelola data kamar beserta tarif dan status hunian (terisi atau kosong)</p></td><td><p>Pengelola</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-04</p></td><td><p>Pengelola mencatat kontrak penghuni: kamar, tanggal mulai, tanggal berakhir, dan tarif</p></td><td><p>Pengelola</p></td><td><p>AM-01, AM-03</p></td></tr><tr><td><p>BR-05</p></td><td><p>Sistem menampilkan kontrak yang mendekati tanggal berakhir agar tidak terlambat diketahui</p></td><td><p>Pengelola, Pemilik</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-06</p></td><td><p>Pengelola menerbitkan tagihan sewa per periode untuk semua kontrak aktif; sistem menghitung nominal dari kontrak dan mencegah tagihan ganda</p></td><td><p>Pengelola</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-07</p></td><td><p>Penghuni melihat tagihan miliknya beserta nominal, jatuh tempo, dan status</p></td><td><p>Penghuni</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-08</p></td><td><p>Penghuni mengunggah bukti pembayaran untuk tagihan miliknya</p></td><td><p>Penghuni</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-09</p></td><td><p>Pengelola memverifikasi bukti pembayaran dengan keputusan terima atau tolak</p></td><td><p>Pengelola</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-10</p></td><td><p>Riwayat transaksi tersedia per penghuni; penghuni hanya melihat riwayat miliknya</p></td><td><p>Penghuni, Pengelola, Pemilik</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-11</p></td><td><p>Sistem menandai tagihan yang lewat jatuh tempo dan belum lunas sebagai tunggakan</p></td><td><p>Pengelola, Pemilik</p></td><td><p>AM-01</p></td></tr><tr><td><p>BR-12</p></td><td><p>Penghuni mengajukan keluhan fasilitas, dan sistem membuat tiket bernomor dengan status</p></td><td><p>Penghuni</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-13</p></td><td><p>Pengelola meninjau tiket dan menugaskannya ke vendor sebagai permintaan kerja</p></td><td><p>Pengelola</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-14</p></td><td><p>Status tiket dapat dipantau sampai selesai</p></td><td><p>Penghuni, Pengelola, Pemilik</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-15</p></td><td><p>Vendor melihat permintaan kerja yang ditugaskan kepadanya</p></td><td><p>Vendor</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-16</p></td><td><p>Vendor memperbarui status pekerjaan</p></td><td><p>Vendor</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-17</p></td><td><p>Vendor mengisi biaya perbaikan pada permintaan kerja</p></td><td><p>Vendor</p></td><td><p>AM-02, AM-03</p></td></tr><tr><td><p>BR-18</p></td><td><p>Pemilik melihat dashboard ringkasan hunian dan tunggakan</p></td><td><p>Pemilik</p></td><td><p>AM-01, AM-03</p></td></tr><tr><td><p>BR-19</p></td><td><p>Pembayaran lunas dikirim ke modul Keuangan dan Akuntansi ERP</p></td><td><p>Sistem</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-20</p></td><td><p>Biaya perbaikan dikirim ke modul Pengadaan dan Hutang Vendor ERP</p></td><td><p>Sistem</p></td><td><p>AM-02, AM-03</p></td></tr><tr><td><p>BR-21</p></td><td><p>Data pemeliharaan fasilitas dikirim ke modul Aset dan Pemeliharaan ERP</p></td><td><p>Sistem</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-22</p></td><td><p>Status transaksi dari ERP dikembalikan ke web dan ditampilkan kepada pengguna</p></td><td><p>Sistem</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-23</p></td><td><p>ERP mencatat pemasukan dan pengeluaran serta menyajikan laporan keuangan sederhana</p></td><td><p>Pemilik, Pengelola</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-24</p></td><td><p>ERP menyimpan informasi vendor beserta riwayat pekerjaannya</p></td><td><p>Pemilik, Pengelola</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-25</p></td><td><p>ERP mencatat fasilitas beserta riwayat perawatan dan status pemeliharaannya</p></td><td><p>Pemilik, Pengelola</p></td><td><p>AM-02</p></td></tr><tr><td><p>BR-26</p></td><td><p>Data awal dari Google Spreadsheet dimigrasikan ke basis data web; data untuk uji coba dianonimkan</p></td><td><p>Tim</p></td><td><p>AM-03</p></td></tr><tr><td><p>BR-27</p></td><td><p>Setiap sprint menghasilkan dokumentasi: BPMN, UML, ERD, skenario uji, dan dokumentasi konfigurasi ERP</p></td><td><p>Tim</p></td><td><p>—</p></td></tr></tbody></table>

### **Kebutuhan Non-Fungsional**

Kebutuhan berikut mengatur kualitas sistem, bukan fiturnya, dan ikut diuji pada evaluasi.

<table><thead><tr><th><p>Kode</p></th><th><p>Kategori</p></th><th><p>Kebutuhan</p></th></tr></thead><tbody><tr><td><p>NFR-01</p></td><td><p>Keamanan akses</p></td><td><p>Hak akses per peran (RBAC) dicek di server, bukan hanya disembunyikan di tampilan. Password disimpan dalam bentuk hash.</p></td></tr><tr><td><p>NFR-02</p></td><td><p>Pelindungan data pribadi</p></td><td><p>Identitas dan kontrak penghuni hanya dapat dibuka pemilik, pengelola, dan penghuni yang bersangkutan. Vendor dan penghuni lain tidak dapat melihatnya. Pengelolaan data mengacu pada UU No. 27 Tahun 2022.</p></td></tr><tr><td><p>NFR-03</p></td><td><p>Keamanan integrasi</p></td><td><p>Setiap permintaan dari web ke ERP melalui JSON-2 API Odoo membawa API key sebagai bearer token. API key milik pengguna integrasi khusus dengan hak akses minimum, disimpan di server, dan dirotasi paling lambat setiap tiga bulan.</p></td></tr><tr><td><p>NFR-04</p></td><td><p>Konsistensi web–ERP</p></td><td><p>Nominal, tanggal, dan status transaksi di web dan ERP sama. Setiap transaksi memiliki status sinkronisasi (terkirim atau gagal) yang terlihat di web, sehingga transaksi yang gagal dapat dikirim ulang. Setiap transaksi membawa referensi unik agar pengiriman ulang tidak tercatat dua kali di ERP.</p></td></tr><tr><td><p>NFR-05</p></td><td><p>Integritas data</p></td><td><p>Basis data relasional dinormalisasi sampai bentuk normal ketiga, dengan foreign key dan integritas referensial.</p></td></tr><tr><td><p>NFR-06</p></td><td><p>Kegunaan</p></td><td><p>Antarmuka diuji pengguna pada setiap sprint review. Skor SUS rata-rata minimal 68.</p></td></tr><tr><td><p>NFR-07</p></td><td><p>Kualitas</p></td><td><p>Kualitas sistem dievaluasi dengan karakteristik functional suitability dan usability ISO/IEC 25010.</p></td></tr><tr><td><p>NFR-08</p></td><td><p>Etika data uji</p></td><td><p>Data untuk migrasi uji coba dianonimkan. Identitas dan nomor kontak penghuni tidak disalin ke dokumen proyek, dan responden memberi persetujuan sebelum diwawancarai.</p></td></tr><tr><td><p>NFR-09</p></td><td><p>Dokumentasi</p></td><td><p>Setiap sprint memenuhi Definition of Done yang mewajibkan dokumentasi (model proses dan data, UML, ERD, skenario uji, konfigurasi ERP).</p></td></tr></tbody></table>

## **7. Aturan Bisnis**

Aturan berikut wajib ditegakkan di server, bukan hanya di tampilan, karena tampilan bisa dilewati.

<table><thead><tr><th><p>Kode</p></th><th><p>Aturan</p></th><th><p>Dasar</p></th></tr></thead><tbody><tr><td><p>ATB-01</p></td><td><p>Satu kamar hanya memiliki satu kontrak aktif pada satu waktu. Kamar dengan kontrak aktif berstatus terisi; kamar tanpa kontrak aktif berstatus kosong.</p></td><td><p>BR-03, BR-04</p></td></tr><tr><td><p>ATB-02</p></td><td><p>Tagihan diterbitkan per kontrak per periode, dengan nominal sesuai tarif pada kontrak. Kontrak yang sudah berakhir tidak menerbitkan tagihan baru.</p></td><td><p>BR-06</p></td></tr><tr><td><p>ATB-03</p></td><td><p>Unggahan bukti bayar mengubah status tagihan menjadi menunggu verifikasi, belum lunas. Tagihan baru lunas setelah pengelola menerima bukti tersebut.</p></td><td><p>BR-08, BR-09</p></td></tr><tr><td><p>ATB-04</p></td><td><p>Bukti yang ditolak mengembalikan tagihan ke status belum dibayar, dan penghuni dapat mengunggah bukti baru.</p></td><td><p>BR-09</p></td></tr><tr><td><p>ATB-05</p></td><td><p>Tagihan yang belum lunas dihitung sebagai tunggakan mulai hari setelah jatuh tempo dan tampil di dashboard pemilik. Pengecualian: tagihan yang buktinya diunggah paling lambat pada tanggal jatuh tempo dan masih menunggu verifikasi tidak dihitung tunggakan. Bukti yang diunggah setelah jatuh tempo tidak menghapus tunggakan sampai diterima. Bila bukti yang diunggah tepat waktu ditolak, tagihan dihitung tunggakan sejak hari setelah jatuh tempo.</p></td><td><p>BR-11, BR-18</p></td></tr><tr><td><p>ATB-06</p></td><td><p>Hanya pembayaran berstatus lunas yang dikirim ke ERP. Data yang dikirim ke ERP dibatasi pada tiga jenis transaksi di tabel integrasi (Bagian 8).</p></td><td><p>BR-19 s.d. BR-21</p></td></tr><tr><td><p>ATB-07</p></td><td><p>Setiap keluhan menjadi satu tiket bernomor unik dengan alur status diajukan → ditugaskan → dikerjakan → selesai.</p></td><td><p>BR-12 s.d. BR-16</p></td></tr><tr><td><p>ATB-08</p></td><td><p>Vendor hanya melihat dan memperbarui permintaan kerja yang ditugaskan kepadanya, beserta informasi kamar dan fasilitas. Vendor tidak melihat identitas, kontrak, atau tagihan penghuni.</p></td><td><p>BR-15, BR-16</p></td></tr><tr><td><p>ATB-09</p></td><td><p>Biaya perbaikan dikirim ke ERP setelah tiket berstatus selesai.</p></td><td><p>BR-17, BR-20</p></td></tr><tr><td><p>ATB-10</p></td><td><p>Penghuni hanya melihat kontrak, tagihan, riwayat transaksi, dan tiket miliknya sendiri.</p></td><td><p>BR-07, BR-10, BR-14</p></td></tr><tr><td><p>ATB-11</p></td><td><p>Riwayat kontrak, pembayaran, dan tiket penghuni yang sudah keluar tetap tersimpan untuk laporan. Masa simpan dan penghapusannya ditetapkan bersama pemilik dengan mengacu pada UU No. 27 Tahun 2022.</p></td><td><p>BR-10, NFR-02</p></td></tr></tbody></table>

**Siklus status**

*Status tagihan: belum dibayar → menunggu verifikasi → lunas*

*Status tiket: diajukan → ditugaskan → dikerjakan → selesai*

*Tunggakan = tagihan belum lunas yang sudah lewat jatuh tempo, kecuali buktinya diunggah paling lambat pada jatuh tempo dan masih menunggu verifikasi (ATB-05)*

Tanggal jatuh tempo, ketentuan denda keterlambatan, dan batas hari peringatan kontrak ditetapkan melalui ISU-01 dan ISU-02. Nilainya disimpan sebagai pengaturan sistem sehingga dapat diubah tanpa mengubah kode.

## **8. Proses Bisnis**

Saat ini penagihan, konfirmasi pembayaran, dan laporan keluhan berlangsung lewat WhatsApp, lalu direkap pengelola di Google Spreadsheet. Proses usulan (to-be) dimodelkan dengan activity diagram berlajur pada Gambar 1 dan Gambar 2. BPMN as-is dan to-be yang rinci disusun pada Sprint 1.

![](https://cdn-mineru.openxlab.org.cn/result/2026-10-29/33c55054-d815-48ab-9675-8b46c853daaf/0a700788022c536237aeddbf8460de741355656ac382251a26ebfc79b334a2d2.jpg)

*Gambar 1. Activity diagram tagihan dan pembayaran sewa*

Pengelola menerbitkan tagihan per periode, lalu dua alur berjalan paralel. Penghuni mengunggah bukti bayar yang diverifikasi pengelola, sementara sistem memeriksa jatuh tempo setiap hari dan menandai tunggakan sesuai ATB-05. Pembayaran lunas dikirim ke Odoo; pengiriman yang gagal dikirim ulang oleh pengelola.

![](https://cdn-mineru.openxlab.org.cn/result/2026-10-29/33c55054-d815-48ab-9675-8b46c853daaf/40d837b0c6d16baba870f00870ca6b47437019589b09f240c6b08418deb93988.jpg)

*Gambar 2. Activity diagram keluhan fasilitas dan perbaikan*

Setelah tiket selesai, biaya dan data pemeliharaan dikirim ke Odoo, sedangkan penghuni dan pemilik memantau status tiket. Vendor hanya melihat tiket yang ditugaskan kepadanya.

**Integrasi web dan ERP**

1. Pengguna melakukan aktivitas melalui web pengelolaan kos.
2. Sistem memvalidasi data transaksi.
3. Data transaksi dikirim ke Odoo melalui JSON-2 API (POST /json/2/<model>/<method>) beserta referensi unik transaksi.
4. ERP menyimpan data di modul yang berkaitan.
5. Status transaksi dikembalikan ke web dan ditampilkan kepada pengguna.

<table><thead><tr><th><p>Proses</p></th><th><p>Data yang dikirim</p></th><th><p>Modul ERP</p></th></tr></thead><tbody><tr><td><p>Pembayaran sewa</p></td><td><p>ID penghuni, periode sewa, nominal pembayaran, tanggal pembayaran, status pembayaran</p></td><td><p>Keuangan dan Akuntansi</p></td></tr><tr><td><p>Biaya perbaikan</p></td><td><p>Jenis kerusakan, fasilitas, biaya, vendor</p></td><td><p>Pengadaan dan Hutang Vendor</p></td></tr><tr><td><p>Pemeliharaan fasilitas</p></td><td><p>Data aset, riwayat perbaikan, status pekerjaan</p></td><td><p>Aset dan Pemeliharaan</p></td></tr></tbody></table>

Proposal menyebut mekanisme ini sebagai REST API dalam arti umum. Implementasinya memakai JSON-2 API Odoo 19, yaitu API HTTP berformat JSON. Endpoint XML-RPC dan JSON-RPC lama tidak dipakai karena dijadwalkan dihapus pada Odoo 20.

**Pemetaan modul ERP ke Odoo Community 19**

<table><thead><tr><th><p>Modul ERP</p></th><th><p>Aplikasi Odoo</p></th><th><p>Catatan</p></th></tr></thead><tbody><tr><td><p>Keuangan dan Akuntansi</p></td><td><p>Invoicing (modul account): faktur, pembayaran, dan jurnal</p></td><td><p>Laporan laba rugi dan neraca bawaan hanya tersedia di Enterprise. Laporan keuangan sederhana memakai laporan Invoicing atau modul OCA account_financial_report. Perlu diverifikasi di instance (ISU-10).</p></td></tr><tr><td><p>Pengadaan dan Hutang Vendor</p></td><td><p>Purchase dan tagihan vendor (vendor bills)</p></td><td><p>Perlu diverifikasi di instance.</p></td></tr><tr><td><p>Aset dan Pemeliharaan</p></td><td><p>Maintenance: peralatan (equipment) dan permintaan perawatan</p></td><td><p>Penyusutan aset (Assets) hanya tersedia di Enterprise dan tidak dibutuhkan. Perlu diverifikasi di instance.</p></td></tr></tbody></table>

## **9. Asumsi, Dependensi, Batasan & Risiko**

Risiko terbesar adalah sinkronisasi data antara web dan ERP serta beban mengembangkan dua sistem dalam 10 minggu oleh tim beranggotakan 3 orang.

**Asumsi**

<table><thead><tr><th><p>Kode</p></th><th><p>Asumsi</p></th><th><p>Dampak bila asumsi tidak terbukti</p></th></tr></thead><tbody><tr><td><p>ASM-01</p></td><td><p>Alternatif B (web sebagai sistem inti, ERP sebagai back-office) menjadi solusi terpilih. Pemilihan akhir memakai SAW bersama pemilik.</p></td><td><p>BRD direvisi mengikuti alternatif yang terpilih</p></td></tr><tr><td><p>ASM-02</p></td><td><p>Nilai awal indikator pada Bagian 2, termasuk nominal total tunggakan, mewakili kondisi aktual.</p></td><td><p>Target efisiensi dan pembanding evaluasi disesuaikan setelah konfirmasi pra-sprint</p></td></tr><tr><td><p>ASM-03</p></td><td><p>Tagihan diterbitkan bulanan.</p></td><td><p>ATB-02 dan penerbitan tagihan disesuaikan</p></td></tr><tr><td><p>ASM-04</p></td><td><p>Satu kamar dihuni satu penghuni.</p></td><td><p>Model kontrak dan ATB-01 disesuaikan</p></td></tr><tr><td><p>ASM-05</p></td><td><p>Akun penghuni dan vendor dibuat oleh pengelola; tidak ada registrasi mandiri.</p></td><td><p>Dibutuhkan alur registrasi dan verifikasi akun</p></td></tr><tr><td><p>ASM-06</p></td><td><p>Penghuni dapat melihat identitas dan kontrak miliknya sendiri; larangan akses berlaku bagi vendor dan penghuni lain.</p></td><td><p>Matriks hak akses dan NFR-02 disesuaikan</p></td></tr><tr><td><p>ASM-07</p></td><td><p>Penghuni dan vendor terutama mengakses web dari ponsel.</p></td><td><p>Prioritas desain tampilan bergeser ke layar desktop</p></td></tr></tbody></table>

**Dependensi**

<table><thead><tr><th><p>Kode</p></th><th><p>Dependensi</p></th><th><p>Dibutuhkan untuk</p></th></tr></thead><tbody><tr><td><p>DEP-01</p></td><td><p>Instance Odoo Community 19 berjalan dengan pengguna integrasi dan API key aktif untuk mencatat transaksi di tiga modul</p></td><td><p>BR-19 s.d. BR-22, TB-02</p></td></tr><tr><td><p>DEP-02</p></td><td><p>Pemilik mengizinkan akses ke dokumen kontrak, pembayaran, dan biaya operasional 6 bulan terakhir</p></td><td><p>BR-26, TB-03</p></td></tr><tr><td><p>DEP-03</p></td><td><p>Pemilik dan pengelola tersedia untuk sprint review dan UAT</p></td><td><p>TB-01, TB-08</p></td></tr><tr><td><p>DEP-04</p></td><td><p>Penghuni dan vendor bersedia menjadi responden dan pengguna uji</p></td><td><p>TB-06, TB-07</p></td></tr><tr><td><p>DEP-05</p></td><td><p>Bobot kriteria SAW ditetapkan bersama pemilik</p></td><td><p>ASM-01</p></td></tr></tbody></table>

**Isu terbuka**

Tanggal mengacu pada kalender pengembangan: minggu 7 = 26 Oktober–1 November 2026, minggu 8 = 2–8 November 2026.

<table><thead><tr><th><p>Kode</p></th><th><p>Isu</p></th><th><p>Penanggung jawab</p></th><th><p>Target keputusan</p></th><th><p>Status</p></th></tr></thead><tbody><tr><td><p>ISU-01</p></td><td><p>Tanggal jatuh tempo tagihan dan ketentuan denda keterlambatan</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-02</p></td><td><p>Batas hari peringatan sebelum kontrak berakhir</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-03</p></td><td><p>Kebutuhan pengingat tagihan atau notifikasi kepada penghuni</p></td><td><p>Pemilik, Tim</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-04</p></td><td><p>Pihak yang mengoperasikan ERP sehari-hari</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-05</p></td><td><p>Akses pengelola ke dashboard ringkasan dan laporan keuangan ERP</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-06</p></td><td><p>Platform ERP</p></td><td><p>Tim</p></td><td><p>Pra-sprint</p></td><td><p>Ditutup: Odoo Community 19, Docker, JSON-2 API</p></td></tr><tr><td><p>ISU-07</p></td><td><p>Penetapan anggota untuk peran Product Owner, Scrum Master, dan Developers</p></td><td><p>Tim</p></td><td><p>30 Okt 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-08</p></td><td><p>Anggaran proyek, terutama biaya server untuk Odoo dan web</p></td><td><p>Pemilik, Tim</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-09</p></td><td><p>Teknologi web: Laravel atau Node.js, dengan PostgreSQL</p></td><td><p>Tim</p></td><td><p>30 Okt 2026</p></td><td><p>Terbuka</p></td></tr><tr><td><p>ISU-10</p></td><td><p>Kecukupan laporan keuangan sederhana di Odoo Community (Invoicing atau modul OCA)</p></td><td><p>Tim</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td></tr></tbody></table>

**Batasan**

- Objek tunggal: Kost Putri Kirana, Cikarang Utara, Kabupaten Bekasi.
- Waktu pengembangan 10 minggu (minggu 5–14) oleh tim beranggotakan 3 orang.
- Tidak mencakup aplikasi mobile, integrasi pembayaran otomatis, sistem akuntansi kompleks, dan implementasi produksi penuh.
- ERP dikonfigurasi dan dikustomisasi sebatas kebutuhan, tidak dibangun dari awal.
- Responden pengujian terbatas pada pemilik, pengelola, dan hingga 8 penghuni.

**Kriteria pemilihan ERP**

Platform ERP dipilih pada pra-sprint dengan kriteria berikut, dan hasilnya adalah Odoo Community 19 (ISU-06). Dukungan API menjadi kriteria utama karena seluruh integrasi berjalan melalui API.

<table><thead><tr><th><p>Kriteria</p></th><th><p>Penjelasan</p></th></tr></thead><tbody><tr><td><p>Dukungan API</p></td><td><p>ERP menyediakan API HTTP berformat JSON untuk integrasi dengan web</p></td></tr><tr><td><p>Modul bisnis</p></td><td><p>Memiliki modul Keuangan dan Akuntansi, Pengadaan dan Hutang Vendor, serta Aset dan Pemeliharaan</p></td></tr><tr><td><p>Kemudahan konfigurasi</p></td><td><p>Dapat disesuaikan dengan proses bisnis kos</p></td></tr><tr><td><p>Dokumentasi teknis</p></td><td><p>Memiliki dokumentasi penggunaan dan integrasi</p></td></tr><tr><td><p>Biaya implementasi</p></td><td><p>Sesuai dengan skala usaha Kost Putri Kirana</p></td></tr></tbody></table>

**Risiko & mitigasi**

<table><thead><tr><th><p>Risiko</p></th><th><p>Dampak</p></th><th><p>Mitigasi</p></th></tr></thead><tbody><tr><td><p>Data web dan ERP tidak sinkron</p></td><td><p>Selisih data keuangan; laporan tidak dapat dipercaya</p></td><td><p>Batasi transaksi yang dikirim ke tiga jenis; dokumentasikan integrasi API; tampilkan status sinkronisasi di web; cocokkan minimal 10 transaksi uji pada Sprint 3–4</p></td></tr><tr><td><p>Beban mengembangkan dua sistem dalam 10 minggu</p></td><td><p>Fitur tidak selesai tepat waktu</p></td><td><p>Kerjakan item backlog berprioritas tinggi lebih dulu; keluarkan item berprioritas rendah dari sprint dan catat sebagai pengembangan lanjutan</p></td></tr><tr><td><p>Kurva belajar dan dukungan API ERP</p></td><td><p>Konfigurasi dan integrasi terhambat</p></td><td><p>Uji coba JSON-2 API di instance Odoo pada Sprint 1; konfigurasi sebatas kebutuhan; batasi kustomisasi</p></td></tr><tr><td><p>Fitur keuangan Odoo Community terbatas</p></td><td><p>Laporan keuangan sederhana tidak tersedia secara bawaan</p></td><td><p>Verifikasi laporan Invoicing atau modul OCA account_financial_report pada Sprint 1 (ISU-10)</p></td></tr><tr><td><p>Sprint 3 memuat 12 fitur</p></td><td><p>Integrasi dan modul keluhan tidak selesai dalam sprint</p></td><td><p>F-24 dan F-28 (Should) menjadi kandidat pertama yang digeser ke pengembangan lanjutan</p></td></tr><tr><td><p>Kebocoran data pribadi penghuni</p></td><td><p>Pelanggaran UU No. 27 Tahun 2022 dan hilangnya kepercayaan penghuni</p></td><td><p>Terapkan RBAC sejak rancangan basis data; anonimkan data uji; minta persetujuan responden</p></td></tr><tr><td><p>Kebutuhan berubah selama pengembangan</p></td><td><p>Pekerjaan ulang</p></td><td><p>Sprint review setiap dua minggu bersama pemilik; prioritaskan ulang backlog di setiap sprint</p></td></tr><tr><td><p>Dokumentasi tertinggal karena fokus pada pengiriman cepat</p></td><td><p>Deliverable SDLC tidak lengkap</p></td><td><p>Definition of Done mewajibkan dokumentasi di setiap sprint</p></td></tr><tr><td><p>Pemilik atau pengguna sulit hadir untuk review dan UAT</p></td><td><p>Validasi tertunda</p></td><td><p>Sprint review dapat dilakukan asinkron; jadwal UAT disepakati sejak pra-sprint</p></td></tr></tbody></table>

## **10. Deliverable, Pembagian Tim & Alur Pengerjaan**

Pengembangan berlangsung 10 minggu (minggu 5–14), terdiri atas pra-sprint dan empat sprint dua mingguan. Sprint dinyatakan selesai bila memenuhi Definition of Done, termasuk dokumentasinya.

<table><thead><tr><th><p>Sprint</p></th><th><p>Sasaran</p></th><th><p>Keluaran yang harus selesai</p></th><th><p>Bukti selesai</p></th></tr></thead><tbody><tr><td><p>Pra-sprint (minggu 5–6)</p></td><td><p>Masalah dan kebutuhan terkonfirmasi</p></td><td><p>Nilai awal indikator terkonfirmasi, product backlog, bobot SAW, platform ERP terpilih</p></td><td><p>Catatan wawancara dan observasi; backlog disetujui pemilik</p></td></tr><tr><td><p>Sprint 1 (minggu 7–8)</p></td><td><p>Rancangan siap dan fondasi web</p></td><td><p>BPMN to-be, ERD dan skema basis data, rancangan hak akses, desain UI, web dengan login dan hak akses, Odoo Community 19 terpasang</p></td><td><p>Review pemilik atas proses to-be dan desain UI; uji login dan hak akses lulus</p></td></tr><tr><td><p>Sprint 2 (minggu 9–10)</p></td><td><p>Fungsi inti penghuni dan pembayaran</p></td><td><p>Modul penghuni, kamar, dan kontrak; modul tagihan dan pembayaran; diagram UML modul; skenario dan hasil uji; data awal termigrasi</p></td><td><p>Demo ke pengelola; skenario kritis modul lulus</p></td></tr><tr><td><p>Sprint 3 (minggu 11–12)</p></td><td><p>ERP dan integrasi</p></td><td><p>ERP terkonfigurasi untuk tiga fungsi, integrasi JSON-2 API, modul tiket keluhan, akses vendor, dokumentasi konfigurasi ERP</p></td><td><p>Transaksi uji tercatat di ERP; demo ke pemilik dan pengelola</p></td></tr><tr><td><p>Sprint 4 (minggu 13–14)</p></td><td><p>Pengujian, evaluasi, dan validasi</p></td><td><p>Dashboard pemilik, hasil integration test dan UAT, skor SUS dan TAM, pengukuran ulang indikator, berita acara validasi, sistem final, dokumentasi, laporan akhir</p></td><td><p>Seluruh indikator Bagian 3 terukur; berita acara ditandatangani pemilik</p></td></tr></tbody></table>

**Pembagian tim**

Peran Scrum dibagi di antara tiga anggota tim: Product Owner (mewakili problem owner), Scrum Master, dan Developers. Penetapan anggota untuk setiap peran dicatat sebagai ISU-07.

**Alur pengerjaan**

1. Identifikasi masalah (pra-sprint, minggu 5)
2. Pengumpulan data: wawancara, observasi, dan studi dokumen (minggu 5–6)
3. Analisis kebutuhan dan penyusunan product backlog, termasuk pemilihan solusi dengan SAW dan platform ERP (minggu 6)
4. Perancangan sistem: BPMN, ERD, hak akses, dan antarmuka (mulai Sprint 1, disempurnakan setiap sprint)
5. Pengembangan inkremental web dan konfigurasi ERP (Sprint 1–4, minggu 7–14)
6. Validasi dan evaluasi: UAT, SUS, TAM, pengukuran ulang indikator, dan berita acara (Sprint 4, minggu 13–14)

Item backlog berprioritas tinggi dikerjakan lebih dulu pada setiap sprint. Bila waktu tidak cukup, item berprioritas rendah dikeluarkan dari sprint dan dicatat sebagai pengembangan lanjutan.

## **11. Glosarium**

<table><thead><tr><th><p>Istilah</p></th><th><p>Arti dalam dokumen ini</p></th></tr></thead><tbody><tr><td><p>Kamar</p></td><td><p>Unit hunian yang disewakan; berstatus terisi atau kosong</p></td></tr><tr><td><p>Penghuni</p></td><td><p>Penyewa kamar yang terikat kontrak</p></td></tr><tr><td><p>Pemilik</p></td><td><p>Pemilik usaha kos sekaligus problem owner proyek</p></td></tr><tr><td><p>Pengelola</p></td><td><p>Pihak yang menjalankan operasional harian kos</p></td></tr><tr><td><p>Vendor</p></td><td><p>Teknisi, penyedia internet atau kebersihan, dan pemasok perlengkapan</p></td></tr><tr><td><p>Kontrak</p></td><td><p>Perjanjian sewa satu penghuni atas satu kamar untuk periode tertentu</p></td></tr><tr><td><p>Tagihan</p></td><td><p>Kewajiban bayar sewa per kontrak per periode</p></td></tr><tr><td><p>Bukti bayar</p></td><td><p>Berkas yang diunggah penghuni sebagai bukti pembayaran</p></td></tr><tr><td><p>Verifikasi</p></td><td><p>Keputusan pengelola menerima atau menolak bukti bayar</p></td></tr><tr><td><p>Tunggakan</p></td><td><p>Tagihan belum lunas yang sudah lewat jatuh tempo, dengan pengecualian pada ATB-05</p></td></tr><tr><td><p>Tiket keluhan</p></td><td><p>Catatan keluhan fasilitas bernomor unik dengan status</p></td></tr><tr><td><p>Permintaan kerja</p></td><td><p>Tiket keluhan yang sudah ditugaskan ke vendor</p></td></tr><tr><td><p>ERP</p></td><td><p>Enterprise Resource Planning; di proyek ini menjadi back-office keuangan, pengadaan, dan aset</p></td></tr><tr><td><p>JSON-2 API</p></td><td><p>API HTTP berformat JSON milik Odoo 19 (POST /json/2/&lt;model&gt;/&lt;method&gt;). Proposal menyebutnya REST API dalam arti umum</p></td></tr><tr><td><p>API key</p></td><td><p>Kunci akses pengguna integrasi Odoo yang dikirim sebagai bearer token; berlaku maksimal tiga bulan</p></td></tr><tr><td><p>Referensi unik transaksi</p></td><td><p>Penanda tiap transaksi agar pengiriman ulang ke ERP tidak tercatat ganda</p></td></tr><tr><td><p>Status sinkronisasi</p></td><td><p>Penanda apakah sebuah transaksi sudah tercatat di ERP (terkirim) atau belum (gagal)</p></td></tr><tr><td><p>RBAC</p></td><td><p>Role-Based Access Control, pembatasan akses data sesuai peran pengguna</p></td></tr><tr><td><p>Sprint</p></td><td><p>Siklus kerja dua minggu dalam Scrum</p></td></tr><tr><td><p>Definition of Done</p></td><td><p>Daftar periksa yang harus dipenuhi agar hasil sprint dianggap selesai</p></td></tr><tr><td><p>SAW</p></td><td><p>Simple Additive Weighting, metode pemilihan solusi dengan kriteria berbobot</p></td></tr><tr><td><p>UAT</p></td><td><p>User Acceptance Test, pengujian oleh pengguna dengan skenario tugas nyata</p></td></tr><tr><td><p>SUS</p></td><td><p>System Usability Scale, skor kebergunaan pada rentang 0–100</p></td></tr><tr><td><p>TAM</p></td><td><p>Technology Acceptance Model, ukuran persepsi kemanfaatan dan kemudahan penggunaan</p></td></tr></tbody></table>

## **12. Dokumen Acuan**

<table><thead><tr><th><p>Kode</p></th><th><p>Dokumen</p></th><th><p>Keterangan</p></th></tr></thead><tbody><tr><td><p>REF-01</p></td><td><p>Proposal Capstone Design &amp; Project: Peningkatan Pengelolaan Operasional dan Layanan Hunian dalam Usaha Kos untuk Mendukung Efisiensi Operasional dan Kepuasan Penghuni melalui Integrasi dan Inovasi Sistem Informasi. Program Studi S1 Sistem Informasi, Universitas Telkom, 2026</p></td><td><p>Latar belakang, nilai awal, solusi, indikator, dan jadwal</p></td></tr><tr><td><p>REF-02</p></td><td><p>Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi</p></td><td><p>Dasar pelindungan data penghuni</p></td></tr><tr><td><p>REF-03</p></td><td><p>ISO/IEC 25010, model kualitas produk perangkat lunak</p></td><td><p>Karakteristik functional suitability dan usability</p></td></tr><tr><td><p>REF-04</p></td><td><p>ISO/IEC/IEEE 29148:2018, rekayasa kebutuhan</p></td><td><p>Acuan karakteristik kebutuhan: tunggal, tidak ambigu, dan dapat diverifikasi</p></td></tr><tr><td><p>REF-05</p></td><td><p>Product Requirements Document (PRD) Web Pengelolaan Kos, versi 1.1</p></td><td><p>Dokumen turunan yang merinci fitur dan kriteria penerimaan</p></td></tr><tr><td><p>REF-06</p></td><td><p>Odoo 19.0 Documentation: External JSON-2 API (odoo.com/documentation/19.0)</p></td><td><p>Endpoint, autentikasi API key, dan batas masa berlaku key</p></td></tr></tbody></table>

## **13. Keputusan Kunci**

Lima keputusan berikut menjadi acuan untuk PRD, BPMN, ERD, UML, product backlog, dan dokumen proyek lainnya.

1. Istilah baku: peran = Pemilik, Pengelola, Penghuni, Vendor; status kamar = terisi / kosong; status tagihan = belum dibayar / menunggu verifikasi / lunas; status tiket = diajukan / ditugaskan / dikerjakan / selesai.
2. Web adalah sistem inti operasional dan Odoo Community 19 adalah back-office untuk tiga modul. Integrasi memakai JSON-2 API dengan API key yang dirotasi paling lambat tiga bulan sekali, setiap transaksi membawa referensi unik, dan hanya tiga jenis transaksi yang dikirim ke ERP.
3. Pengelola menerbitkan tagihan per periode untuk semua kontrak aktif, dan tagihan baru lunas setelah pengelola memverifikasi bukti bayar. Tunggakan dihitung sesuai ATB-05. Tidak ada payment gateway.
4. Setiap keluhan menjadi tiket bernomor unik. Vendor hanya mengakses permintaan kerja yang ditugaskan kepadanya, dan biaya perbaikan dikirim ke ERP setelah tiket selesai.
5. Hak akses berbasis peran dirancang sejak tahap basis data. Identitas dan kontrak penghuni hanya untuk pemilik, pengelola, dan penghuni yang bersangkutan, dan data uji dianonimkan.