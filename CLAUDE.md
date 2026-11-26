# Web Pengelolaan Kost Putri Kirana

Web pengelolaan kos 8 kamar (Cikarang Utara) yang terintegrasi dengan Odoo Community 19. Empat peran: Pemilik, Pengelola, Penghuni, Vendor. Web menangani hunian, tagihan, pembayaran, dan keluhan. Odoo menangani keuangan, tagihan vendor, dan pemeliharaan. Proyek capstone, Agile Scrum 4 sprint.

## Dokumen acuan

Baca hanya bagian yang relevan dengan tugas yang sedang dikerjakan.

- `docs/PRD.md`: fitur F-01 s.d. F-29 beserta kriteria penerimaannya, halaman per peran (Bagian 6), transisi status (Bagian 7), integrasi ERP (Bagian 8). Sebelum mengerjakan fitur, baca bagian F-xx-nya.
- `docs/BRD.md`: aturan bisnis ATB-xx, matriks hak akses (Bagian 5), kebutuhan nonfungsional NFR-xx.
- `docs/erd/ERD.sql` dan `docs/erd/ERD2.sql`: sumber kebenaran skema basis data (tabel, tipe, enum, CHECK, indeks unik parsial).
- `docs/diagram/`: gambar untuk laporan. Jangan dijadikan acuan skema atau nama.

Bila dokumen saling bertentangan, pakai ERD SQL untuk skema, PRD untuk perilaku fitur, dan BRD untuk aturan bisnis. Bila masih ragu, tanyakan dulu sebelum menulis kode.

## Stack

- Laravel 13 (PHP 8.3+) dengan starter kit Livewire: Livewire 4, Flux UI (komponen gratis saja), Tailwind CSS, Laravel Fortify untuk login.
- PostgreSQL 17 (Docker) untuk basis data web. Odoo Community 19 berjalan di Docker terpisah dengan basis datanya sendiri.
- Queue dan session memakai driver `database`.
- Pengujian memakai Pest. Format kode memakai Laravel Pint.
- Desain UI mengikuti `docs/design/DESIGN.md` (versi 2): huruf Archivo, warna primer `#0E5E57`, sidebar `#0C3533`, status berupa ikon plus teks (bukan badge pil). Mockup desktop dan ponsel per halaman ada di `docs/design/mockup/` (lihat `README.md` di sana), logo di `docs/design/logo/`. Semua halaman harus bisa dipakai di layar 360 px tanpa gulir horizontal (NFR-06).

## Perintah

- Jalankan aplikasi (server, queue, log, Vite): `composer run dev`
- Semua tes: `php artisan test`
- Satu tes: `php artisan test --filter=NamaTes`
- Format file yang berubah: `vendor/bin/pint --dirty`
- Bangun ulang basis data dengan data contoh: `php artisan migrate:fresh --seed`
- Daftar route: `php artisan route:list`

Sebelum menyatakan pekerjaan selesai, `php artisan test` harus lulus dan `vendor/bin/pint --dirty` sudah dijalankan.

## Konvensi kode

- Teks antarmuka, pesan validasi, dan pesan galat ditulis dalam Bahasa Indonesia. Nama tabel dan kolom mengikuti ERD apa adanya (Bahasa Indonesia, snake_case).
- Teks antarmuka mengikuti docs/design/DESIGN.md bagian Penulisan: judul = label menu, tanpa deskripsi, tombol 1 kata, Title Case untuk judul/label/kolom/chip/status/tombol, kalimat biasa untuk pesan.
- Nama model sama dengan nama kelas di class diagram: `User`, `Penghuni`, `Vendor`, `PengaturanOperasional`, `Kamar`, `Kontrak`, `Tagihan`, `Pembayaran`, `Fasilitas`, `TiketKeluhan`, `RiwayatStatusTiket`, `SinkronisasiErp`.
- Setiap model wajib menetapkan `protected $table` secara eksplisit, karena Eloquent menebak nama tabel dengan bentuk jamak bahasa Inggris (`kamars`).
- Tabel ERD tidak punya `created_at`/`updated_at`, jadi pasang `public $timestamps = false` pada model domain. Tabel bawaan framework (`sessions`, `jobs`, `cache`, `password_reset_tokens`) boleh tetap ada di luar ERD.
- Sepuluh enum PostgreSQL dibuat sebagai PHP backed enum di `app/Enums`, dengan nilai yang sama persis dengan ERD, lalu dipasang sebagai cast di model.
- Migration memakai tipe enum PostgreSQL asli dan indeks unik parsial lewat `DB::statement(...)`, disalin dari file SQL ERD. Jangan pakai `$table->enum()`, karena di PostgreSQL hasilnya varchar dengan CHECK, bukan tipe enum.
- Perubahan status dilakukan lewat method model yang namanya sama dengan class diagram (`terima()`, `tolak($alasan)`, `selesaikan($biaya)`, `tugaskanVendor()`, `kirimUlang()`, `terbitkanPeriode()`), di dalam `DB::transaction`. Controller dan komponen Livewire tidak mengubah kolom status secara langsung.
- Hak akses ditegakkan di server: Policy per model, middleware peran per grup route, dan filter "milik sendiri" di query (ATB-10). Menyembunyikan tombol di tampilan saja tidak cukup.
- Setiap fitur F-xx punya feature test Pest, termasuk uji akses ditolak untuk peran lain (TB-04).

