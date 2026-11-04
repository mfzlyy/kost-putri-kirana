---
version: 2.0
name: Kost Putri Kirana
description: Sistem desain web pengelolaan kos terintegrasi ERP untuk pemilik, pengelola, penghuni, dan vendor. Menggantikan versi Stitch (Plus Jakarta Sans, aksen terakota).
colors:
  primary: "#0E5E57"
  primary-hover: "#0A4842"
  on-primary: "#FFFFFF"
  primary-container: "#DCEDEA"
  on-primary-container: "#0C3533"
  sidebar: "#0C3533"
  on-sidebar: "#B9D6D2"
  on-sidebar-strong: "#FFFFFF"
  on-sidebar-muted: "#9FC2BD"
  logo-ray: "#7CC6BA"
  background: "#F6F5F1"
  surface: "#FFFFFF"
  surface-muted: "#EFEDE8"
  surface-disabled: "#F1EFE9"
  ink: "#18211F"
  ink-muted: "#4D5755"
  border: "#DEDBD3"
  border-strong: "#CFCBC1"
  divider: "#E7E4DD"
  row-divider: "#EFEDE8"
  chip-active: "#18211F"
  status-ok: "#1D6532"
  status-wait: "#714A00"
  status-none: "#474D4B"
  status-late: "#9E2418"
  danger: "#9E2418"
  danger-input-border: "#B3261E"
  danger-container: "#FADFDB"
  on-danger-container: "#7E1C12"
  overlay: "rgba(12, 53, 51, 0.55)"
typography:
  display-desktop:
    fontFamily: Archivo
    fontSize: 34px
    fontWeight: 750
    fontStretch: 125%
    lineHeight: 1.05
    letterSpacing: -0.01em
  display-mobile:
    fontFamily: Archivo
    fontSize: 26px
    fontWeight: 750
    fontStretch: 125%
    lineHeight: 1.1
  metric:
    fontFamily: Archivo
    fontSize: 38px
    fontWeight: 750
    fontStretch: 125%
    lineHeight: 1
    fontFeature: '"tnum"'
  room-number:
    fontFamily: Archivo
    fontSize: 20px
    fontWeight: 750
    fontStretch: 125%
    lineHeight: 1
    fontFeature: '"tnum"'
  section-title:
    fontFamily: Archivo
    fontSize: 16px
    fontWeight: 650
    lineHeight: 1.3
  body:
    fontFamily: Archivo
    fontSize: 15px
    fontWeight: 400
    lineHeight: 1.5
  input:
    fontFamily: Archivo
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: Archivo
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1.3
  status:
    fontFamily: Archivo
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1.3
  caption:
    fontFamily: Archivo
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.4
  code:
    fontFamily: Archivo
    fontSize: 13px
    fontWeight: 400
    fontFeature: '"tnum"'
rounded:
  sm: 8px
  tag: 10px
  md: 12px
  lg: 16px
  full: 9999px
spacing:
  xs: 4px
  sm: 8px
  md: 12px
  lg: 16px
  xl: 24px
  2xl: 32px
  3xl: 48px
components:
  app-background:
    backgroundColor: "{colors.background}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
  sidebar:
    backgroundColor: "{colors.sidebar}"
    textColor: "{colors.on-sidebar}"
    width: 232px
  nav-item:
    textColor: "{colors.on-sidebar}"
    rounded: "{rounded.sm}"
    height: 44px
  nav-item-active:
    backgroundColor: "{colors.primary-container}"
    textColor: "{colors.on-primary-container}"
    rounded: "{rounded.sm}"
  mobile-top-bar:
    backgroundColor: "{colors.sidebar}"
    textColor: "{colors.primary-container}"
    height: 56px
  bottom-tab-penghuni:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-muted}"
    activeColor: "{colors.primary}"
    height: 68px
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    height: 44px
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    borderColor: "{colors.border-strong}"
    rounded: "{rounded.sm}"
    height: 44px
  button-danger:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.danger}"
    borderColor: "#E3B4AD"
    rounded: "{rounded.sm}"
    height: 44px
  input:
    backgroundColor: "{colors.surface}"
    borderColor: "{colors.border-strong}"
    typography: "{typography.input}"
    rounded: "{rounded.sm}"
    height: 46px
  filter-chip:
    backgroundColor: "{colors.surface}"
    borderColor: "{colors.border-strong}"
    rounded: "{rounded.full}"
    height: 36px
  filter-chip-active:
    backgroundColor: "{colors.chip-active}"
    textColor: "#FFFFFF"
  card:
    backgroundColor: "{colors.surface}"
    borderColor: "{colors.border}"
    rounded: "{rounded.lg}"
  room-tag:
    backgroundColor: "{colors.surface}"
    borderColor: "{colors.border}"
    rounded: "{rounded.tag}"
  table-header:
    textColor: "{colors.ink-muted}"
    typography: "{typography.caption}"
  table-row:
    height: 60px
    borderColor: "{colors.row-divider}"
  alert-error:
    backgroundColor: "{colors.danger-container}"
    textColor: "{colors.on-danger-container}"
    rounded: "{rounded.md}"
  note-info:
    backgroundColor: "{colors.primary-container}"
    textColor: "{colors.on-primary-container}"
    rounded: "{rounded.md}"
