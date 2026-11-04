# Mockup Kost Putri Kirana

Sumber mockup dari kanvas desain (versi 2, Archivo). Setiap file `.dc.html` berisi HTML dan CSS satu layar. Beberapa file memakai templat kanvas (`{{...}}`, `<sc-for>`, `<sc-if>`) dan butuh runtime kanvas untuk tampil utuh, jadi untuk melihat tampilannya buka kanvas di claude.ai. Untuk membangun halaman, baca HTML dan CSS-nya sebagai acuan ukuran, warna, teks, dan urutan elemen. Token dan aturan ada di `../DESIGN.md`.

File berawalan `M` adalah versi ponsel halaman kantor, `D` adalah versi desktop halaman penghuni dan vendor. File dengan pilihan peran (pengelola atau pemilik) membaca prop `peran`; pemilik hanya melihat.

## Fondasi

| Layar | File | Ukuran |
|---|---|---|
| Logo | `Logo.dc.html` | 1440 px |
| Sistem desain | `Main.dc.html` | 1440 px |
| Masuk (desktop) | `Login.dc.html` | 1280 px |
| Masuk (ponsel) | `LoginPonsel.dc.html` | 360 px |
| Ganti password (desktop) | `GantiPassword.dc.html` | 1280 px |
| Ganti password (ponsel) | `GantiPasswordPonsel.dc.html` | 360 px |
| Akses ditolak (desktop) | `AksesDitolak.dc.html` | 1280 px |
| Akses ditolak (ponsel) | `AksesDitolakPonsel.dc.html` | 360 px |

## Khusus pemilik

| Layar | File | Ukuran |
|---|---|---|
| Dashboard (desktop) | `Dashboard.dc.html` | 1280 px |
| Dashboard (ponsel) | `MDashboard.dc.html` | 360 px |

## Khusus pengelola

| Layar | File | Ukuran |
|---|---|---|
| Verifikasi pembayaran (desktop) | `Verifikasi.dc.html` | 1280 px |
| Verifikasi pembayaran (ponsel) | `MVerifikasi.dc.html` | 360 px |
| Detail bukti (ponsel) | `MVerifikasiDetail.dc.html` | 360 px |
| Dialog tolak bukti (desktop) | `Tolak.dc.html` | 1280 px |
| Dialog tolak bukti (ponsel) | `MTolak.dc.html` | 360 px |
| Sinkronisasi ERP (desktop) | `Sinkronisasi.dc.html` | 1280 px |
| Sinkronisasi ERP (ponsel) | `MSinkronisasi.dc.html` | 360 px |
| Akun pengguna (desktop) | `Akun.dc.html` | 1280 px |
| Akun pengguna (ponsel) | `MAkun.dc.html` | 360 px |
| Dialog tambah akun (desktop) | `AkunDialog.dc.html` | 1280 px |
| Dialog tambah akun (ponsel) | `MAkunDialog.dc.html` | 360 px |
| Ubah kamar (desktop) | `KamarForm.dc.html` | 1280 px |
| Ubah kamar (ponsel) | `MKamarForm.dc.html` | 360 px |
| Tambah penghuni (desktop) | `PenghuniForm.dc.html` | 1280 px |
| Tambah penghuni (ponsel) | `MPenghuniForm.dc.html` | 360 px |
| Buat kontrak (desktop) | `KontrakForm.dc.html` | 1280 px |
| Buat kontrak (ponsel) | `MKontrakForm.dc.html` | 360 px |

## Dipakai pengelola dan pemilik

| Layar | File | Ukuran |
|---|---|---|
| Tagihan (desktop) | `Tagihan.dc.html` | 1280 px |
| Tagihan (ponsel) | `MTagihan.dc.html` | 360 px |
| Kamar (desktop) | `Kamar.dc.html` | 1280 px |
| Kamar (ponsel) | `MKamar.dc.html` | 360 px |
| Penghuni (desktop) | `Penghuni.dc.html` | 1280 px |
| Penghuni (ponsel) | `MPenghuni.dc.html` | 360 px |
| Kontrak (desktop) | `Kontrak.dc.html` | 1280 px |
| Kontrak (ponsel) | `MKontrak.dc.html` | 360 px |
| Riwayat transaksi (desktop) | `Riwayat.dc.html` | 1280 px |
| Riwayat transaksi (ponsel) | `MRiwayat.dc.html` | 360 px |
| Tiket keluhan (desktop) | `Tiket.dc.html` | 1280 px |
| Tiket keluhan (ponsel) | `MTiket.dc.html` | 360 px |
| Detail tiket (ponsel) | `MTiketDetail.dc.html` | 360 px |
| Pengaturan (desktop) | `Pengaturan.dc.html` | 1280 px |
| Pengaturan (ponsel) | `MPengaturan.dc.html` | 360 px |
| Menu geser (ponsel) | `MMenu.dc.html` | 360 px |

## Penghuni

| Layar | File | Ukuran |
|---|---|---|
| Tagihan saya (ponsel) | `PTagihan.dc.html` | 360 px |
| Tagihan saya (desktop) | `DPTagihan.dc.html` | 1280 px |
| Unggah bukti (ponsel) | `PUnggah.dc.html` | 360 px |
| Unggah bukti (desktop) | `DPUnggah.dc.html` | 1280 px |
| Riwayat transaksi (ponsel) | `PRiwayat.dc.html` | 360 px |
| Riwayat transaksi (desktop) | `DPRiwayat.dc.html` | 1280 px |
| Keluhan saya (ponsel) | `PKeluhan.dc.html` | 360 px |
| Keluhan saya (desktop) | `DPKeluhan.dc.html` | 1280 px |
| Ajukan keluhan (ponsel) | `PAjukan.dc.html` | 360 px |
| Ajukan keluhan (desktop) | `DPAjukan.dc.html` | 1280 px |
| Profil saya (ponsel) | `PProfil.dc.html` | 360 px |
| Profil saya (desktop) | `DPProfil.dc.html` | 1280 px |

## Vendor

| Layar | File | Ukuran |
|---|---|---|
| Permintaan kerja (ponsel) | `VDaftar.dc.html` | 360 px |
| Permintaan kerja (desktop) | `DVDaftar.dc.html` | 1280 px |
| Detail pekerjaan (ponsel) | `VKerja.dc.html` | 360 px |
| Detail pekerjaan (desktop) | `DVKerja.dc.html` | 1280 px |