## Aturan bisnis yang mudah salah

- Tabel `users` memakai `nama` dan `password_hash`, tanpa `remember_token` dan timestamps. Di model `User` pasang `protected $authPasswordName = 'password_hash';`, nonaktifkan "Ingat saya", dan sesuaikan halaman profil starter kit dari `name` ke `nama`.
- Fortify: matikan `registration`, `emailVerification`, dan `twoFactorAuthentication`. Akun penghuni dan vendor dibuat pengelola (F-02, ASM-05). Akun pemilik dan pengelola dibuat lewat seeder. Akun nonaktif tidak bisa login.
- Status kamar (terisi/kosong) tidak disimpan. Nilainya dihitung dari ada tidaknya kontrak aktif (ATB-01).
- Tunggakan tidak disimpan sebagai kolom atau status. Hitung dengan query scope `Tagihan::tunggakan($tanggal)` sesuai ATB-05:
  - tagihan belum lunas dan `$tanggal` > `jatuh_tempo` → tunggakan;
  - pengecualian: ada pembayaran berstatus `menunggu` dengan `diunggah_pada` paling lambat tanggal jatuh tempo;
  - bukti yang diunggah setelah jatuh tempo tidak menghapus tunggakan sampai diterima;
  - bukti tepat waktu yang ditolak membuat tagihan tetap dihitung tunggakan sejak hari setelah jatuh tempo.
- Penerbitan tagihan (F-10, BR-06): pengelola memilih periode, lalu sistem membuat satu tagihan per kontrak aktif. `periode` = tanggal 1 bulan tagihan. Nominal diambil dari tarif kontrak. `jatuh_tempo` diambil dari `pengaturan_operasional.tanggal_jatuh_tempo`. Kontrak yang sudah punya tagihan pada periode itu dilewati tanpa galat. Penerbitan ditolak selama tanggal jatuh tempo belum diatur.
- Pembayaran (ATB-03, ATB-04): unggahan bukti mengubah tagihan menjadi `menunggu_verifikasi`. Tolak wajib disertai alasan dan mengembalikan tagihan ke `belum_dibayar`. Terima membuat tagihan `lunas` dan membuat baris `sinkronisasi_erp`.
- Berkas bukti bayar disimpan di disk privat, bukan `public`. Berkas hanya bisa dibuka lewat route yang memeriksa Policy.
- Tiket (ATB-07): status hanya maju berurutan diajukan → ditugaskan → dikerjakan → selesai. Setiap perubahan mencatat `riwayat_status_tiket`. Status selesai wajib disertai `biaya_perbaikan`. Nomor tiket berformat `KLH-0001`.
- Vendor (ATB-08) hanya melihat tiket yang ditugaskan kepadanya beserta info kamar dan fasilitas, tanpa identitas, kontrak, atau tagihan penghuni.
- `penghuni.no_identitas_enc` memakai cast `encrypted`. Nomor identitas hanya tampil bagi pemilik, pengelola, dan penghuni yang bersangkutan (F-06, NFR-02), dan di daftar ditampilkan tersamar. Data seeder dan data migrasi harus fiktif atau dianonimkan (F-09).
- Data migrasi asli (storage/app/private/migrasi/sumber) adalah data pribadi. Jangan membuka, membaca, mencetak, atau menyalin isinya. Uji migrasi memakai tests/Fixtures/migrasi.

