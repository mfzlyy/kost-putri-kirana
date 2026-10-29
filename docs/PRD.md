# **Product Requirements Document (PRD)**

Web Pengelolaan Kos Terintegrasi ERP — Kost Putri Kirana

Muhammad Fazly Radithya

Nadia Miranda

Kirana Amelia Maharani

Versi 1.1 · 29 Oktober 2026

## **Kontrol Dokumen**

<table><thead><tr><th><p>Versi</p></th><th><p>Tanggal</p></th><th><p>Penulis</p></th><th><p>Ringkasan perubahan</p></th></tr></thead><tbody><tr><td><p>1.1</p></td><td><p>29 Oktober 2026</p></td><td><p>Tim Nasi Goreng Adul Enak</p></td><td><p>Penyelarasan dengan BRD versi 1.2: aturan tunggakan (F-15), integrasi Odoo Community 19 melalui JSON-2 API, referensi unik transaksi dan rotasi API key, kandidat fitur yang digeser dari Sprint 3, register isu bertanggal, dan activity diagram final</p></td></tr><tr><td><p>1.0</p></td><td><p>23 Oktober 2026</p></td><td><p>Tim Nasi Goreng Adul Enak</p></td><td><p>Draf awal, diturunkan dari BRD versi 1.1</p></td></tr></tbody></table>

**Persetujuan**

<table><thead><tr><th><p>Nama</p></th><th><p>Peran</p></th><th><p>Keputusan</p></th><th><p>Tanggal</p></th></tr></thead><tbody><tr><td><p>Muhammad Fazly Radithya</p></td><td><p>Anggota Tim</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Nadia Miranda</p></td><td><p>Anggota Tim</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Kirana Amelia Maharani</p></td><td><p>Anggota Tim</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Pemilik Kost Putri Kirana</p></td><td><p>Problem owner</p></td><td><p>Menunggu</p></td><td></td></tr><tr><td><p>Dosen Pembimbing</p></td><td><p>Pembimbing Capstone</p></td><td><p>Menunggu</p></td><td></td></tr></tbody></table>

## **1. Ringkasan Produk**

Web pengelolaan kos adalah sistem operasional harian Kost Putri Kirana. Pengelola mencatat kamar, penghuni, dan kontrak, lalu menerbitkan tagihan dan memverifikasi pembayaran. Penghuni melihat tagihan, mengunggah bukti bayar, dan melapor keluhan. Vendor menerima permintaan kerja dan mencatat biaya perbaikan, sedangkan pemilik memantau hunian dan tunggakan. Transaksi keuangan diteruskan ke ERP sebagai back-office.

<table><thead><tr><th><p>Aspek</p></th><th><p>Keterangan</p></th></tr></thead><tbody><tr><td><p>Dokumen induk</p></td><td><p>BRD Web Pengelolaan Kos Terintegrasi ERP, versi 1.2 (REF-01)</p></td></tr><tr><td><p>Status dokumen</p></td><td><p>Draf untuk validasi problem owner</p></td></tr><tr><td><p>Rilis</p></td><td><p>Rilis 1, selesai pada akhir Sprint 4 (minggu 14)</p></td></tr><tr><td><p>Platform</p></td><td><p>Aplikasi web yang diakses melalui browser (Laravel atau Node.js dengan PostgreSQL, ISU-09); Odoo Community 19 sebagai back-office melalui JSON-2 API</p></td></tr><tr><td><p>Pengguna</p></td><td><p>Pemilik, Pengelola, Penghuni, dan Vendor</p></td></tr><tr><td><p>Jumlah fitur</p></td><td><p>29 fitur: 25 Must, 3 Should, 1 Could</p></td></tr></tbody></table>

**Konvensi dokumen**

- Setiap fitur memiliki kode F-xx yang dirujuk di product backlog, desain antarmuka, dan skenario uji.
- Kode BR, NFR, ATB, TB, ASM, DEP, dan ISU merujuk BRD versi 1.1. Kode berakhiran P (misalnya ASM-P01) hanya berlaku di PRD ini.
- Prioritas memakai MoSCoW. Must: wajib untuk mencapai indikator keberhasilan (TB). Should: bernilai tinggi tetapi tidak menentukan indikator. Could: dikerjakan bila kapasitas tersisa. Won't: tidak dikerjakan pada rilis ini.
- Kriteria penerimaan ditulis agar dapat diverifikasi. Skenario yang memiliki beberapa kemungkinan hasil memakai format Diberikan–Ketika–Maka.

## **2. Masalah & Tujuan Produk**

Operasional kos saat ini bergantung pada Google Spreadsheet dan WhatsApp. Akibatnya tunggakan terlambat diketahui, keluhan tidak memiliki nomor dan status, dan laporan keuangan disusun manual. Produk ini menjawab tiga akar masalah pada BRD Bagian 2.

<table><thead><tr><th><p>Kode</p></th><th><p>Masalah</p></th><th><p>Jawaban produk</p></th></tr></thead><tbody><tr><td><p>AM-01</p></td><td><p>Penagihan dan pemantauan tunggakan belum terhubung dengan kontrak dan pembayaran</p></td><td><p>Tagihan terbit dari kontrak (F-10), bukti bayar diverifikasi di sistem (F-12, F-13), dan tunggakan ditandai otomatis (F-15)</p></td></tr><tr><td><p>AM-02</p></td><td><p>Keluhan fasilitas tidak memiliki alur, status, dan pelacakan yang terhubung dengan vendor</p></td><td><p>Tiket bernomor (F-16), penugasan vendor (F-17), status yang dapat dipantau (F-18), dan biaya tercatat (F-20)</p></td></tr><tr><td><p>AM-03</p></td><td><p>Data hunian, kontrak, dan keuangan tersebar, termasuk data pribadi penghuni</p></td><td><p>Data terpusat (F-05 s.d. F-09), hak akses per peran (F-03), dashboard pemilik (F-21), dan pencatatan di ERP (F-22 s.d. F-28)</p></td></tr></tbody></table>

**Tujuan produk**

- Pemilik mengetahui kondisi hunian dan tunggakan kapan saja tanpa menunggu rekap pengelola.
- Pengelola menyelesaikan penagihan, verifikasi, dan penugasan perbaikan dari satu tempat.
- Penghuni mengetahui tagihan dan perkembangan keluhannya tanpa harus bertanya lewat chat.
- Vendor menerima instruksi kerja yang jelas, dan biaya pekerjaannya tercatat.
- Transaksi pembayaran dan biaya perbaikan tercatat di ERP dengan nilai yang sama seperti di web.

## **3. Pengguna**

Produk dipakai oleh empat peran. Hak akses setiap peran mengikuti matriks hak akses pada BRD Bagian 5.

<table><thead><tr><th><p>Peran</p></th><th><p>Tujuan di produk</p></th><th><p>Kendala saat ini</p></th><th><p>Konteks penggunaan</p></th></tr></thead><tbody><tr><td><p>Pemilik</p></td><td><p>Memantau hunian, tunggakan, tiket, dan laporan keuangan</p></td><td><p>Status pembayaran hanya diketahui dari catatan dan chat pengelola</p></td><td><p>Sesekali, untuk memantau dan memutuskan</p></td></tr><tr><td><p>Pengelola</p></td><td><p>Mengelola data hunian, menagih, memverifikasi, dan menugaskan vendor</p></td><td><p>Rekap manual memakan waktu dan rawan salah catat</p></td><td><p>Harian, sebagai pengguna paling aktif</p></td></tr><tr><td><p>Penghuni</p></td><td><p>Melihat tagihan, membayar, dan melapor keluhan</p></td><td><p>Tagihan dan status keluhan tidak jelas; bukti bayar tidak terdokumentasi</p></td><td><p>Bulanan saat membayar dan saat ada keluhan; terutama lewat ponsel (ASM-07)</p></td></tr><tr><td><p>Vendor</p></td><td><p>Menerima permintaan kerja dan mencatat biaya</p></td><td><p>Instruksi lewat chat tidak terstruktur; biaya tidak terekap</p></td><td><p>Saat ada penugasan; terutama lewat ponsel</p></td></tr></tbody></table>

## **4. Fitur & Prioritas**

