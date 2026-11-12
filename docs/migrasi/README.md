# Migrasi data awal dari Google Spreadsheet (F-09)

Panduan ini untuk memindahkan data kost dari Google Spreadsheet milik pemilik ke basis data web, satu kali saja, lewat dua perintah Artisan: `migrasi:anonimkan` dan `migrasi:impor`. Tidak ada halaman web untuk fitur ini.

## 1. Tujuan dan batasan

- **Sekali jalan.** Dipakai saat awal uji coba, bukan sebagai fitur rutin.
- **Basis data harus kosong.** `migrasi:impor` menolak berjalan bila tabel `kamar`, `penghuni`, `kontrak`, `tagihan`, `pembayaran` sudah berisi atau sudah ada akun berperan penghuni. Bila ada galat yang perlu diperbaiki, ulangi dari `php artisan migrate:fresh`.
- **Data dianonimkan sebelum dimuat** (BR-26, NFR-08). Nama, nomor identitas, dan telepon penghuni diganti nilai fiktif. Kode, nomor kamar, tarif, tanggal, periode, nominal, dan status dipertahankan supaya rekap bisa dicocokkan. `migrasi:impor` menolak folder yang belum dianonimkan.
- **Tanpa sinkronisasi Odoo.** Riwayat lama tidak dikirim ke Odoo dan tidak ada baris `sinkronisasi_erp`. Saldo awal Odoo di luar cakupan.
- **Akun migrasi bukan untuk login.** Setiap penghuni mendapat akun dengan email `penghuni.<kode>@migrasi.kirana.test` dan password acak yang tidak pernah ditampilkan. Uji login memakai akun contoh dari seeder.
- Pembayaran lama dimuat sebagai pembayaran berstatus `diterima` dengan bukti `migrasi/tanpa-bukti`, karena data lama tidak punya berkas bukti. Halaman bukti bayar menampilkan "Bukti tidak tersedia (data migrasi)." untuk jalur ini.

## 2. Menyiapkan data

1. Salin spreadsheet pemilik menjadi **satu Google Sheets kerja** dengan **empat tab** bernama `kamar`, `penghuni`, `kontrak`, `pembayaran`.
2. Isi kolom sesuai templat di `docs/migrasi/templat/` (nama kolom dan urutannya harus sama; baris pertama adalah header).
3. Buat kode sendiri untuk menghubungkan antartab: `P01`, `P02`, ... untuk penghuni dan `K01`, `K02`, ... untuk kontrak. Kode hanya dipakai saat impor dan tidak disimpan. Kode penghuni boleh huruf, angka, titik, garis bawah, dan tanda hubung (maksimal 30 karakter).
4. Pembayaran hanya **6 bulan terakhir yang diizinkan pemilik**. Periode yang lebih lama dari 6 bulan sebelum bulan berjalan tetap dimuat, tetapi muncul sebagai peringatan (DEP-02).

### Kolom per tab

| Tab | Kolom |
| --- | --- |
| `kamar` | `nomor_kamar`, `tarif_bulanan`, `keterangan` |
| `penghuni` | `kode_penghuni`, `nama`, `no_identitas`, `no_telepon`, `status` (`aktif` / `nonaktif`) |
| `kontrak` | `kode_kontrak`, `kode_penghuni`, `nomor_kamar`, `tanggal_mulai`, `tanggal_berakhir`, `tarif_bulanan`, `status` (`aktif` / `berakhir`) |
| `pembayaran` | `kode_kontrak`, `periode`, `nominal`, `jatuh_tempo`, `tanggal_bayar` |

- Satu baris `pembayaran` = satu tagihan bulanan. `periode` berformat `YYYY-MM` (misalnya `2026-10`). `tanggal_bayar` dikosongkan bila belum dibayar.
- Kontrak berakhir ikut dimuat bila punya tagihan dalam 6 bulan terakhir, karena tagihan harus menempel ke kontrak.
- **Tanggal:** `YYYY-MM-DD` atau `DD/MM/YYYY`.
- **Rupiah:** `1250000`, `1.250.000`, `Rp1.250.000`, `Rp 1.250.000`, atau `1.250.000,00`. Desimal selain `,00` / `.00` ditolak.
- **Telepon:** spasi, tanda hubung, titik, dan kurung dibuang; awalan `+62` atau `62` menjadi `0`.
- Berkas harus UTF-8. Pemisah koma atau titik koma dikenali dari baris header, BOM diabaikan, baris yang semua kolomnya kosong dilewati.

## 3. Mengekspor dari Google Sheets

1. Buka tab, lalu **File → Download → Comma-separated values (.csv)**. Hanya tab yang sedang aktif yang terunduh, jadi ulangi untuk keempat tab.
2. Ganti nama berkas menjadi `kamar.csv`, `penghuni.csv`, `kontrak.csv`, `pembayaran.csv`.
3. Simpan keempatnya di `storage/app/private/migrasi/sumber/`. Folder `storage/app/private` sudah diabaikan git.

## 4. Urutan perintah