## Integrasi Odoo (F-22 s.d. F-25)

- Panggil `POST {ODOO_URL}/json/2/<model>/<method>` dengan header `Authorization: bearer {ODOO_API_KEY}` dan `X-Odoo-Database: {ODOO_DB}`. Body berupa JSON dengan argumen bernama (`ids`, `vals_list`, `domain`, `fields`, `context`).
- Semua panggilan HTTP ke Odoo dilakukan oleh satu kelas klien di `app/Services/Odoo`. `IntegrasiErpService` memakai klien itu.
- API key hanya ada di `.env`. API key tidak boleh dikirim ke browser, ditulis ke log, atau disimpan di basis data (NFR-03). Masa berlakunya maksimal 3 bulan, jadi catat tanggal kedaluwarsanya.
- Pengiriman berjalan lewat job di queue, diproses satu per satu (satu worker). Kegagalan ERP tidak membatalkan transaksi web: status sinkronisasi menjadi `gagal`, `pesan_error` diisi, dan pengelola bisa mengirim ulang (F-25).
- Pemicu pengiriman:
  - pembayaran berstatus diterima (ATB-06);
  - tiket berstatus selesai (ATB-09): biaya perbaikan, ditambah pemeliharaan bila fasilitas punya `odoo_equipment_id`.
- Format referensi unik: `KPK-PAY-000123` (pembayaran), `KPK-TKT-0001-B` (biaya perbaikan), `KPK-TKT-0001-M` (pemeliharaan).
- Sebelum `create`, selalu `search_read` berdasarkan referensi. Bila catatan sudah ada, lanjutkan dari keadaannya (draft → post, belum dibayar → bayar) tanpa membuat catatan baru, lalu tandai `terkirim`. Keadaan yang tidak bisa dilanjutkan otomatis (cancel, partial, ganda) ditandai `gagal` untuk diperiksa manual.
- Pemicu: `SinkronisasiErp` created men-dispatch `KirimSinkronisasiErp` setelah commit. Baris lama atau yang tertinggal dikirim dengan `php artisan erp:kirim-tertunda`.
- Model Odoo yang dipakai:
  - `account.move`: faktur pelanggan `out_invoice` dan tagihan vendor `in_invoice`, dengan field `ref`. Baris faktur selalu diberi `tax_ids` kosong (`[[6, 0, []]]`) karena Odoo memasang pajak bawaan 12%;
  - `account.payment.register`: wizard untuk mencatat pembayaran faktur, dibuat dengan context `active_model` dan `active_ids`, lalu `action_create_payments` dengan `dont_redirect_to_payments`;
  - `res.partner`: penghuni dengan `name` "Penghuni #<id>" dan `ref` "PNH-<id>", vendor dengan `ref` "VDR-<id>" dan ID partner disimpan di `vendor.odoo_partner_id`; cari berdasarkan `ref` sebelum membuat;
  - `maintenance.request`: perawatan, dengan referensi di awal `name`.
- Data yang dikirim hanya kolom pada tabel integrasi PRD Bagian 8. Identitas penghuni selain ID tidak dikirim.
- Rincian payload, pemetaan field, dan temuan verifikasi ada di `docs/integrasi/dokumen-teknis-integrasi.md`. Baca sebelum mengerjakan F-22 s.d. F-25.
- Di tes, selalu pakai `Http::fake()`. Tes tidak boleh memanggil Odoo sungguhan.

## Cara kerja

- Kerjakan satu tahap atau satu fitur per sesi, sesuai prompt yang diberikan. Setelah tahap selesai, sebutkan cara mengujinya.
- Jangan menambah fitur, kolom, atau tabel di luar PRD dan ERD. Bila perlu perubahan skema, usulkan dulu beserta alasannya.
- F-29 (pengingat tagihan, Could) dan semua yang berlabel "Di luar cakupan" di PRD jangan dikerjakan sebelum diminta.
- Jangan commit `.env` atau berkas unggahan. Berikan contoh variabel di `.env.example`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.
</laravel-boost-guidelines>