Tabel berikut merangkum seluruh fitur Rilis 1. Rincian cerita pengguna dan kriteria penerimaan ada di Bagian 5. Kebutuhan BR-27 (dokumentasi per sprint) dipenuhi melalui Definition of Done, bukan sebagai fitur. Sprint 3 memuat 12 fitur, sehingga fitur bertanda \* (F-24 dan F-28) menjadi kandidat pertama yang digeser ke pengembangan lanjutan bila kapasitas tidak mencukupi.

<table><thead><tr><th><p>Kode</p></th><th><p>Modul</p></th><th><p>Fitur</p></th><th><p>Prioritas</p></th><th><p>Dasar</p></th><th><p>Target</p></th></tr></thead><tbody><tr><td><p>F-01</p></td><td><p>Akses &amp; Pengaturan</p></td><td><p>Login dan logout</p></td><td><p>Must</p></td><td><p>BR-01</p></td><td><p>Sprint 1</p></td></tr><tr><td><p>F-02</p></td><td><p>Akses &amp; Pengaturan</p></td><td><p>Kelola akun pengguna</p></td><td><p>Must</p></td><td><p>BR-01, ASM-05</p></td><td><p>Sprint 1</p></td></tr><tr><td><p>F-03</p></td><td><p>Akses &amp; Pengaturan</p></td><td><p>Hak akses per peran</p></td><td><p>Must</p></td><td><p>BR-01, NFR-01, NFR-02</p></td><td><p>Sprint 1</p></td></tr><tr><td><p>F-04</p></td><td><p>Akses &amp; Pengaturan</p></td><td><p>Pengaturan operasional</p></td><td><p>Must</p></td><td><p>ATB-05, ISU-01, ISU-02</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-05</p></td><td><p>Hunian</p></td><td><p>Data kamar</p></td><td><p>Must</p></td><td><p>BR-03, ATB-01</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-06</p></td><td><p>Hunian</p></td><td><p>Data penghuni</p></td><td><p>Must</p></td><td><p>BR-02, NFR-02, ASM-06</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-07</p></td><td><p>Hunian</p></td><td><p>Kontrak penghuni</p></td><td><p>Must</p></td><td><p>BR-04, ATB-01, ATB-02, ASM-04</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-08</p></td><td><p>Hunian</p></td><td><p>Peringatan kontrak berakhir</p></td><td><p>Should</p></td><td><p>BR-05, ISU-02</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-09</p></td><td><p>Hunian</p></td><td><p>Migrasi data awal</p></td><td><p>Must</p></td><td><p>BR-26, NFR-08, DEP-02</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-10</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Penerbitan tagihan periodik</p></td><td><p>Must</p></td><td><p>BR-06, ATB-02, ASM-03</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-11</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Tagihan saya</p></td><td><p>Must</p></td><td><p>BR-07, ATB-10</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-12</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Unggah bukti bayar</p></td><td><p>Must</p></td><td><p>BR-08, ATB-03</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-13</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Verifikasi pembayaran</p></td><td><p>Must</p></td><td><p>BR-09, ATB-03, ATB-04</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-14</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Riwayat transaksi</p></td><td><p>Must</p></td><td><p>BR-10, ATB-10, ATB-11</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-15</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Penandaan tunggakan</p></td><td><p>Must</p></td><td><p>BR-11, ATB-05</p></td><td><p>Sprint 2</p></td></tr><tr><td><p>F-16</p></td><td><p>Keluhan Fasilitas</p></td><td><p>Ajukan keluhan</p></td><td><p>Must</p></td><td><p>BR-12, ATB-07, DEP-P01</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-17</p></td><td><p>Keluhan Fasilitas</p></td><td><p>Tinjau dan tugaskan vendor</p></td><td><p>Must</p></td><td><p>BR-13, ATB-07</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-18</p></td><td><p>Keluhan Fasilitas</p></td><td><p>Pantau status tiket</p></td><td><p>Must</p></td><td><p>BR-14, ATB-10</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-19</p></td><td><p>Layanan Vendor</p></td><td><p>Permintaan kerja</p></td><td><p>Must</p></td><td><p>BR-15, ATB-08</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-20</p></td><td><p>Layanan Vendor</p></td><td><p>Perbarui status dan isi biaya</p></td><td><p>Must</p></td><td><p>BR-16, BR-17, ATB-07, ATB-09</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-21</p></td><td><p>Pemantauan Pemilik</p></td><td><p>Dashboard hunian dan tunggakan</p></td><td><p>Must</p></td><td><p>BR-18, ISU-05</p></td><td><p>Sprint 4</p></td></tr><tr><td><p>F-22</p></td><td><p>Integrasi ERP</p></td><td><p>Kirim pembayaran ke ERP</p></td><td><p>Must</p></td><td><p>BR-19, ATB-06, NFR-03</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-23</p></td><td><p>Integrasi ERP</p></td><td><p>Kirim biaya perbaikan ke ERP</p></td><td><p>Must</p></td><td><p>BR-20, BR-24, ATB-09</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-24</p></td><td><p>Integrasi ERP</p></td><td><p>Kirim data pemeliharaan ke ERP</p></td><td><p>Should</p></td><td><p>BR-21, BR-25</p></td><td><p>Sprint 3*</p></td></tr><tr><td><p>F-25</p></td><td><p>Integrasi ERP</p></td><td><p>Status sinkronisasi dan kirim ulang</p></td><td><p>Must</p></td><td><p>BR-22, NFR-04</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-26</p></td><td><p>Konfigurasi ERP</p></td><td><p>Konfigurasi modul Keuangan dan Akuntansi</p></td><td><p>Must</p></td><td><p>BR-23, ISU-04</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-27</p></td><td><p>Konfigurasi ERP</p></td><td><p>Konfigurasi modul Pengadaan dan Hutang Vendor</p></td><td><p>Must</p></td><td><p>BR-24</p></td><td><p>Sprint 3</p></td></tr><tr><td><p>F-28</p></td><td><p>Konfigurasi ERP</p></td><td><p>Konfigurasi modul Aset dan Pemeliharaan</p></td><td><p>Should</p></td><td><p>BR-25, DEP-P01</p></td><td><p>Sprint 3*</p></td></tr><tr><td><p>F-29</p></td><td><p>Tagihan &amp; Pembayaran</p></td><td><p>Pengingat tagihan</p></td><td><p>Could</p></td><td><p>ISU-03</p></td><td><p>Kondisional</p></td></tr></tbody></table>

## **5. Kebutuhan Fungsional**

Setiap fitur memuat cerita pengguna, prioritas, rujukan BRD, target sprint, dan kriteria penerimaan. Kriteria yang berlaku untuk semua halaman, seperti kondisi memuat, kosong, dan error, diatur di Bagian 9.

### **5.1 Akses & Pengaturan**

**F-01 · Login dan logout**

*Sebagai pengguna terdaftar, saya ingin masuk dengan akun saya, agar dapat memakai fitur sesuai peran saya.*

**Prioritas:** Must · **Dasar:** BR-01 · **Target:** Sprint 1

1. Pengguna masuk dengan email dan password (ASM-P03).
2. Diberikan email atau password salah, ketika pengguna menekan Masuk, maka sistem menampilkan pesan “Email atau password salah” tanpa menyebutkan bagian mana yang salah.
3. Setelah berhasil masuk, pengguna diarahkan ke halaman utama perannya (Bagian 6).
4. Akun berstatus nonaktif tidak dapat masuk.
5. Semua halaman selain Login mewajibkan login; akses tanpa login diarahkan ke halaman Login.
6. Logout mengakhiri sesi, sehingga halaman yang membutuhkan login tidak dapat dibuka lagi tanpa masuk ulang.
7. Pengguna dapat mengganti password miliknya dengan memasukkan password lama dan password baru.

**F-02 · Kelola akun pengguna**

*Sebagai pengelola, saya ingin membuat dan menonaktifkan akun penghuni dan vendor, agar hanya pihak yang berhak yang dapat masuk.*

**Prioritas:** Must · **Dasar:** BR-01, ASM-05 · **Target:** Sprint 1

