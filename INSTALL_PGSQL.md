# Setup Ekstensi PostgreSQL untuk PHP

## Masalah
PHP tidak memiliki ekstensi `pdo_pgsql` dan `pgsql` yang diperlukan untuk koneksi ke PostgreSQL.

## Solusi

### Untuk Windows dengan Herd Lite / PHP Standalone

1. **Download file DLL ekstensi PostgreSQL:**
   - Download PHP versi yang sama dengan yang terinstall (cek dengan `php -v`)
   - Dari https://windows.php.net/download/
   - Pilih "Thread Safe" zip
   - Extract file `php_pdo_pgsql.dll` dan `php_pgsql.dll` dari folder `ext`

2. **Copy file DLL ke folder ekstensi PHP:**
   ```powershell
   # Cek lokasi folder ekstensi
   php -i | Select-String "extension_dir"
   
   # Copy file dll ke folder tersebut (biasanya C:\php\ext)
   # Atau untuk Herd Lite, cari folder PHP Herd
   ```

3. **Aktifkan ekstensi di php.ini:**
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```
   
   File php.ini untuk Herd Lite ada di: `C:\Users\<username>\.config\herd-lite\bin\php.ini`

4. **Restart server dan verifikasi:**
   ```powershell
   # Cek apakah ekstensi sudah aktif
   php -m | Select-String -Pattern "pdo_pgsql"
   
   # Harusnya muncul:
   # pdo_pgsql
   # pgsql
   ```

### Alternatif: Gunakan XAMPP atau Laragon

Kedua aplikasi ini sudah include ekstensi PostgreSQL secara default. Tinggal diaktifkan di php.ini:
- XAMPP: `C:\xampp\php\php.ini`
- Laragon: `C:\laragon\bin\php\php<version>\php.ini`

Uncomment atau tambahkan:
```ini
extension=pdo_pgsql
extension=pgsql
```

## Setelah Ekstensi Terinstall

Jalankan migrasi:
```bash
php artisan migrate:fresh
php artisan test
```

Semua tes harus lulus.