```
php artisan migrate:fresh
php artisan db:seed --class=PengaturanOperasionalSeeder
php artisan db:seed --class=AkunAwalSeeder
php artisan migrasi:anonimkan storage/app/private/migrasi/sumber storage/app/private/migrasi/anonim
php artisan migrasi:impor storage/app/private/migrasi/anonim --uji
php artisan migrasi:impor storage/app/private/migrasi/anonim
```

### `migrasi:anonimkan {sumber} {tujuan} {--seed=} {--timpa}`

- Hanya berjalan bila `APP_ENV` bukan `production` ("Anonimisasi hanya dijalankan di laptop pengembang.").
- Menulis keempat berkas ke folder tujuan. Berhenti bila folder tujuan sudah berisi CSV, kecuali diberi `--timpa`.
- Yang diganti (hanya di `penghuni.csv`): `nama` (nama fiktif unik), `no_identitas` (`00` + 14 angka acak; kode provinsi 00 tidak ada), dan `no_telepon` (`08000000` + 4 angka acak). Sel yang kosong di sumber tetap kosong, jadi galat sumber tetap terlapor saat impor. Kolom tambahan di luar templat tidak ikut disalin.
- `--seed=7` membuat hasil bisa diulang. Tanpa `--seed`, hasilnya acak.
- Menulis `_anonim.json` (waktu pembuatan dan jumlah baris per berkas). **Tabel padanan nama asli dan nama fiktif tidak disimpan di mana pun.**
- Keluaran konsol hanya jumlah baris; isi baris tidak pernah dicetak.

### `migrasi:impor {folder} {--uji}`

Prasyarat (berhenti dengan pesan jelas bila gagal):

1. Folder punya `_anonim.json` dan semua `no_identitas` berawalan `00`.
2. Basis data kosong dari data hunian dan akun penghuni.
3. Baris `pengaturan_operasional` ada dan ada akun pengelola aktif (dipakai sebagai pemverifikasi pembayaran lama).

Lalu semua baris divalidasi (galat dikumpulkan, tidak berhenti di galat pertama), baris valid dimuat dalam **satu transaksi** (gagal di tengah = tidak ada yang tersimpan), dan laporan dicetak.

- `--uji` menjalankan validasi, pemuatan, dan rekonsiliasi lalu membatalkan semuanya. Pakai ini dulu.
- Kode keluar `0` bila prasyarat lolos (walau ada baris ditolak), `1` bila prasyarat atau transaksi gagal.

## 5. Membaca laporan

Laporan ditulis ke `storage/app/private/migrasi/laporan/` dan tidak pernah memuat nama, nomor identitas, atau telepon.

**`kesalahan-<tanggal>-<jam>.csv`** (F-09 butir 4), kolom: `berkas`, `baris`, `kode`, `kolom`, `pesan`, `jenis`.

- `baris` adalah nomor baris di berkas (header = 1), sama dengan nomor baris di Google Sheets.
- `jenis = galat`: baris ditolak dan tidak dimuat. Baris yang merujuk baris tertolak ikut ditolak ("Merujuk kontrak.csv K06 yang tidak dimuat.").
- `jenis = peringatan`: baris tetap dimuat. Ada dua: kontrak aktif yang sudah lewat tanggal berakhir (akan diakhiri oleh `kontrak:akhiri-kedaluwarsa`) dan periode pembayaran di luar 6 bulan terakhir.
- Berkas ini selalu dibuat; bila tidak ada temuan, isinya hanya header.

**`rekonsiliasi-<tanggal>-<jam>.csv`** (F-09 butir 3), juga dicetak sebagai tabel di konsol.

- Kolom `Sumber` (semua baris data di berkas), `Dimuat` (dihitung ulang lewat query ke basis data setelah insert), `Ditolak`, dan `Selisih` = Sumber − Dimuat − Ditolak. Selisih harus 0.
- Baris jumlah: Kamar, Penghuni, Kontrak aktif, Kontrak berakhir (kontrak dengan status tidak dikenal hanya muncul di laporan kesalahan).
- Baris per periode (urut naik): Jumlah tagihan, Total nominal tagihan, Jumlah lunas, Total dibayar. Angka rupiah ditulis `Rp1.400.000`.
- Kesimpulan di akhir: "Rekonsiliasi cocok: selisih 0." atau "Ada selisih. Periksa laporan kesalahan."

Memperbaiki baris: ubah di **Google Sheets kerja**, ekspor ulang, lalu ulangi dari `php artisan migrate:fresh` (langkah 4). Jangan memperbaiki langsung di folder anonim.

## 6. Setelah selesai

- **Hapus folder `storage/app/private/migrasi/sumber`** dan berkas CSV unduhan di folder Downloads. Folder `anonim` dan `laporan` boleh disimpan selama masih dibutuhkan untuk laporan.
- **Jangan mengunggah atau menempel data asli ke chat atau alat AI** (termasuk Claude Code). Uji migrasi memakai fixture fiktif di `tests/Fixtures/migrasi`.
- Kembalikan data pengembangan dengan `php artisan migrate:fresh --seed` bila perlu.
