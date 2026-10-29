# Web Pengelolaan Kost Putri Kirana

Sistem pengelolaan kost 8 kamar di Cikarang Utara yang terintegrasi dengan Odoo Community 19.

## Stack Teknologi

- **Backend**: Laravel 13 (PHP 8.4.27)
- **Frontend**: Livewire 4.4.8, Flux UI, Tailwind CSS
- **Database**: PostgreSQL 17 (Docker)
- **Testing**: Pest
- **Authentication**: Laravel Fortify

## Persyaratan Sistem

- PHP 8.4+ dengan ekstensi: `pdo_pgsql`, `pgsql`
- Node.js 18+
- Docker & Docker Compose
- Composer 2.x

## Setup Proyek

1. Clone repository:
```bash
git clone <repository-url>
cd kost-putri-kirana
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Setup environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Jalankan Docker PostgreSQL:
```bash
docker compose up -d
```

5. Migrasi database:
```bash
php artisan migrate:fresh --seed
```

6. Build frontend assets:
```bash
npm run build
```

## Menjalankan Aplikasi

### Development (Server + Queue + Log + Vite):
```bash
composer run dev
```

### Atau manual:
```bash
# Laravel server
php artisan serve

# Vite dev server (terminal terpisah)
npm run dev

# Queue worker (terminal terpisah)
php artisan queue:work
```

## Testing

```bash
# Semua tes
php artisan test

# Tes spesifik
php artisan test --filter=NamaTes

# Format kode
vendor/bin/pint --dirty
```

## Database

- **Development**: `kost_putri_kirana` (port 5433)
- **Testing**: `kost_putri_kirana_test` (port 5433)
- **User**: `kpk`

Container PostgreSQL: `kpk-postgres`

## Perintah Berguna

```bash
# Rebuild database dengan data contoh
php artisan migrate:fresh --seed

# Lihat daftar route
php artisan route:list

# Akses PostgreSQL CLI
docker exec -it kpk-postgres psql -U kpk -d kost_putri_kirana

# Stop Docker
docker compose down

# Stop Docker + hapus volume
docker compose down -v
```

## Struktur Proyek

- `app/` - Kode aplikasi (Models, Livewire, Services)
- `database/` - Migrasi, seeder, factory
- `docs/` - Dokumentasi (PRD, BRD, ERD, diagram)
- `resources/` - Views, CSS, JS
- `routes/` - Definisi route
- `tests/` - Unit & feature tests

## Catatan PHP

Proyek ini menggunakan PHP 8.4 dari `C:\php84\` karena memiliki ekstensi PostgreSQL yang diperlukan.

Jika menggunakan Herd Lite atau PHP lain yang tidak memiliki ekstensi PostgreSQL, gunakan wrapper `artisan.bat`:
```bash
.\artisan.bat migrate
```

Atau gunakan path lengkap:
```bash
C:\php84\php.exe artisan migrate
```

## Dokumentasi

- **PRD**: `docs/PRD.md` - Product Requirements (fitur F-01 s.d. F-29)
- **BRD**: `docs/BRD.md` - Business Requirements (aturan bisnis ATB-xx)
- **ERD**: `docs/erd/erd_kost_putri_kirana.sql` - Skema database
- **Design**: `docs/design/DESIGN.md` - Panduan desain UI

## Status

✅ Setup Laravel 13 + PostgreSQL  
✅ Docker PostgreSQL 17  
✅ Database development & testing  
✅ Livewire 4 starter kit  
✅ Testing framework (Pest)  
✅ 34 tes lulus  

Siap untuk development fitur!