---

## Overview

Web operasional harian Kost Putri Kirana, kos putri 8 kamar di Cikarang Utara. Empat peran memakai produk ini:
pemilik, pengelola, penghuni, dan vendor. Rujukan visualnya adalah papan kunci di meja pengelola kos: deretan
gantungan kunci bernomor, sidebar hijau tua seperti papan kayu, dan kertas putih hangat untuk catatan. Produk ini
mengurus uang sewa dan data pribadi, jadi status dan nominal selalu paling mudah dibaca di setiap layar.

Semua teks antarmuka memakai Bahasa Indonesia yang singkat dan sopan, huruf kapital hanya di awal kalimat.
Contohnya "Unggah bukti bayar", bukan "Upload". Nominal ditulis Rp1.400.000 (tanpa spasi), tanggal ditulis
3 Nov 2026 atau 3 November 2026.

Mockup acuan ada di `docs/design/mockup/`. Setiap halaman punya versi desktop (1280 px) dan ponsel (360 px).
Lihat `docs/design/mockup/README.md` untuk daftar file per peran.

## Brand dan logo

Logo adalah gantungan kunci kamar dengan lubang di atas dan huruf K. Garis miring atas huruf K dibuat seperti
seberkas sinar, karena "kirana" berarti sinar. File ada di `docs/design/logo/`:

- `logo.svg`: untuk latar terang (halaman login, header ponsel penghuni dan vendor).
- `logo-gelap.svg`: untuk latar hijau tua (sidebar dan top bar ponsel).
- `favicon.svg`: 16 px, bentuk disederhanakan tanpa lubang.

Wordmark: "Kost Putri" kecil (11 px, warna on-sidebar-muted) di atas "Kirana" (18 px, berat 750, lebar 125%).
Jarak logo ke wordmark 10 px. Jangan memutar, memberi bayangan, atau mengganti warna sinar di luar dua versi di atas.

## Colors