1. Pengelola membuat akun dengan nama, email, peran (Penghuni atau Vendor), dan password awal.
2. Email wajib unik; email yang sudah terdaftar ditolak dengan pesan yang jelas.
3. Pengelola dapat menonaktifkan dan mengaktifkan kembali akun. Akun tidak dihapus agar riwayat tetap utuh (ATB-11).
4. Akun Pemilik dan Pengelola disiapkan sebagai data awal sistem, tidak melalui halaman ini.
5. Daftar akun dapat difilter berdasarkan peran dan status.

**F-03 · Hak akses per peran**

*Sebagai pemilik, saya ingin setiap peran hanya melihat data yang menjadi haknya, agar data pribadi penghuni terlindungi.*

**Prioritas:** Must · **Dasar:** BR-01, NFR-01, NFR-02 · **Target:** Sprint 1

1. Menu yang tampil untuk setiap peran mengikuti matriks hak akses BRD Bagian 5.
2. Diberikan pengguna membuka alamat halaman di luar haknya secara langsung, ketika halaman diminta, maka server menolak dengan pesan “Anda tidak memiliki akses ke halaman ini” dan tidak menampilkan data apa pun.
3. Penghuni yang mengganti nomor data pada alamat halaman tidak dapat melihat kontrak, tagihan, atau tiket penghuni lain.
4. Vendor tidak dapat membuka identitas, kontrak, maupun tagihan penghuni.
5. Seluruh skenario uji hak akses per peran lulus (TB-04).

**F-04 · Pengaturan operasional**

*Sebagai pengelola, saya ingin mengatur tanggal jatuh tempo dan batas peringatan kontrak, agar aturan kos dapat diubah tanpa mengubah kode.*

**Prioritas:** Must · **Dasar:** ATB-05, ISU-01, ISU-02 · **Target:** Sprint 2

1. Pengaturan memuat tanggal jatuh tempo tagihan setiap periode (tanggal 1–28) dan batas hari peringatan kontrak berakhir.
2. Tagihan tidak dapat diterbitkan selama tanggal jatuh tempo belum diisi.
3. Perubahan pengaturan hanya berlaku untuk tagihan yang diterbitkan setelah perubahan; tagihan yang sudah terbit tidak berubah.
4. Pengelola dapat mengubah pengaturan; pemilik dapat melihatnya.

### **5.2 Hunian**

**F-05 · Data kamar**

*Sebagai pengelola, saya ingin mencatat kamar beserta tarifnya, agar status hunian setiap kamar terlihat.*

**Prioritas:** Must · **Dasar:** BR-03, ATB-01 · **Target:** Sprint 2

1. Data kamar memuat nomor kamar (unik), tarif sewa per bulan, dan keterangan (opsional).
2. Status kamar dihitung otomatis: terisi bila memiliki kontrak aktif, kosong bila tidak (ATB-01). Status tidak dapat diubah manual.
3. Perubahan tarif kamar tidak mengubah tarif pada kontrak yang sedang berjalan.
4. Kamar yang memiliki kontrak aktif tidak dapat dihapus.
5. Daftar kamar menampilkan nomor, tarif, status, dan nama penghuni aktif, serta dapat difilter berdasarkan status.
6. Pemilik dapat melihat daftar kamar tanpa dapat mengubahnya.

**F-06 · Data penghuni**

*Sebagai pengelola, saya ingin mencatat data penghuni di satu tempat, agar data tidak lagi tersebar di chat dan Spreadsheet.*

**Prioritas:** Must · **Dasar:** BR-02, NFR-02, ASM-06 · **Target:** Sprint 2

1. Data penghuni memuat nama lengkap, nomor identitas, nomor telepon, dan email akun. Hanya data yang dibutuhkan untuk kontrak dan penagihan yang dikumpulkan.
2. Nama lengkap, nomor identitas, dan nomor telepon wajib diisi; field yang kosong atau salah format ditandai dengan pesan per field.
3. Setiap penghuni terhubung ke satu akun berperan Penghuni (F-02).
4. Nomor identitas hanya tampil bagi pemilik, pengelola, dan penghuni yang bersangkutan.
5. Penghuni dapat melihat data dirinya di halaman Profil Saya; perubahan data dilakukan oleh pengelola.
6. Penghuni yang sudah keluar tetap tersimpan dengan status nonaktif beserta riwayatnya (ATB-11).

**F-07 · Kontrak penghuni**

*Sebagai pengelola, saya ingin mencatat kontrak setiap penghuni, agar tagihan dan masa sewa bersumber dari data yang sama.*

**Prioritas:** Must · **Dasar:** BR-04, ATB-01, ATB-02, ASM-04 · **Target:** Sprint 2

1. Kontrak memuat penghuni, kamar, tanggal mulai, tanggal berakhir, dan tarif per bulan. Tarif terisi dari tarif kamar dan dapat disesuaikan sebelum disimpan.
2. Tanggal berakhir harus setelah tanggal mulai.
3. Diberikan kamar sudah memiliki kontrak aktif pada rentang tanggal yang sama, ketika pengelola menyimpan kontrak baru untuk kamar itu, maka sistem menolak dengan pesan bahwa kamar sudah terisi.
4. Satu penghuni hanya memiliki satu kontrak aktif.
5. Kontrak berstatus berakhir setelah tanggal berakhir lewat atau saat pengelola mengakhirinya lebih awal, dan kamar kembali berstatus kosong.
6. Perpanjangan dicatat sebagai kontrak baru agar riwayat kontrak lama tetap utuh (ASM-P04).

**F-08 · Peringatan kontrak berakhir**

*Sebagai pengelola, saya ingin melihat kontrak yang akan berakhir, agar perpanjangan atau pengosongan kamar dapat disiapkan tepat waktu.*

**Prioritas:** Should · **Dasar:** BR-05, ISU-02 · **Target:** Sprint 2

1. Daftar Kontrak Akan Berakhir memuat kontrak aktif yang tanggal berakhirnya masuk batas hari pada pengaturan (F-04).
2. Setiap baris menampilkan penghuni, kamar, tanggal berakhir, dan sisa hari.
3. Daftar tampil bagi pengelola dan pemilik; bila kosong, ditampilkan pesan “Tidak ada kontrak yang akan berakhir”.

**F-09 · Migrasi data awal**

*Sebagai pengelola, saya ingin data dari Google Spreadsheet dipindahkan ke sistem, agar pencatatan tidak dimulai dari nol.*

**Prioritas:** Must · **Dasar:** BR-26, NFR-08, DEP-02 · **Target:** Sprint 2

1. Data yang dipindahkan: kamar, penghuni, kontrak aktif, dan riwayat pembayaran 6 bulan terakhir yang diizinkan pemilik.
2. Data dianonimkan sebelum dimuat: nama dan nomor identitas diganti, sedangkan nominal dan tanggal dipertahankan agar rekap dapat dicocokkan.
3. Setelah migrasi, jumlah kamar, penghuni, dan kontrak serta total pembayaran per periode sama dengan data sumber (selisih 0).
4. Baris sumber yang tidak valid dicatat dalam laporan kesalahan migrasi dan tidak dimuat.

### **5.3 Tagihan & Pembayaran**

**F-10 · Penerbitan tagihan periodik**

*Sebagai pengelola, saya ingin menerbitkan tagihan semua kontrak aktif untuk satu periode sekaligus, agar tidak perlu menagih satu per satu lewat chat.*

**Prioritas:** Must · **Dasar:** BR-06, ATB-02, ASM-03 · **Target:** Sprint 2

1. Pengelola memilih periode (bulan dan tahun), lalu sistem membuat satu tagihan untuk setiap kontrak yang aktif pada periode tersebut.
2. Nominal tagihan sama dengan tarif pada kontrak, dan jatuh tempo mengikuti pengaturan (F-04).
3. Diberikan tagihan periode tersebut sudah ada untuk sebuah kontrak, ketika pengelola menerbitkan ulang, maka sistem tidak membuat tagihan ganda dan menampilkan jumlah tagihan yang dilewati.
4. Kontrak yang sudah berakhir tidak mendapat tagihan baru.
5. Perhitungan tagihan untuk kontrak yang tidak penuh satu periode mengikuti keputusan ISU-P01.
6. Tagihan baru berstatus belum dibayar dan langsung tampil di halaman Tagihan Saya milik penghuni.
7. Bila tidak ada kontrak aktif pada periode itu, sistem menampilkan pesan dan tidak membuat tagihan.

