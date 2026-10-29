---
version: alpha
name: Kost Putri Kirana
description: Sistem desain web pengelolaan kos terintegrasi ERP untuk pemilik, pengelola, penghuni, dan vendor.
colors:
  primary: "#0E5E57"
  primary-hover: "#0A4A44"
  on-primary: "#FFFFFF"
  primary-container: "#D7ECE8"
  on-primary-container: "#0A3F3A"
  accent: "#A84A22"
  on-accent: "#FFFFFF"
  background: "#F7F6F2"
  surface: "#FFFFFF"
  surface-muted: "#EFEDE7"
  ink: "#1C1F1E"
  ink-muted: "#5A615F"
  border: "#DAD7CF"
  success-container: "#E3F2E6"
  on-success-container: "#1E6B34"
  warning-container: "#FCF0D9"
  on-warning-container: "#7A4F00"
  danger: "#B3261E"
  danger-container: "#FBE4E1"
  on-danger-container: "#A3261A"
  info-container: "#E3EDF9"
  on-info-container: "#1F4F8F"
  neutral-container: "#ECEBE6"
  on-neutral-container: "#4A4F4D"
typography:
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 22px
    fontWeight: 700
    lineHeight: 1.3
  title:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: 600
    lineHeight: 1.4
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1.3
  caption:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: 500
    lineHeight: 1.4
  metric:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: 700
    lineHeight: 1.1
    fontFeature: '"tnum"'
  money:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: 600
    lineHeight: 1.4
    fontFeature: '"tnum"'
rounded:
  sm: 6px
  md: 10px
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
    typography: "{typography.body-md}"
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 44px
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.on-primary}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 44px
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 44px
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body-lg}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 48px
  input-helper:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-muted}"
    typography: "{typography.caption}"
  divider:
    backgroundColor: "{colors.border}"
    height: 1px
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: 16px
  card-metric:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.metric}"
    rounded: "{rounded.lg}"
    padding: 20px
  table-header:
    backgroundColor: "{colors.surface-muted}"
    textColor: "{colors.ink-muted}"
    typography: "{typography.caption}"
    padding: 12px
  amount:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.money}"
  sidebar:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    width: 248px
  nav-item-active:
    backgroundColor: "{colors.primary-container}"
    textColor: "{colors.on-primary-container}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: 12px
  bottom-nav:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-muted}"
    typography: "{typography.caption}"
    height: 64px
  brand-mark:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    rounded: "{rounded.sm}"
    size: 32px
  badge-success:
    backgroundColor: "{colors.success-container}"
    textColor: "{colors.on-success-container}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 4px
  badge-warning:
    backgroundColor: "{colors.warning-container}"
    textColor: "{colors.on-warning-container}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 4px
  badge-danger:
    backgroundColor: "{colors.danger-container}"
    textColor: "{colors.on-danger-container}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 4px
  badge-info:
    backgroundColor: "{colors.info-container}"
    textColor: "{colors.on-info-container}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 4px
  badge-neutral:
    backgroundColor: "{colors.neutral-container}"
    textColor: "{colors.on-neutral-container}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 4px
  alert-error:
    backgroundColor: "{colors.danger-container}"
    textColor: "{colors.on-danger-container}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: 12px
---

## Overview

Web operasional harian Kost Putri Kirana, kos putri 8 kamar di Cikarang Utara. Empat peran memakai produk ini:
pemilik, pengelola, penghuni, dan vendor. Karakter visualnya tenang, rapi, dan dapat dipercaya, seperti buku kas
yang tertata. Produk ini mengurus uang sewa dan data pribadi, jadi kejelasan lebih penting daripada dekorasi.
Informasi terpenting di setiap layar adalah status dan nominal, sehingga keduanya selalu paling mudah dibaca.

Semua teks antarmuka memakai Bahasa Indonesia yang singkat dan sopan. Contohnya "Unggah bukti bayar", bukan
"Upload". Nominal ditulis dalam format Rupiah tanpa spasi (Rp1.500.000), dan tanggal ditulis 23 Okt 2026.

## Colors

