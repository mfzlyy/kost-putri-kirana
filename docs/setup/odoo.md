# Odoo Community 19 di Docker

Panduan memasang dan merawat Odoo untuk integrasi ERP Web Pengelolaan Kost Putri Kirana (F-22 s.d. F-28, KPK-49).

## 1. Ringkasan

| Item | Nilai |
|---|---|
| Proyek compose | `kpk-odoo` (terpisah dari proyek web) |
| Berkas compose | `docker/odoo/docker-compose.yml` |
| Container aplikasi | `kpk-odoo` (image `odoo:19.0`), port host `8069` |
| Container basis data | `kpk-odoo-db` (image `postgres:17`), tidak dibuka ke host |
| Volume | `kpk_odoo_pgdata` (data PostgreSQL), `kpk_odoo_data` (filestore dan sesi Odoo) |
| Konfigurasi | `docker/odoo/config/odoo.conf` (rahasia, tidak di-commit; contoh: `odoo.conf.example`) |
| Rahasia basis data | `docker/odoo/.env` (tidak di-commit; contoh: `.env.example`) |
| Modul tambahan | `docker/odoo/addons/` (kosong; dipasang ke `/mnt/extra-addons`) |
| Basis data Odoo | `kpk_odoo` (dibuat manual, lihat bagian 4) |

PostgreSQL web (`kpk-postgres`, port 5433) tidak berhubungan dengan Odoo dan tidak boleh diubah.

**Mengapa `odoo:19.0`, bukan `latest`?** Tag `latest` sekarang mengarah ke Odoo 20. Proyek ini dirancang untuk Odoo 19 dan JSON-2 API-nya, jadi image dikunci ke `odoo:19.0`. Jangan memakai `latest` atau `odoo:20`.

Konfigurasi koneksi basis data (`HOST`, `USER`, `PASSWORD`) diberikan lewat environment container, bukan lewat `odoo.conf`.

## 2. Perintah harian

Jalankan dari folder proyek (`kost-putri-kirana`):

```powershell
# menyalakan
docker compose -f docker/odoo/docker-compose.yml up -d

# mematikan (data tetap ada di volume)
docker compose -f docker/odoo/docker-compose.yml stop

# melihat log
docker compose -f docker/odoo/docker-compose.yml logs -f odoo

# status
docker compose -f docker/odoo/docker-compose.yml ps
```

Cek cepat: `http://localhost:8069/web/health` harus menjawab `{"status": "pass"}`. Versi: `docker exec kpk-odoo odoo --version`.

Perintah `docker compose ... down -v` menghapus volume dan seluruh data Odoo. Jangan dipakai kecuali memang ingin mengulang dari nol.

## 3. Backup dan pulihkan basis data Odoo

**Backup utama (termasuk filestore):** buka `http://localhost:8069/web/database/manager`, pilih basis data `kpk_odoo`, klik **Backup**, pilih format **zip**. Master password ada di baris `admin_passwd` pada `docker/odoo/config/odoo.conf`.

**Pulihkan:** halaman yang sama, tombol **Restore** dengan berkas zip tersebut.

**Alternatif (hanya basis data, tanpa filestore)** memakai `pg_dump` di dalam `kpk-odoo-db`. Jangan memakai pengalihan `>` di PowerShell karena merusak encoding berkas; simpan dulu di dalam container, lalu salin:

```powershell
docker exec kpk-odoo-db pg_dump -U odoo -d kpk_odoo -f /tmp/kpk_odoo.sql
docker cp kpk-odoo-db:/tmp/kpk_odoo.sql ..\backup_kpk_odoo.sql
docker exec kpk-odoo-db rm /tmp/kpk_odoo.sql
```

## 4. Langkah manual setelah instalasi

Langkah ini dikerjakan sendiri karena memakai password dan API key yang tidak boleh lewat chat.

1. Buka http://localhost:8069/web/database/manager, lalu isi:
   - Master Password: dari `docker/odoo/config/odoo.conf` (baris `admin_passwd`)
   - Database Name: `kpk_odoo`
   - Email: `admin@kostputrikirana.test`
   - Password: buat sendiri, minimal 12 karakter
   - Language: English (US)
   - Country: Indonesia
   - Demo data: tidak dicentang

   Klik **Create**.
2. Login sebagai admin. Buka **Apps**, lalu pasang: **Invoicing**, **Purchase**, **Maintenance**, **Contacts**.
3. Buka **Settings > Companies >** perusahaan utama:
   - Nama: `Kost Putri Kirana`
   - Negara: Indonesia
   - Mata uang: IDR

   Pastikan lokalisasi Indonesia (`l10n_id`) ikut terpasang.
4. Buat pengguna integrasi di **Settings > Users > New**:
   - Name: `Integrasi Web KPK`
   - Email/Login: `integrasi@kostputrikirana.test`
   - Tipe: Internal User
   - Hak akses:
     - Accounting/Invoicing: Invoicing
     - Purchase: User
     - Contact: Creation
   - Tidak diberi hak Administration/Settings.
   - Timezone: `Asia/Jakarta`
   - Simpan, lalu pilih **Actions > Change Password** dan buat password sementara.
5. Buka jendela privat browser. Login sebagai `integrasi@kostputrikirana.test`, lalu buka menu profil kanan atas > **My Preferences** > tab **Security** > **Add API Key**.
   - Description: `Web Kost Putri Kirana`
   - Duration: 3 bulan (pilihan terlama)
   - Konfirmasi dengan password pengguna integrasi.
   - Salin API key yang muncul. Key hanya tampil sekali.
6. Tempel key ke `.env` proyek Laravel: `ODOO_API_KEY=<key>`. Isi juga `ODOO_API_KEY_EXPIRES_AT=<tanggal kedaluwarsa, format YYYY-MM-DD>`. Jangan kirim key ke chat atau commit.
7. Uji koneksi dari PowerShell di folder proyek:

   ```powershell
   $key = (Select-String -Path .env -Pattern '^ODOO_API_KEY=(.+)$').Matches[0].Groups[1].Value
   Invoke-RestMethod -Method Post -Uri "http://localhost:8069/json/2/res.users/context_get" `
     -Headers @{ Authorization = "bearer $key"; "X-Odoo-Database" = "kpk_odoo" } `
     -ContentType "application/json" -Body "{}"
   ```

   Bila berhasil, keluar data `lang`, `tz` (`Asia/Jakarta`), dan `uid` milik pengguna integrasi. Bila muncul `401` atau `Invalid apikey`, ulangi langkah 5 dan 6.

## 5. Catatan keamanan

- Master password, password basis data (`docker/odoo/.env`), dan API key tidak di-commit dan tidak dikirim lewat chat. `.gitignore` sudah mengecualikan `docker/odoo/.env` dan `docker/odoo/config/odoo.conf`.
- API key berlaku maksimal 3 bulan. Catat tanggal kedaluwarsanya di `ODOO_API_KEY_EXPIRES_AT` dan buat ulang key sebelum tanggal itu.
- API key hanya disimpan di `.env`; tidak boleh dikirim ke browser, ditulis ke log, atau disimpan di basis data (NFR-03).
- `list_db = True` di `odoo.conf` diperlukan untuk membuat basis data lewat browser. Matikan (`list_db = False`) saat deployment, dan pasang `proxy_mode = True` bila Odoo berada di belakang reverse proxy.
- Pengguna integrasi sengaja tidak diberi hak Administration/Settings (hak minimum).