**F-11 · Tagihan saya**

*Sebagai penghuni, saya ingin melihat tagihan saya beserta jatuh temponya, agar saya tahu berapa dan kapan harus membayar.*

**Prioritas:** Must · **Dasar:** BR-07, ATB-10 · **Target:** Sprint 2

1. Halaman menampilkan tagihan milik penghuni yang sedang login: periode, nominal, jatuh tempo, dan status.
2. Tagihan yang ditolak menampilkan alasan penolakan dari pengelola.
3. Tagihan yang termasuk tunggakan diberi penanda yang jelas.
4. Bila belum ada tagihan, ditampilkan pesan “Belum ada tagihan”.

**F-12 · Unggah bukti bayar**

*Sebagai penghuni, saya ingin mengunggah bukti pembayaran, agar pembayaran saya tercatat tanpa harus dikirim lewat chat.*

**Prioritas:** Must · **Dasar:** BR-08, ATB-03 · **Target:** Sprint 2

1. Bukti hanya dapat diunggah untuk tagihan milik penghuni tersebut yang berstatus belum dibayar.
2. Berkas berformat JPG, PNG, atau PDF dengan ukuran maksimal 5 MB (ASM-P01). Berkas lain ditolak dengan pesan yang menyebutkan format dan batas ukuran.
3. Penghuni mengisi tanggal pembayaran. Satu bukti melunasi satu tagihan penuh (ASM-P02).
4. Setelah unggahan berhasil, status tagihan menjadi menunggu verifikasi dan tagihan tidak dapat diberi bukti lain sampai diverifikasi.
5. Unggahan yang gagal tidak mengubah status tagihan, dan penghuni dapat mencoba lagi.

**F-13 · Verifikasi pembayaran**

*Sebagai pengelola, saya ingin memeriksa dan memutuskan bukti bayar, agar status lunas hanya diberikan pada pembayaran yang benar.*

**Prioritas:** Must · **Dasar:** BR-09, ATB-03, ATB-04 · **Target:** Sprint 2

1. Daftar verifikasi menampilkan tagihan berstatus menunggu verifikasi, diurutkan dari unggahan terlama.
2. Pengelola dapat membuka berkas bukti, lalu memilih Terima atau Tolak.
3. Diberikan bukti sesuai, ketika pengelola memilih Terima, maka tagihan menjadi lunas, pembayaran masuk riwayat transaksi, dan transaksi diantrekan untuk dikirim ke ERP (F-22).
4. Diberikan bukti tidak sesuai, ketika pengelola memilih Tolak, maka pengelola wajib mengisi alasan, tagihan kembali berstatus belum dibayar, dan penghuni dapat mengunggah bukti baru.
5. Setiap keputusan mencatat nama pengelola dan waktu verifikasi.

**F-14 · Riwayat transaksi**

*Sebagai penghuni, saya ingin melihat riwayat pembayaran saya, agar saya memiliki catatan pembayaran yang terdokumentasi.*

**Prioritas:** Must · **Dasar:** BR-10, ATB-10, ATB-11 · **Target:** Sprint 2

1. Riwayat memuat pembayaran berstatus lunas: periode, nominal, tanggal bayar, dan tanggal verifikasi.
2. Penghuni hanya melihat riwayat miliknya. Pengelola dan pemilik dapat melihat riwayat per penghuni dan memfilternya per periode.
3. Bagi pengelola dan pemilik, setiap transaksi menampilkan status sinkronisasi ERP (F-25).
4. Riwayat penghuni yang sudah keluar tetap dapat dilihat pengelola dan pemilik.

**F-15 · Penandaan tunggakan**

*Sebagai pemilik, saya ingin tagihan yang lewat jatuh tempo ditandai otomatis, agar tunggakan diketahui tanpa menunggu rekap.*

**Prioritas:** Must · **Dasar:** BR-11, ATB-05 · **Target:** Sprint 2

1. Tagihan yang belum lunas ditandai sebagai tunggakan mulai hari setelah tanggal jatuh tempo (ATB-05).
2. Diberikan bukti diunggah paling lambat pada tanggal jatuh tempo dan masih menunggu verifikasi, ketika jatuh tempo lewat, maka tagihan tidak ditandai sebagai tunggakan.
3. Diberikan bukti diunggah setelah tanggal jatuh tempo, ketika tagihan menunggu verifikasi, maka penanda tunggakan tetap tampil sampai bukti diterima.
4. Diberikan bukti yang diunggah tepat waktu ditolak, ketika penolakan disimpan setelah jatuh tempo, maka tagihan ditandai sebagai tunggakan dengan jumlah hari dihitung sejak hari setelah jatuh tempo.
5. Daftar tunggakan menampilkan penghuni, kamar, periode, nominal, dan jumlah hari lewat jatuh tempo.
6. Penanda tunggakan hilang setelah tagihan lunas.
7. Jumlah dan total nominal tunggakan sama dengan perhitungan manual pada data uji (TB-03).

### **5.4 Keluhan Fasilitas**

**F-16 · Ajukan keluhan**

*Sebagai penghuni, saya ingin melaporkan kerusakan fasilitas melalui web, agar keluhan saya tercatat dan tidak terlewat.*

**Prioritas:** Must · **Dasar:** BR-12, ATB-07, DEP-P01 · **Target:** Sprint 3

1. Form keluhan memuat lokasi (kamar penghuni atau area bersama), fasilitas yang dipilih dari daftar fasilitas acuan, dan deskripsi. Ketiganya wajib diisi.
2. Setelah dikirim, sistem membuat tiket bernomor unik berurutan (contoh: KLH-0001) dengan status diajukan, lalu menampilkan nomor tiket kepada penghuni.
3. Klik berulang pada tombol Kirim tidak membuat tiket ganda.
4. Tiket baru langsung tampil di daftar tiket pengelola.

**F-17 · Tinjau dan tugaskan vendor**

*Sebagai pengelola, saya ingin menugaskan tiket ke vendor yang tepat, agar perbaikan dikerjakan dengan instruksi yang jelas.*

**Prioritas:** Must · **Dasar:** BR-13, ATB-07 · **Target:** Sprint 3

1. Daftar tiket dapat difilter berdasarkan status dan diurutkan dari yang terlama.
2. Pengelola mengisi jenis kerusakan dan catatan instruksi, lalu memilih satu akun vendor yang aktif.
3. Setelah ditugaskan, status tiket menjadi ditugaskan dan tiket tampil di halaman Permintaan Kerja vendor tersebut.
4. Tiket berstatus diajukan yang belum ditugaskan tampil paling atas sebagai antrean kerja pengelola.

**F-18 · Pantau status tiket**

*Sebagai penghuni, saya ingin melihat perkembangan keluhan saya, agar tidak perlu menanyakannya berulang kali lewat chat.*

**Prioritas:** Must · **Dasar:** BR-14, ATB-10 · **Target:** Sprint 3

1. Penghuni melihat daftar tiket miliknya beserta nomor, fasilitas, status, dan waktu setiap perubahan status.
2. Pengelola dan pemilik melihat seluruh tiket beserta riwayat statusnya.
3. Penghuni tidak dapat melihat tiket yang dilaporkan penghuni lain.
4. Riwayat status mencatat waktu dari diajukan sampai selesai, sehingga durasi penanganan dapat dihitung dan dibandingkan dengan nilai awal 5 hari.

### **5.5 Layanan Vendor**

**F-19 · Permintaan kerja**

*Sebagai vendor, saya ingin melihat pekerjaan yang ditugaskan kepada saya, agar instruksinya jelas tanpa harus lewat chat.*

**Prioritas:** Must · **Dasar:** BR-15, ATB-08 · **Target:** Sprint 3

1. Vendor hanya melihat tiket yang ditugaskan kepadanya: nomor, lokasi, fasilitas, jenis kerusakan, deskripsi, dan catatan instruksi.
2. Identitas, kontak, kontrak, dan tagihan penghuni tidak ditampilkan.
3. Bila belum ada pekerjaan, ditampilkan pesan “Belum ada permintaan kerja”.