- **Primary (#0E5E57)**, hijau teal: tombol utama, tautan, tab aktif, dan ruas meter tiket. Satu aksi utama per layar.
- **Sidebar (#0C3533)**, hijau tua: sidebar desktop, top bar ponsel, dan latar logo. Teks menu di atasnya
  #B9D6D2, menu aktif berlatar Primary-container (#DCEDEA) dengan teks #0C3533.
- **Logo-ray (#7CC6BA)**: hanya untuk sinar di logo. Bukan untuk tombol atau teks.
- **Background (#F6F5F1)** dan **Surface (#FFFFFF)**: latar kertas hangat dengan kartu putih. Surface-muted (#EFEDE8)
  untuk tempat pratinjau gambar. Surface-disabled (#F1EFE9) untuk kamar atau pilihan yang tidak bisa dipilih.
- **Ink (#18211F)** teks utama, **Ink-muted (#4D5755)** teks pendukung. Border (#DEDBD3) untuk kartu,
  Border-strong (#CFCBC1) untuk input dan tombol sekunder, Divider (#E7E4DD) dan Row-divider (#EFEDE8) untuk garis
  di dalam kartu dan tabel.
- **Warna status** hanya dipakai untuk teks dan ikon status, tidak pernah sebagai latar:
  - status-ok #1D6532: lunas, terkirim, aktif, terisi.
  - status-wait #714A00: menunggu dicek, akan berakhir, belum ditugaskan.
  - status-none #474D4B: belum bayar, kosong, nonaktif.
  - status-late #9E2418: telat, lewat jatuh tempo, ditolak, gagal.
- **Danger-container (#FADFDB)** hanya untuk ringkasan error di atas form.
- Terakota (#A84A22) dari versi Stitch tidak dipakai lagi.

## Typography

Satu keluarga huruf: **Archivo** (Google Fonts, variabel dengan sumbu lebar `wdth` 62–125 dan berat `wght` 100–900).

- Judul halaman memakai lebar 125% dan berat 750: 34 px di desktop, 26 px di ponsel. Ini satu-satunya tempat huruf
  dilebarkan, selain nomor kamar dan angka besar.
- Angka besar (nominal tagihan, metrik) 38–44 px, berat 750, lebar 125%, angka tabular.
- Nomor kamar 20–22 px, berat 750, lebar 125%, angka tabular.
- Teks umum 15 px, isian form 16 px agar tidak diperbesar otomatis di ponsel.
- Label dan tombol 14–15 px berat 600. Kepala tabel 13 px warna Ink-muted, tanpa latar dan tanpa huruf kapital semua.
- Semua nominal, tanggal di tabel, dan kode (KLH-0012, KPK-PAY-000041) memakai `font-variant-numeric: tabular-nums`.

## Layout

Skala jarak berbasis 4 px: 4, 8, 12, 16, 24, 32, 48.

- **Desktop, semua peran (≥ 1024 px)**: sidebar kiri 232 px berwarna Sidebar berisi logo, menu peran, lalu nama,
  peran, Ganti password, dan Keluar di bagian bawah. Konten padding 40 px 48 px, lebar maksimal 1040 px
  (form 760–860 px). Halaman daftar memakai tabel di dalam kartu.
- **Ponsel pemilik dan pengelola (< 1024 px)**: top bar 56 px berwarna Sidebar dengan tombol menu, logo, dan label
  peran. Sidebar menjadi menu geser dari kiri (lebar 288 px) dengan overlay. Tabel berubah menjadi daftar kartu.
- **Ponsel penghuni**: tab bawah 68 px dengan 4 menu (Tagihan, Riwayat, Keluhan, Profil), ikon garis 24 px.
  [ASUMSI] Prompt 4 membuat menu geser untuk semua peran. Tab bawah ditambahkan saat fitur penghuni dibangun.
- **Ponsel vendor**: header putih dengan logo, judul "Permintaan kerja", nama vendor, dan tautan Keluar.
- Halaman detail di ponsel memakai header putih 56 px dengan tombol kembali.
- Setiap halaman: judul, satu kalimat penjelasan, aksi utama, filter, lalu konten. Tidak ada gulir horizontal di
  360 px (NFR-06). Tabel desktop boleh bergulir di dalam kartunya sendiri.

## Elevation & Depth

Desain datar. Pemisahan memakai Surface di atas Background dan garis Border 1 px. Bayangan hanya untuk dialog
(0 24px 60px, rgba(12, 53, 51, .28)). Overlay dialog dan menu geser memakai rgba(12, 53, 51, .55).

## Shapes

- 8 px: tombol, input, item menu, segmented control.
- 10 px: gantungan kunci kamar.
- 12 px: alert, catatan info, area unggah.
- 16 px: kartu, tabel, dialog, bottom sheet (sudut atas saja).
- Penuh (pil): hanya chip filter dan angka hitungan di menu. Status tidak memakai pil.
- Ikon: SVG garis 1,75 px, ujung membulat, 20–24 px. Tidak memakai font ikon.

## Status

Status ditulis sebagai ikon kecil 14 px plus teks berat 600 berwarna status, tanpa latar dan tanpa bingkai.

| Ikon | Arti | Warna | Contoh teks |
|---|---|---|---|
| Lingkaran penuh dengan centang | selesai baik | status-ok | Lunas, Terkirim, Aktif, Terisi |
| Jam (lingkaran garis + jarum) | menunggu orang lain | status-wait | Menunggu dicek, 17 hari lagi |
| Lingkaran garis | belum ada tindakan | status-none | Belum bayar, Nonaktif |
| Lingkaran garis putus-putus | kosong | status-none | Kosong (kamar) |
| Segitiga penuh dengan tanda seru | terlambat | status-late | Telat 24 hari, Lewat 4 hari |
| Lingkaran penuh dengan silang | gagal atau ditolak | status-late | Gagal, Ditolak |

Path SVG (viewBox 0 0 16 16, `aria-hidden="true"`, warna dari `currentColor`):

```html
<!-- ok -->   <circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M4.8 8.2l2.1 2.1 4.3-4.6" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
<!-- wait --> <circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 4.8V8l2.2 1.4" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
<!-- none --> <circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/>
<!-- kosong --> <circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-dasharray="2.4 2"/>
<!-- late --> <path d="M8 1.5 15 14H1z" fill="currentColor"/><path d="M8 6v3.6" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/><circle cx="8" cy="11.8" r=".95" fill="#fff"/>
<!-- gagal --> <circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M5.6 5.6l4.8 4.8M10.4 5.6l-4.8 4.8" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
```

**Meter tiket**: 4 ruas 14×5 px, jarak 3 px, sudut 2 px. Ruas terisi Primary, sisanya Border. Jumlah ruas terisi
mengikuti status ATB-07: diajukan 1, ditugaskan 2, dikerjakan 3, selesai 4. Selalu diikuti teks status.

## Components

- **Gantungan kunci kamar** (elemen khas): kotak 10 px dengan lubang bulat 8 px di tengah atas, nomor kamar besar,
  lalu nama atau keterangan. Dipakai di Dashboard (papan kamar) dan pemilih kamar di form kontrak. Kamar yang tidak
  bisa dipilih berlatar Surface-disabled, kamar terpilih bergaris Primary 2 px dan berlatar Primary-container.
- **Tombol**: Primary, Secondary (putih, garis Border-strong), Danger (putih, teks merah) untuk tolak, hapus, dan
  nonaktifkan. Tombol konfirmasi tolak di dialog berlatar status-late. Tinggi 44–46 px di desktop, 48 px dan
  selebar layar di ponsel.
- **Tautan aksi di baris tabel**: teks Primary berat 600 tanpa garis bawah ("Ubah", "Aktifkan"); aksi berbahaya
  berwarna Danger ("Nonaktifkan", "Hapus", "Akhiri").
- **Input**: label di atas, bantuan atau error 13 px di bawah. Error: garis #B3261E, `aria-invalid`, pesan
  menyebut cara memperbaiki ("NIK harus 16 angka. Baru 8 angka."). Bila ada lebih dari satu error, tampilkan
  ringkasan Danger-container di atas form.
- **Chip filter**: pil 36 px dengan jumlah ("Belum bayar 3"). Chip aktif berlatar #18211F. Di ponsel chip bergulir
  menyamping di dalam barisnya.
- **Tabel desktop**: di dalam kartu 16 px, kepala 13 px Ink-muted, baris 60 px, nominal rata kanan.
- **Daftar kartu ponsel**: satu kartu putih berisi baris-baris bergaris pemisah; tiap baris berisi nama atau nomor
  kamar, nominal di kanan, keterangan, lalu status.
- **Dialog**: desktop di tengah dengan lebar maksimal 560 px; ponsel sebagai bottom sheet atau layar penuh untuk form
  panjang.
- **Catatan info**: latar Primary-container untuk penjelasan aturan (misalnya aturan jatuh tempo, siapa yang bisa
  melihat NIK).
- **Status kosong**: ikon gantungan kunci bergaris putus-putus, judul pendek, satu kalimat arahan.
- **Data yang disamarkan**: nomor identitas `32**********3105` (2 digit awal, 4 digit akhir) dengan tombol
  "Tampilkan" hanya untuk pemilik, pengelola, dan penghuni yang bersangkutan.
- **Penanda Odoo**: status Terkirim atau Gagal memakai ikon status yang sama; "Gagal" menaut ke halaman
  Sinkronisasi ERP.

## Peran dan halaman

- **Khusus pemilik**: Dashboard.
- **Khusus pengelola**: Verifikasi pembayaran, Sinkronisasi ERP, Akun pengguna, dan semua form tambah atau ubah.
- **Dipakai berdua**: Kamar, Penghuni, Kontrak, Tagihan, Riwayat transaksi, Tiket keluhan, Pengaturan. Pemilik
  hanya melihat: tombol tambah, ubah, hapus, terbitkan, perpanjang, akhiri, tugaskan, dan simpan tidak dirender
  (bukan sekadar disembunyikan dengan CSS), dan aksinya tetap ditolak oleh Policy.
- **Penghuni**: Tagihan saya, Riwayat transaksi, Keluhan saya, Profil saya.
- **Vendor**: Permintaan kerja. Vendor tidak pernah melihat nama, kontak, kontrak, atau tagihan penghuni.

## Do's and Don'ts

- Do: tulis status sebagai ikon plus teks, dan nominal dengan angka tabular.
- Do: satu aksi utama per layar; minta konfirmasi untuk terima, tolak, tugaskan, selesai, nonaktifkan, dan akhiri.
- Do: rancang keadaan kosong, memuat, dan error untuk setiap daftar.
- Do: pakai data contoh fiktif yang konsisten (Kamar 01–08, tarif Rp1.250.000 dan Rp1.400.000).
- Don't: memakai badge pil berwarna untuk status.
- Don't: label huruf kapital semua, teks yang digabung dengan titik tengah ("A · B"), atau panah "→" di tombol.
- Don't: gradien, foto stok, ilustrasi besar, bayangan pada kartu, atau warna status tanpa teks.
- Don't: istilah bahasa Inggris di antarmuka bila ada padanan Indonesia yang lazim.
- Don't: memakai warna sinar logo (#7CC6BA) atau warna sidebar untuk tombol.