- **Primary (#0E5E57)**, hijau teal tua: aksi utama, tautan, dan menu aktif. Dipakai hemat, satu aksi utama per
  layar.
- **Accent (#A84A22)**, terakota: hanya untuk logo dan sorotan kecil. Bukan untuk tombol.
- **Background (#F7F6F2)** dan **Surface (#FFFFFF)**: latar hangat dengan kartu putih. Surface-muted (#EFEDE7) untuk
  kepala tabel dan area sekunder.
- **Ink (#1C1F1E)** untuk teks utama dan **Ink-muted (#5A615F)** untuk teks pendukung. Border (#DAD7CF) untuk garis
  pemisah.
- **Warna status** selalu berpasangan dengan label teks, tidak pernah warna saja:
  - Sukses (hijau): tagihan lunas, tiket selesai, sinkronisasi terkirim.
  - Peringatan (kuning tua): menunggu verifikasi, tiket dikerjakan, kontrak akan berakhir.
  - Bahaya (merah): tunggakan, bukti ditolak, sinkronisasi gagal.
  - Info (biru): tiket ditugaskan.
  - Netral (abu): belum dibayar, tiket diajukan, kamar kosong, kontrak berakhir, sinkronisasi menunggu.

## Typography

Satu keluarga huruf: **Plus Jakarta Sans**, huruf karya desainer Indonesia yang tersedia di Google Fonts.

- headline-lg 28px/700 untuk judul halaman di desktop; headline-md 22px/700 untuk judul halaman di ponsel.
- title 18px/600 untuk judul kartu dan bagian.
- body-lg 16px untuk isian form, agar teks tidak diperbesar otomatis di ponsel. body-md 14px untuk teks umum dan
  isi tabel.
- label 14px/600 untuk tombol dan menu; caption 12px/500 untuk badge, kepala tabel, dan teks bantuan.
- metric 32px/700 dan money 16px/600 memakai angka tabular (tnum) agar nominal sejajar rapi di tabel dan kartu.

## Layout

Skala jarak berbasis 4px: 4, 8, 12, 16, 24, 32, 48.

- **Desktop (pemilik dan pengelola)**: lebar acuan 1280px. Sidebar kiri 248px berisi logo dan menu; area konten
  maksimal 1120px dengan padding 32px. Halaman daftar memakai tabel dengan filter di atasnya.
- **Ponsel (penghuni dan vendor)**: lebar acuan 360px tanpa gulir horizontal. Padding samping 16px, navigasi bawah
  64px dengan 3–4 menu. Daftar memakai kartu bertumpuk, bukan tabel.
- Setiap halaman dimulai dengan judul, lalu satu baris ringkasan atau filter, lalu konten.
- Kartu metrik dashboard tersusun dalam grid 3 kolom di desktop dan 1 kolom di ponsel.

## Elevation & Depth

Desain datar. Pemisahan memakai latar Surface di atas Background dan garis Border 1px, tanpa bayangan pada kartu.
Bayangan lembut (0 8px 24px, hitam 12%) hanya untuk dialog konfirmasi, bottom sheet, dan menu tarik-turun.

## Shapes

- rounded.sm 6px: logo dan elemen kecil.
- rounded.md 10px: tombol, input, item menu, dan alert.
- rounded.lg 16px: kartu dan dialog.
- rounded.full: badge status dan avatar.
- Ikon memakai Material Symbols Rounded dengan gaya garis (outline), ukuran 20px di menu dan 24px di navigasi bawah.

## Components

- **Tombol**: tinggi 44px. Primary untuk satu aksi utama, Secondary (latar putih, teks primary, garis border) untuk
  aksi kedua, dan Danger untuk tindakan menolak atau mengakhiri. Saat proses berjalan, tombol menampilkan spinner
  dan dinonaktifkan.
- **Input**: tinggi 48px, label di atas field, teks bantuan atau pesan error di bawah. Field yang error memakai garis
  dan teks merah, dan isian lain tetap tersimpan.
- **Badge status**: bentuk pil dengan pasangan warna status di bagian Colors, berisi teks status huruf kecil di awal
  kalimat, misalnya "Menunggu verifikasi".
- **Kartu tagihan (ponsel)**: periode sebagai judul, nominal dengan gaya money, jatuh tempo, badge status, dan
  satu tombol aksi bila tersedia.
- **Tabel (desktop)**: kepala tabel Surface-muted dengan caption, baris 52px, nominal rata kanan, dan badge status
  di kolom status.
- **Kartu metrik**: label caption di atas, angka metric, dan tautan "Lihat rincian".
- **Status kosong**: ikon garis 48px, satu kalimat penjelasan, dan tombol aksi bila ada.
- **Status memuat**: skeleton abu muda sesuai bentuk konten.
- **Dialog konfirmasi**: judul tindakan, satu kalimat dampak, tombol Batal (secondary) dan tombol aksi.
- **Penanda sinkronisasi ERP**: ikon kecil dengan badge Terkirim, Menunggu, atau Gagal di samping transaksi.

## Do's and Don'ts

- Do: tampilkan status sebagai badge berteks dan nominal dengan angka tabular.
- Do: beri satu aksi utama per layar dan minta konfirmasi untuk terima, tolak, tugaskan, selesai, dan akhiri kontrak.
- Do: rancang status kosong, memuat, dan error untuk setiap daftar.
- Do: pakai data contoh yang realistis tetapi fiktif, misalnya Kamar 03 dan Rp1.250.000.
- Don't: menampilkan nomor identitas atau kontak penghuni di layar vendor.
- Don't: memakai gradien, foto stok, ilustrasi besar, atau warna status tanpa teks.
- Don't: memakai istilah bahasa Inggris di antarmuka bila ada padanan Indonesia yang lazim.
- Don't: memakai warna accent untuk tombol atau teks panjang.