**F-20 · Perbarui status dan isi biaya**

*Sebagai vendor, saya ingin memperbarui status pekerjaan dan mencatat biayanya, agar pengelola dan penghuni mengetahui perkembangannya dan biaya tercatat.*

**Prioritas:** Must · **Dasar:** BR-16, BR-17, ATB-07, ATB-09 · **Target:** Sprint 3

1. Vendor mengubah status secara berurutan: ditugaskan, dikerjakan, lalu selesai. Status tidak dapat dilompati atau dimundurkan.
2. Status selesai hanya dapat disimpan bila biaya perbaikan dalam Rupiah (nol atau lebih) sudah diisi.
3. Setelah tiket selesai, biaya dan data pemeliharaan diantrekan untuk dikirim ke ERP (F-23, F-24).
4. Setiap perubahan status langsung tampil di riwayat tiket bagi penghuni, pengelola, dan pemilik.

### **5.6 Pemantauan Pemilik**

**F-21 · Dashboard hunian dan tunggakan**

*Sebagai pemilik, saya ingin melihat ringkasan hunian dan tunggakan di satu halaman, agar keputusan usaha dapat diambil tanpa menunggu laporan pengelola.*

**Prioritas:** Must · **Dasar:** BR-18, ISU-05 · **Target:** Sprint 4

1. Dashboard menampilkan jumlah kamar terisi dan kosong, tingkat hunian (%), jumlah penghuni menunggak, total nominal tunggakan, jumlah tiket per status, dan jumlah kontrak yang akan berakhir.
2. Setiap angka sama dengan data di halaman rinciannya (selisih 0).
3. Setiap angka dapat dibuka untuk melihat daftar rinciannya.
4. Data mencerminkan kondisi terbaru saat halaman dibuka.
5. Akses pengelola ke dashboard mengikuti keputusan ISU-05.

### **5.7 Integrasi & Konfigurasi ERP**

**F-22 · Kirim pembayaran ke ERP**

*Sebagai pemilik, saya ingin setiap pembayaran lunas tercatat di ERP, agar laporan keuangan tidak perlu disusun manual.*

**Prioritas:** Must · **Dasar:** BR-19, ATB-06, NFR-03 · **Target:** Sprint 3

1. Pembayaran yang diterima pengelola dikirim ke modul Keuangan dan Akuntansi (aplikasi Invoicing Odoo) berisi ID penghuni, periode sewa, nominal, tanggal pembayaran, status pembayaran, dan referensi unik transaksi.
2. Hanya tagihan berstatus lunas yang dikirim.
3. Satu pembayaran tercatat satu kali di ERP walaupun dikirim ulang, karena referensi uniknya diperiksa sebelum catatan dibuat.
4. Nominal, tanggal, dan status di ERP sama dengan data di web (TB-02).
5. Bentuk catatan di Invoicing (misalnya faktur pelanggan beserta pembayarannya) diverifikasi di instance pada Sprint 1.

**F-23 · Kirim biaya perbaikan ke ERP**

*Sebagai pemilik, saya ingin biaya perbaikan tercatat di ERP, agar pengeluaran dan kewajiban kepada vendor terekap.*

**Prioritas:** Must · **Dasar:** BR-20, BR-24, ATB-09 · **Target:** Sprint 3

1. Setelah tiket selesai, sistem mengirim jenis kerusakan, fasilitas, biaya, vendor, dan referensi unik transaksi ke modul Pengadaan dan Hutang Vendor, yang dicatat sebagai tagihan vendor (vendor bill) di Odoo.
2. Vendor yang belum ada di ERP ikut tercatat, sehingga riwayat pekerjaan vendor terbentuk.
3. Satu tiket menghasilkan satu catatan biaya di ERP walaupun dikirim ulang.

**F-24 · Kirim data pemeliharaan ke ERP**

*Sebagai pengelola, saya ingin riwayat perbaikan fasilitas tercatat di ERP, agar kondisi setiap fasilitas dapat ditelusuri.*

**Prioritas:** Should · **Dasar:** BR-21, BR-25 · **Target:** Sprint 3 (dapat digeser)

1. Setelah tiket selesai, sistem mengirim data aset (fasilitas), riwayat perbaikan, status pekerjaan, dan referensi unik transaksi ke modul Aset dan Pemeliharaan, yang dicatat sebagai permintaan perawatan pada peralatan (equipment) di aplikasi Maintenance Odoo.
2. Riwayat perawatan fasilitas di ERP bertambah satu catatan untuk setiap tiket yang selesai.

**F-25 · Status sinkronisasi dan kirim ulang**

*Sebagai pengelola, saya ingin melihat transaksi yang gagal tercatat di ERP dan mengirimnya ulang, agar data web dan ERP tetap sama.*

**Prioritas:** Must · **Dasar:** BR-22, NFR-04 · **Target:** Sprint 3

1. Setiap transaksi yang dikirim ke ERP memiliki status sinkronisasi: menunggu, terkirim, atau gagal.
2. Halaman Sinkronisasi ERP menampilkan transaksi gagal beserta referensi unik dan pesan kesalahan.
3. Pengelola dapat mengirim ulang transaksi gagal; transaksi yang berhasil berubah menjadi terkirim.
4. Kegagalan pengiriman tidak membatalkan status lunas atau selesai di web.

**F-26 · Konfigurasi modul Keuangan dan Akuntansi**

*Sebagai pemilik, saya ingin pemasukan dan pengeluaran kos tercatat di ERP, agar laporan keuangan sederhana tersedia setiap bulan.*

**Prioritas:** Must · **Dasar:** BR-23, ISU-04 · **Target:** Sprint 3

1. Aplikasi Invoicing Odoo mencatat pemasukan dari pembayaran sewa dan pengeluaran dari tagihan vendor.
2. Laporan keuangan sederhana memakai laporan Invoicing atau modul OCA account\_financial\_report, sesuai hasil verifikasi ISU-10.
3. Laporan keuangan bulanan (pemasukan, pengeluaran, dan selisih) dapat dibuka oleh pihak yang ditetapkan pada ISU-04 dan ISU-05.
4. Waktu menyusun laporan keuangan bulanan turun minimal 50% dari nilai awal 1 jam (TB-05).
5. Langkah konfigurasi terdokumentasi.

**F-27 · Konfigurasi modul Pengadaan dan Hutang Vendor**

*Sebagai pengelola, saya ingin data vendor dan biayanya tersimpan di ERP, agar pembayaran kepada vendor dapat dipantau.*

**Prioritas:** Must · **Dasar:** BR-24 · **Target:** Sprint 3

1. Aplikasi Purchase Odoo menyimpan data vendor beserta riwayat pekerjaan dan biayanya.
2. Biaya perbaikan dari web tercatat sebagai hutang kepada vendor terkait.
3. Langkah konfigurasi terdokumentasi.

**F-28 · Konfigurasi modul Aset dan Pemeliharaan**

*Sebagai pengelola, saya ingin fasilitas kos tercatat sebagai aset, agar riwayat perawatannya terlihat.*

**Prioritas:** Should · **Dasar:** BR-25, DEP-P01 · **Target:** Sprint 3 (dapat digeser)

1. Aplikasi Maintenance Odoo memuat daftar fasilitas acuan sebagai peralatan (equipment). Penyusutan aset tidak termasuk karena hanya tersedia di Odoo Enterprise.
2. Setiap fasilitas memiliki riwayat perawatan dan status pemeliharaan.
3. Langkah konfigurasi terdokumentasi.

### **5.8 Fitur Opsional**

**F-29 · Pengingat tagihan**

*Sebagai penghuni, saya ingin diingatkan sebelum jatuh tempo, agar tidak terlambat membayar.*

**Prioritas:** Could · **Dasar:** ISU-03 · **Target:** Setelah fitur Must selesai

1. Saluran dan waktu pengingat ditetapkan setelah ISU-03 diputuskan.
2. Fitur hanya dikerjakan bila seluruh fitur Must sudah memenuhi kriteria penerimaan.

## **6. Alur Pengguna & Daftar Halaman**

Dua alur utama produk digambarkan di bawah. Alur lain disusun dari fitur pada Bagian 5.

![](https://cdn-mineru.openxlab.org.cn/result/2026-10-29/4df2a36d-f1dc-410f-b9ae-1cde74248afd/0a700788022c536237aeddbf8460de741355656ac382251a26ebfc79b334a2d2.jpg)

*Gambar 1. Activity diagram tagihan dan pembayaran sewa*

![](https://cdn-mineru.openxlab.org.cn/result/2026-10-29/4df2a36d-f1dc-410f-b9ae-1cde74248afd/40d837b0c6d16baba870f00870ca6b47437019589b09f240c6b08418deb93988.jpg)

*Gambar 2. Activity diagram keluhan fasilitas dan perbaikan*

**Alur A. Penghuni baru masuk**

1. Pengelola membuat akun penghuni (F-02).
2. Pengelola melengkapi data penghuni (F-06).
3. Pengelola mencatat kontrak; kamar otomatis berstatus terisi (F-07, F-05).
4. Penghuni masuk dan melihat profil serta tagihannya (F-01, F-11).

**Alur B. Penagihan bulanan**

1. Pengelola menerbitkan tagihan periode berjalan (F-10).
2. Penghuni melihat tagihan dan mengunggah bukti bayar (F-11, F-12).
3. Pengelola menerima atau menolak bukti (F-13).
4. Pembayaran lunas tercatat di riwayat dan dikirim ke ERP (F-14, F-22, F-25).
5. Tagihan yang belum lunas setelah jatuh tempo ditandai sebagai tunggakan (F-15).

**Alur C. Keluhan fasilitas**

1. Penghuni mengajukan keluhan dan menerima nomor tiket (F-16).
2. Pengelola meninjau tiket dan menugaskan vendor (F-17).
3. Vendor mengerjakan, menyelesaikan, dan mengisi biaya (F-19, F-20).
4. Biaya dan data pemeliharaan dikirim ke ERP (F-23, F-24).
5. Penghuni, pengelola, dan pemilik memantau status sampai selesai (F-18).

**Alur D. Pemantauan pemilik**

1. Pemilik masuk dan langsung melihat dashboard (F-21).
2. Pemilik membuka rincian tunggakan, tiket, atau kontrak yang akan berakhir (F-15, F-18, F-08).
3. Pemilik membuka laporan keuangan bulanan di ERP (F-26).

**Daftar halaman**

Halaman utama setelah login: Pemilik ke Dashboard, Pengelola ke Verifikasi Pembayaran, Penghuni ke Tagihan Saya, dan Vendor ke Permintaan Kerja.

<table><thead><tr><th><p>Halaman</p></th><th><p>Peran</p></th><th><p>Fitur</p></th></tr></thead><tbody><tr><td><p>Login dan Ganti Password</p></td><td><p>Semua</p></td><td><p>F-01</p></td></tr><tr><td><p>Akun Pengguna</p></td><td><p>Pengelola</p></td><td><p>F-02</p></td></tr><tr><td><p>Pengaturan</p></td><td><p>Pengelola (ubah), Pemilik (lihat)</p></td><td><p>F-04</p></td></tr><tr><td><p>Kamar</p></td><td><p>Pengelola (kelola), Pemilik (lihat)</p></td><td><p>F-05</p></td></tr><tr><td><p>Penghuni</p></td><td><p>Pengelola (kelola), Pemilik (lihat)</p></td><td><p>F-06</p></td></tr><tr><td><p>Kontrak</p></td><td><p>Pengelola (kelola), Pemilik (lihat)</p></td><td><p>F-07, F-08</p></td></tr><tr><td><p>Tagihan</p></td><td><p>Pengelola (terbitkan), Pemilik (lihat)</p></td><td><p>F-10, F-15</p></td></tr><tr><td><p>Verifikasi Pembayaran</p></td><td><p>Pengelola</p></td><td><p>F-13</p></td></tr><tr><td><p>Riwayat Transaksi</p></td><td><p>Pengelola, Pemilik, Penghuni (milik sendiri)</p></td><td><p>F-14</p></td></tr><tr><td><p>Tagihan Saya</p></td><td><p>Penghuni</p></td><td><p>F-11, F-12</p></td></tr><tr><td><p>Profil Saya</p></td><td><p>Penghuni</p></td><td><p>F-06</p></td></tr><tr><td><p>Keluhan Saya</p></td><td><p>Penghuni</p></td><td><p>F-16, F-18</p></td></tr><tr><td><p>Tiket Keluhan</p></td><td><p>Pengelola (tugaskan), Pemilik (lihat)</p></td><td><p>F-17, F-18</p></td></tr><tr><td><p>Permintaan Kerja</p></td><td><p>Vendor</p></td><td><p>F-19, F-20</p></td></tr><tr><td><p>Dashboard</p></td><td><p>Pemilik</p></td><td><p>F-21</p></td></tr><tr><td><p>Sinkronisasi ERP</p></td><td><p>Pengelola</p></td><td><p>F-25</p></td></tr></tbody></table>

## **7. Status & Transisi Data**

Perubahan status hanya terjadi melalui kejadian berikut dan dicek di server. Tunggakan adalah penanda yang dihitung dari jatuh tempo sesuai ATB-05, bukan status tersendiri.

<table><thead><tr><th><p>Objek</p></th><th><p>Dari</p></th><th><p>Kejadian</p></th><th><p>Oleh</p></th><th><p>Menjadi</p></th></tr></thead><tbody><tr><td><p>Kamar</p></td><td><p>kosong</p></td><td><p>Kontrak aktif dicatat</p></td><td><p>Pengelola</p></td><td><p>terisi</p></td></tr><tr><td><p>Kamar</p></td><td><p>terisi</p></td><td><p>Kontrak berakhir</p></td><td><p>Sistem atau Pengelola</p></td><td><p>kosong</p></td></tr><tr><td><p>Kontrak</p></td><td><p>aktif</p></td><td><p>Tanggal berakhir lewat</p></td><td><p>Sistem</p></td><td><p>berakhir</p></td></tr><tr><td><p>Kontrak</p></td><td><p>aktif</p></td><td><p>Diakhiri lebih awal</p></td><td><p>Pengelola</p></td><td><p>berakhir</p></td></tr><tr><td><p>Tagihan</p></td><td><p>—</p></td><td><p>Tagihan diterbitkan</p></td><td><p>Pengelola</p></td><td><p>belum dibayar</p></td></tr><tr><td><p>Tagihan</p></td><td><p>belum dibayar</p></td><td><p>Bukti bayar diunggah</p></td><td><p>Penghuni</p></td><td><p>menunggu verifikasi</p></td></tr><tr><td><p>Tagihan</p></td><td><p>menunggu verifikasi</p></td><td><p>Bukti diterima</p></td><td><p>Pengelola</p></td><td><p>lunas</p></td></tr><tr><td><p>Tagihan</p></td><td><p>menunggu verifikasi</p></td><td><p>Bukti ditolak dengan alasan</p></td><td><p>Pengelola</p></td><td><p>belum dibayar</p></td></tr><tr><td><p>Tiket</p></td><td><p>—</p></td><td><p>Keluhan dikirim</p></td><td><p>Penghuni</p></td><td><p>diajukan</p></td></tr><tr><td><p>Tiket</p></td><td><p>diajukan</p></td><td><p>Vendor ditugaskan</p></td><td><p>Pengelola</p></td><td><p>ditugaskan</p></td></tr><tr><td><p>Tiket</p></td><td><p>ditugaskan</p></td><td><p>Pekerjaan dimulai</p></td><td><p>Vendor</p></td><td><p>dikerjakan</p></td></tr><tr><td><p>Tiket</p></td><td><p>dikerjakan</p></td><td><p>Pekerjaan selesai dan biaya diisi</p></td><td><p>Vendor</p></td><td><p>selesai</p></td></tr><tr><td><p>Sinkronisasi</p></td><td><p>—</p></td><td><p>Transaksi siap dikirim</p></td><td><p>Sistem</p></td><td><p>menunggu</p></td></tr><tr><td><p>Sinkronisasi</p></td><td><p>menunggu</p></td><td><p>ERP menerima transaksi</p></td><td><p>Sistem</p></td><td><p>terkirim</p></td></tr><tr><td><p>Sinkronisasi</p></td><td><p>menunggu</p></td><td><p>Referensi unik sudah tercatat di ERP</p></td><td><p>Sistem</p></td><td><p>terkirim</p></td></tr><tr><td><p>Sinkronisasi</p></td><td><p>menunggu</p></td><td><p>ERP menolak atau tidak dapat dihubungi</p></td><td><p>Sistem</p></td><td><p>gagal</p></td></tr><tr><td><p>Sinkronisasi</p></td><td><p>gagal</p></td><td><p>Kirim ulang</p></td><td><p>Pengelola</p></td><td><p>menunggu</p></td></tr></tbody></table>

## **8. Integrasi ERP**

Web mengirim tiga jenis transaksi ke Odoo Community 19 melalui JSON-2 API, yaitu API HTTP berformat JSON dengan permintaan POST ke /json/2/<model>/<method>, lalu menyimpan status balasannya. Proposal menyebut mekanisme ini REST API dalam arti umum. Rincian model, metode, dan format data ditetapkan pada dokumen teknis integrasi.

<table><thead><tr><th><p>Transaksi</p></th><th><p>Pemicu</p></th><th><p>Data yang dikirim</p></th><th><p>Catatan di Odoo (fitur)</p></th></tr></thead><tbody><tr><td><p>Pembayaran sewa</p></td><td><p>Pengelola menerima bukti bayar</p></td><td><p>ID penghuni, periode sewa, nominal, tanggal pembayaran, status pembayaran, referensi unik</p></td><td><p>Invoicing: faktur pelanggan dan pembayaran (F-22)</p></td></tr><tr><td><p>Biaya perbaikan</p></td><td><p>Vendor menyelesaikan tiket</p></td><td><p>Jenis kerusakan, fasilitas, biaya, vendor, referensi unik</p></td><td><p>Purchase dan tagihan vendor (F-23)</p></td></tr><tr><td><p>Pemeliharaan fasilitas</p></td><td><p>Vendor menyelesaikan tiket</p></td><td><p>Data aset, riwayat perbaikan, status pekerjaan, referensi unik</p></td><td><p>Maintenance: permintaan perawatan (F-24)</p></td></tr></tbody></table>

Bentuk catatan di setiap aplikasi Odoo diverifikasi di instance pada Sprint 1 sebelum integrasi dibangun.

**Aturan integrasi**

- Autentikasi memakai API key milik pengguna integrasi khusus di Odoo dengan hak akses minimum. API key dikirim sebagai bearer token, disimpan di server, dan tidak pernah dikirim ke browser pengguna (NFR-03).
- API key dibuat dengan masa berlaku maksimal tiga bulan dan dirotasi sebelum kedaluwarsa. Tanggal kedaluwarsa dicatat agar rotasi tidak terlewat.
- Setiap transaksi membawa referensi unik, misalnya KPK-PAY-000123 untuk pembayaran dan KPK-TKT-0001 untuk tiket. Sebelum membuat catatan, sistem memeriksa referensi tersebut di Odoo; bila sudah ada, catatan baru tidak dibuat dan transaksi ditandai terkirim.
- Pengiriman dijalankan berurutan oleh satu proses, karena setiap panggilan JSON-2 API berjalan dalam transaksi basis data yang terpisah.
- Kegagalan ERP tidak membatalkan proses di web; transaksi ditandai gagal dan dapat dikirim ulang (F-25).
- Data yang dikirim dibatasi pada kolom di tabel di atas. Identitas penghuni selain ID tidak dikirim ke ERP.
- Endpoint XML-RPC dan JSON-RPC tidak dipakai karena dijadwalkan dihapus pada Odoo 20.

## **9. Kebutuhan Non-Fungsional & Perilaku Antarmuka**

Kebutuhan non-fungsional BRD diterjemahkan menjadi spesifikasi produk berikut.

<table><thead><tr><th><p>Kode</p></th><th><p>Kategori</p></th><th><p>Spesifikasi di produk</p></th></tr></thead><tbody><tr><td><p>NFR-01</p></td><td><p>Keamanan akses</p></td><td><p>Hak akses dicek di server pada setiap permintaan. Password disimpan dalam bentuk hash, dan sesi berakhir saat logout.</p></td></tr><tr><td><p>NFR-02</p></td><td><p>Pelindungan data pribadi</p></td><td><p>Nomor identitas hanya tampil bagi pemilik, pengelola, dan penghuni yang bersangkutan. Vendor hanya melihat lokasi dan fasilitas. Data yang dikumpulkan dibatasi pada kebutuhan kontrak dan penagihan.</p></td></tr><tr><td><p>NFR-03</p></td><td><p>Keamanan integrasi</p></td><td><p>API key pengguna integrasi Odoo disimpan di server, dikirim sebagai bearer token pada setiap permintaan, dan dirotasi paling lambat setiap tiga bulan.</p></td></tr><tr><td><p>NFR-04</p></td><td><p>Konsistensi web–ERP</p></td><td><p>Status sinkronisasi dan kirim ulang (F-25); pengiriman idempoten melalui referensi unik transaksi.</p></td></tr><tr><td><p>NFR-05</p></td><td><p>Integritas data</p></td><td><p>Skema basis data ternormalisasi sampai bentuk normal ketiga, dengan foreign key dan integritas referensial.</p></td></tr><tr><td><p>NFR-06</p></td><td><p>Kegunaan</p></td><td><p>Seluruh halaman dapat dipakai pada layar ponsel selebar 360 piksel tanpa gulir horizontal. Skor SUS rata-rata minimal 68.</p></td></tr><tr><td><p>NFR-07</p></td><td><p>Kualitas</p></td><td><p>Evaluasi functional suitability dan usability mengacu pada ISO/IEC 25010.</p></td></tr><tr><td><p>NFR-08</p></td><td><p>Etika data uji</p></td><td><p>Data migrasi dan data uji dianonimkan (F-09).</p></td></tr><tr><td><p>NFR-09</p></td><td><p>Dokumentasi</p></td><td><p>Setiap fitur yang selesai disertai skenario uji dan pembaruan dokumentasi sprint.</p></td></tr></tbody></table>

**Perilaku antarmuka umum**

- Saat data dimuat, halaman menampilkan indikator memuat.
- Daftar yang kosong menampilkan pesan yang menjelaskan keadaan dan tindakan berikutnya bila ada.
- Tombol simpan dinonaktifkan selama proses berjalan agar tidak terjadi kiriman ganda.
- Pesan error ditulis dalam Bahasa Indonesia, menunjuk field yang bermasalah, dan isian lain tidak hilang saat penyimpanan gagal.
- Tindakan yang mengubah status (terima, tolak, tugaskan, selesai, akhiri kontrak) meminta konfirmasi sebelum disimpan.
- Nominal ditampilkan dalam format Rupiah (contoh: Rp1.500.000) dan tanggal dalam format 23 Oktober 2026.

## **10. Kriteria Sukses & Penerimaan Rilis**

Kriteria sukses produk mengikuti indikator keberhasilan BRD Bagian 3.

<table><thead><tr><th><p>Kode</p></th><th><p>Target</p></th><th><p>Fitur yang diukur</p></th></tr></thead><tbody><tr><td><p>TB-01</p></td><td><p>100% skenario kritis lulus dan minimal 90% seluruh skenario lulus</p></td><td><p>Seluruh fitur Must</p></td></tr><tr><td><p>TB-02</p></td><td><p>100% dari minimal 10 transaksi uji tercatat sama di ERP</p></td><td><p>F-22, F-23, F-25</p></td></tr><tr><td><p>TB-03</p></td><td><p>Selisih 0 pada rekap tagihan, pembayaran, dan tunggakan</p></td><td><p>F-09, F-10, F-14, F-15, F-21</p></td></tr><tr><td><p>TB-04</p></td><td><p>100% skenario uji hak akses lulus</p></td><td><p>F-03</p></td></tr><tr><td><p>TB-05</p></td><td><p>Waktu rekap pembayaran dan laporan bulanan turun minimal 50%</p></td><td><p>F-14, F-26</p></td></tr><tr><td><p>TB-06</p></td><td><p>Skor SUS rata-rata minimal 68</p></td><td><p>Seluruh halaman</p></td></tr><tr><td><p>TB-07</p></td><td><p>Persentase skor TAM minimal 80%</p></td><td><p>Seluruh halaman</p></td></tr><tr><td><p>TB-08</p></td><td><p>100% item backlog berprioritas tinggi diterima pemilik</p></td><td><p>Seluruh fitur Must</p></td></tr></tbody></table>

**Rilis 1 dinyatakan siap bila**

- Seluruh fitur Must memenuhi kriteria penerimaannya.
- Fitur Should dan Could yang belum selesai, termasuk F-24 dan F-28 bila digeser, tercatat di backlog pengembangan lanjutan.
- Seluruh indikator TB-01 sampai TB-08 telah diukur pada Sprint 4.
- Berita acara validasi ditandatangani pemilik.

## **11. Asumsi, Dependensi & Isu Terbuka**

Asumsi, dependensi, dan isu terbuka BRD (ASM-01 s.d. ASM-07, DEP-01 s.d. DEP-05, ISU-01 s.d. ISU-10) tetap berlaku. Tabel berikut memuat tambahan di tingkat produk dan dampaknya pada fitur.

**Asumsi dan dependensi produk**

<table><thead><tr><th><p>Kode</p></th><th><p>Isi</p></th><th><p>Fitur terdampak</p></th></tr></thead><tbody><tr><td><p>ASM-P01</p></td><td><p>Bukti bayar berupa berkas JPG, PNG, atau PDF dengan ukuran maksimal 5 MB</p></td><td><p>F-12</p></td></tr><tr><td><p>ASM-P02</p></td><td><p>Satu pembayaran melunasi satu tagihan penuh; pembayaran sebagian tidak didukung</p></td><td><p>F-12, F-13</p></td></tr><tr><td><p>ASM-P03</p></td><td><p>Pengguna masuk dengan email dan password</p></td><td><p>F-01, F-02</p></td></tr><tr><td><p>ASM-P04</p></td><td><p>Perpanjangan kontrak dicatat sebagai kontrak baru</p></td><td><p>F-07</p></td></tr><tr><td><p>DEP-P01</p></td><td><p>Daftar fasilitas acuan (nama dan lokasi) disiapkan bersama pengelola pada Sprint 3 dan menjadi data aset di ERP</p></td><td><p>F-16, F-24, F-28</p></td></tr></tbody></table>

**Isu terbuka**

Tanggal target sama dengan register isu BRD versi 1.2.

<table><thead><tr><th><p>Kode</p></th><th><p>Isu</p></th><th><p>Penanggung jawab</p></th><th><p>Target</p></th><th><p>Status</p></th><th><p>Fitur terdampak</p></th></tr></thead><tbody><tr><td><p>ISU-01</p></td><td><p>Tanggal jatuh tempo dan ketentuan denda keterlambatan</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-04, F-15</p></td></tr><tr><td><p>ISU-02</p></td><td><p>Batas hari peringatan kontrak berakhir</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-04, F-08</p></td></tr><tr><td><p>ISU-03</p></td><td><p>Kebutuhan pengingat tagihan</p></td><td><p>Pemilik, Tim</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-29</p></td></tr><tr><td><p>ISU-04</p></td><td><p>Pihak yang mengoperasikan ERP</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-26, F-27, F-28</p></td></tr><tr><td><p>ISU-05</p></td><td><p>Akses pengelola ke dashboard dan laporan ERP</p></td><td><p>Pemilik</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-21, F-26</p></td></tr><tr><td><p>ISU-06</p></td><td><p>Platform ERP</p></td><td><p>Tim</p></td><td><p>Pra-sprint</p></td><td><p>Ditutup: Odoo Community 19</p></td><td><p>F-22 s.d. F-28</p></td></tr><tr><td><p>ISU-09</p></td><td><p>Teknologi web: Laravel atau Node.js, dengan PostgreSQL</p></td><td><p>Tim</p></td><td><p>30 Okt 2026</p></td><td><p>Terbuka</p></td><td><p>Seluruh fitur web</p></td></tr><tr><td><p>ISU-10</p></td><td><p>Kecukupan laporan keuangan sederhana di Odoo Community</p></td><td><p>Tim</p></td><td><p>6 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-26</p></td></tr><tr><td><p>ISU-P01</p></td><td><p>Perhitungan tagihan untuk kontrak yang dimulai atau berakhir di tengah periode</p></td><td><p>Pemilik</p></td><td><p>13 Nov 2026</p></td><td><p>Terbuka</p></td><td><p>F-10</p></td></tr></tbody></table>

## **12. Di Luar Cakupan**

Hal berikut tidak dikerjakan pada Rilis 1 (Won't). Perubahan cakupan dilakukan melalui revisi PRD yang disetujui problem owner.

- Aplikasi mobile; produk diakses melalui browser.
- Integrasi pembayaran otomatis (payment gateway); pembayaran diverifikasi manual oleh pengelola.
- Perhitungan denda keterlambatan. Bila ISU-01 menetapkan adanya denda, kebutuhan ini ditambahkan melalui revisi PRD.
- Pembayaran sebagian atau cicilan atas satu tagihan.
- Registrasi mandiri oleh penghuni atau vendor.
- Sistem akuntansi kompleks; ERP hanya menyajikan laporan keuangan sederhana.
- Implementasi produksi penuh.

## **13. Dokumen Acuan**

<table><thead><tr><th><p>Kode</p></th><th><p>Dokumen</p></th><th><p>Keterangan</p></th></tr></thead><tbody><tr><td><p>REF-01</p></td><td><p>Business Requirements Document (BRD) Web Pengelolaan Kos Terintegrasi ERP, versi 1.2</p></td><td><p>Dokumen induk: tujuan, kebutuhan bisnis, aturan bisnis, dan register isu</p></td></tr><tr><td><p>REF-02</p></td><td><p>Proposal Capstone Design &amp; Project Kost Putri Kirana, Program Studi S1 Sistem Informasi, Universitas Telkom, 2026</p></td><td><p>Latar belakang, indikator, dan jadwal</p></td></tr><tr><td><p>REF-03</p></td><td><p>Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi</p></td><td><p>Pelindungan data penghuni</p></td></tr><tr><td><p>REF-04</p></td><td><p>ISO/IEC 25010, model kualitas produk perangkat lunak</p></td><td><p>Acuan evaluasi kualitas</p></td></tr><tr><td><p>REF-05</p></td><td><p>ISO/IEC/IEEE 29148:2018, rekayasa kebutuhan</p></td><td><p>Acuan karakteristik kebutuhan yang dapat diverifikasi</p></td></tr><tr><td><p>REF-06</p></td><td><p>Agile Alliance Glossary: Given-When-Then</p></td><td><p>Acuan format kriteria penerimaan</p></td></tr><tr><td><p>REF-07</p></td><td><p>Odoo 19.0 Documentation: External JSON-2 API (odoo.com/documentation/19.0)</p></td><td><p>Endpoint, autentikasi API key, dan batas masa berlaku key</p></td></tr></tbody></table>

## **14. Keputusan Kunci**

Lima keputusan berikut menjadi acuan untuk desain antarmuka, struktur data, product backlog, dan skenario uji.

1. Rilis 1 memuat 29 fitur: 25 Must, 3 Should, dan 1 Could. Fitur Must adalah fitur yang dibutuhkan untuk mencapai indikator TB-01 sampai TB-08.
2. Tagihan diterbitkan pengelola untuk semua kontrak aktif dalam satu periode sekaligus tanpa tagihan ganda. Satu pembayaran melunasi satu tagihan penuh dan baru lunas setelah diverifikasi.
3. Status kamar dihitung dari kontrak. Status tagihan dan tiket berubah berurutan sesuai Bagian 7, dan tunggakan adalah penanda, bukan status.
4. Integrasi ke Odoo Community 19 memakai JSON-2 API dengan API key yang dirotasi paling lambat tiga bulan sekali. Tiga jenis transaksi dikirim secara idempoten melalui referensi unik, dan kegagalan ERP dapat dikirim ulang tanpa membatalkan proses di web.
5. Hak akses dicek di server pada setiap permintaan, vendor tidak pernah melihat data pribadi penghuni, dan data migrasi dianonimkan.