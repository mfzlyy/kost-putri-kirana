# Dokumen Teknis Integrasi Web–Odoo

Acuan Sprint 3 untuk F-22 s.d. F-25. Dirujuk PRD Bagian 8. Semua contoh payload di bawah terbukti berhasil pada instance Odoo 19 lewat `php artisan odoo:uji-alur` (KPK-50, 3 November 2026). Referensi uji `KPK-UJI-` diganti bentuk aslinya.

## 1. Ringkasan

| Item | Nilai |
|---|---|
| Odoo | Community 19.0, image `odoo:19.0` (`Odoo Server 19.0-20261102`) |
| Digest image | `odoo@sha256:5e755c725b3c43cf0be16d579904e1418300db3a2c44952f928eaddbcb0cc16d` |
| Basis data | `kpk_odoo`, mata uang perusahaan IDR, lokalisasi `l10n_id` |
| Endpoint | `POST {ODOO_URL}/json/2/<model>/<method>` |
| Header | `Authorization: bearer {ODOO_API_KEY}`, `X-Odoo-Database: {ODOO_DB}`, `Content-Type: application/json`, `User-Agent: KostPutriKirana/1.0` |
| Body | Objek JSON berisi argumen bernama method. Body kosong dikirim sebagai `{}`, bukan `[]` |
| Klien | `App\Services\Odoo\OdooClient` (satu-satunya pemanggil HTTP), konfigurasi di `config/services.php` entri `odoo` |
| Pengguna integrasi | `integrasi@kostputrikirana.test` (uid 5), pengguna internal |
| Hak akses | Role User; Accounting / Invoicing; Purchase / User; Contact / Creation; Maintenance / Equipment Manager. Odoo juga melaporkan grup Multi Currencies dan Technical Features pada pengguna ini. Tidak ada hak Administration/Settings |
| Masa berlaku API key | Maksimal 3 bulan. Tanggal kedaluwarsa di `ODOO_API_KEY_EXPIRES_AT` (saat verifikasi: 2027-02-03, sisa 92 hari). `php artisan odoo:periksa` memberi PERINGATAN dan exit code 1 bila sisa kurang dari 14 hari |

Argumen body mengikuti nama parameter method Odoo:

| Method | Argumen |
|---|---|
| `create` | `vals_list` (daftar nilai). Balasan: daftar ID, mis. `[7]` |
| `write` | `ids`, `vals`. Balasan: `true` |
| `read` | `ids`, `fields` |
| `search_read` | `domain`, `fields`, `limit`, `order` |
| `fields_get` | `attributes` |
| method aksi (`action_post`, ...) | `ids` |
| semua | `context` (opsional) |

Setiap panggilan JSON-2 berjalan dalam transaksi Odoo tersendiri. Karena itu satu transaksi bisnis (mis. buat faktur, posting, bayar) terdiri dari beberapa transaksi terpisah, dan kegagalan di tengah menyisakan catatan setengah jadi. Pemulihannya dijelaskan di bagian 3.

## 2. Transaksi

### 2.1 Pembayaran sewa (F-22)

**Pemicu di web:** pengelola menerima bukti bayar (`pembayaran.status_verifikasi = diterima`); baris `sinkronisasi_erp` dengan `jenis_transaksi = pembayaran` dibuat dan dikirim lewat queue.

**Referensi unik:** `KPK-PAY-` + `pembayaran.id` 6 digit, mis. `KPK-PAY-000123`.

**Urutan panggilan**

1. `res.partner/search_read` untuk partner penghuni; bila belum ada, `res.partner/create`.
2. `account.move/search_read` (cek idempotensi). Bila faktur sudah ada, lompat ke langkah pemulihan (bagian 3).
3. `account.move/create` (faktur pelanggan).
4. `account.move/action_post`.
5. `account.journal/search_read` (jurnal bank). ID jurnal boleh di-cache; saat verifikasi jurnal bank adalah #6.
6. `account.payment.register/create` dengan context.
7. `account.payment.register/action_create_payments` dengan context.
8. `account.move/read` untuk membaca status.

**Partner penghuni** (hanya ID yang dikirim, tanpa identitas lain):

```json
POST /json/2/res.partner/search_read
{"domain": [["ref", "=", "PNH-9001"]], "fields": ["name", "ref"], "limit": 1}

POST /json/2/res.partner/create
{"vals_list": [{"ref": "PNH-9001", "name": "Penghuni #9001", "customer_rank": 1}]}
```

**Cek idempotensi:**

```json
POST /json/2/account.move/search_read
{"domain": [["move_type", "=", "out_invoice"], ["ref", "=", "KPK-PAY-000123"]],
 "fields": ["state", "payment_state", "amount_total"], "limit": 1}
```

**Faktur pelanggan** (`tax_ids` wajib dikosongkan, lihat bagian 3):

```json
POST /json/2/account.move/create
{"vals_list": [{
  "move_type": "out_invoice",
  "partner_id": 7,
  "ref": "KPK-PAY-000123",
  "invoice_date": "2026-11-01",
  "invoice_date_due": "2026-11-10",
  "invoice_line_ids": [[0, 0, {
    "name": "Sewa kos periode 2026-11",
    "quantity": 1,
    "price_unit": 1400000,
    "tax_ids": [[6, 0, []]]
  }]]
}]}

POST /json/2/account.move/action_post
{"ids": [1]}
```

**Pembayaran** (wizard, bukan model biasa; context wajib):

```json
POST /json/2/account.payment.register/create
{"vals_list": [{"payment_date": "2026-11-03", "journal_id": 6, "communication": "KPK-PAY-000123"}],
 "context": {"active_model": "account.move", "active_ids": [1]}}

POST /json/2/account.payment.register/action_create_payments
{"ids": [1],
 "context": {"active_model": "account.move", "active_ids": [1], "dont_redirect_to_payments": true}}
```

**Status dibaca kembali:**

```json
POST /json/2/account.move/read
{"ids": [1], "fields": ["state", "payment_state", "amount_total", "amount_residual", "ref"]}
→ [{"state": "posted", "payment_state": "paid", "amount_total": 1400000.0, "amount_residual": 0.0, "ref": "KPK-PAY-000123"}]
```

Arti **terkirim**: faktur ditemukan atau dibuat dengan `state = posted` dan `payment_state = paid`. `odoo_record_id` diisi `account.move.id` faktur.

**Pemetaan kolom web → field Odoo**

| Kolom web (ERD) | Field Odoo | Catatan |
|---|---|---|
| `penghuni.id` | `res.partner.ref` = `PNH-<id>`, `res.partner.name` = `Penghuni #<id>` | Identitas lain tidak dikirim |
| `tagihan.periode` | `account.move.invoice_date` dan teks baris `Sewa kos periode YYYY-MM` | Periode selalu tanggal 1 |
| `tagihan.jatuh_tempo` | `account.move.invoice_date_due` | |
| `tagihan.nominal` | `account.move.line.price_unit` | `quantity` 1, `tax_ids` kosong |
| `pembayaran.tanggal_bayar` | `account.payment.register.payment_date` | |
| `pembayaran.nominal_bayar` | `account.payment.register.amount` (opsional) | Bila tidak diisi, wizard melunasi sisa faktur; web selalu mengisinya. `nominal_bayar` harus sama dengan `nominal` (keputusan di bagian 8.1) |
| `pembayaran.id` | `account.move.ref` dan `account.payment.register.communication` = `KPK-PAY-<id 6 digit>` | Setelah dibayar, `communication` tersimpan di `account.payment.memo` |
| `pembayaran.status_verifikasi` | tidak dikirim sebagai field | Pengiriman hanya dipicu status `diterima` |

### 2.2 Biaya perbaikan (F-23)

**Pemicu di web:** tiket berstatus `selesai` dengan `biaya_perbaikan` terisi (ATB-09). Baris `sinkronisasi_erp` `jenis_transaksi = biaya_perbaikan`.

**Referensi unik:** `KPK-TKT-` + angka nomor tiket + `-B`, mis. tiket `KLH-0001` menjadi `KPK-TKT-0001-B`.

**Urutan panggilan:** cari/buat partner vendor → cek idempotensi → `account.move/create` → `account.move/action_post` → `account.move/read`.

```json
POST /json/2/res.partner/create
{"vals_list": [{"ref": "VDR-9001", "name": "Vendor Uji KPK", "supplier_rank": 1}]}

POST /json/2/account.move/search_read
{"domain": [["move_type", "=", "in_invoice"], ["ref", "=", "KPK-TKT-0001-B"]],
 "fields": ["state", "payment_state", "amount_total"], "limit": 1}

POST /json/2/account.move/create
{"vals_list": [{
  "move_type": "in_invoice",
  "partner_id": 8,
  "ref": "KPK-TKT-0001-B",
  "invoice_date": "2026-11-03",
  "invoice_line_ids": [[0, 0, {
    "name": "Perbaikan AC tidak dingin - AC Kamar 03",
    "quantity": 1,
    "price_unit": 150000,
    "tax_ids": [[6, 0, []]]
  }]]
}]}

POST /json/2/account.move/action_post
{"ids": [3]}
```

Status dibaca kembali: `{"state": "posted", "amount_total": 150000.0, "partner_id": [8, "Vendor Uji KPK"]}`. Arti **terkirim**: tagihan vendor ada dengan `state = posted`. `odoo_record_id` = `account.move.id`.

Partner vendor: bila `vendor.odoo_partner_id` kosong, cari berdasarkan `ref = VDR-<vendor.id>`, buat bila belum ada, lalu simpan ID-nya ke `vendor.odoo_partner_id`.

**Pemetaan kolom web → field Odoo**

| Kolom web (ERD) | Field Odoo |
|---|---|
| `vendor.odoo_partner_id` (atau `vendor.id`) | `partner_id` (`res.partner.ref` = `VDR-<id>`) |
| `vendor.nama_vendor` | `res.partner.name` |
| `tiket_keluhan.biaya_perbaikan` | `account.move.line.price_unit` |
| `tiket_keluhan.jenis_kerusakan`, `fasilitas.nama_fasilitas`, `fasilitas.lokasi` | `account.move.line.name`, mis. `Perbaikan AC tidak dingin - AC Kamar 03` |
| waktu status `selesai` di `riwayat_status_tiket.waktu_perubahan` | `account.move.invoice_date` |
| `tiket_keluhan.nomor_tiket` | `account.move.ref` = `KPK-TKT-<nomor>-B` |

### 2.3 Pemeliharaan (F-24)

**Pemicu di web:** tiket `selesai` pada fasilitas yang punya `fasilitas.odoo_equipment_id`. Baris `sinkronisasi_erp` `jenis_transaksi = pemeliharaan`.

**Referensi unik:** `KPK-TKT-0001-M`, ditaruh di awal `maintenance.request.name`.

**Urutan panggilan:** cek idempotensi → `maintenance.request/create` → `maintenance.stage/search_read` (tahap `done = true`) → `maintenance.request/write` → `maintenance.request/read`.

```json
POST /json/2/maintenance.request/search_read
{"domain": [["name", "=like", "KPK-TKT-0001-M%"]], "fields": ["name"], "limit": 1}

POST /json/2/maintenance.request/create
{"vals_list": [{
  "name": "KPK-TKT-0001-M AC tidak dingin",
  "equipment_id": 1,
  "maintenance_type": "corrective",
  "request_date": "2026-11-03",
  "description": "Freon diisi ulang. Biaya Rp150.000."
}]}

POST /json/2/maintenance.stage/search_read
{"domain": [["done", "=", true]], "fields": ["name"], "limit": 1, "order": "sequence, id"}

POST /json/2/maintenance.request/write
{"ids": [1], "vals": {"stage_id": 3}}
```

Status dibaca kembali: `{"name": "KPK-TKT-0001-M AC tidak dingin", "equipment_id": [1, "KPK-UJI AC Kamar 03"], "stage_id": [3, "Repaired"], "maintenance_team_id": [1, "Internal Maintenance"]}`, dan `maintenance.stage` #3 memiliki `done = true`. Arti **terkirim**: permintaan ada dan berada di tahap dengan `done = true`.

Pencarian idempotensi memakai `=like` pada awal `name`, jadi `KPK-TKT-0001-M` tidak boleh menjadi awalan nomor lain (nomor tiket berformat tetap 4 digit, aman sampai 9999).

**Pemetaan kolom web → field Odoo**

| Kolom web (ERD) | Field Odoo |
|---|---|
| `fasilitas.odoo_equipment_id` | `equipment_id` |
| `tiket_keluhan.nomor_tiket` + `jenis_kerusakan` | `name` = `KPK-TKT-<nomor>-M <jenis_kerusakan>` |
| `tiket_keluhan.status_tiket = selesai` | `stage_id` ke tahap `done = true` |
| waktu status `selesai` | `request_date` |
| ringkasan perbaikan dan biaya | `description` |
| (tetap) | `maintenance_type = corrective` |

Peralatan (`maintenance.equipment`) dibuat oleh pengelola/F-28, bukan oleh integrasi. Pada verifikasi, peralatan uji dibuat langsung (`{"name": "KPK-UJI AC Kamar 03"}`) dan hak Equipment Manager mencukupi.

## 3. Temuan verifikasi

| Temuan | Rincian |
|---|---|
| `payment_state` | Setelah `action_create_payments`, faktur langsung `payment_state = paid`, `amount_residual = 0` (bukan `in_payment`). Pembayaran terbentuk di `account.payment` (`PBNK1/2026/00001`, `state = paid`, `payment_type = inbound`, `memo = KPK-PAY-...`) pada jurnal Bank. |
| Pajak | **`tax_ids` wajib dikosongkan** (`[[6, 0, []]]`). Perusahaan memiliki pajak bawaan penjualan dan pembelian `12% (Non-Luxury Good)` (`account_sale_tax_id` #1, `account_purchase_tax_id` #2), sehingga tanpa pengosongan Odoo menambah pajak otomatis dan `amount_total` tidak sama dengan nominal tagihan. Dengan `tax_ids` kosong, `amount_total` = 1.400.000 persis. |
| Tagihan bernilai 0 | **Diterima**: tagihan vendor `price_unit = 0` berhasil dibuat dan di-post (`state = posted`, `amount_total = 0`). Jadi F-20 butir 2 (biaya nol atau lebih) tidak terhalang di sisi Odoo. Pertimbangkan apakah tagihan bernilai 0 memang perlu dikirim. |
| Posting ulang | `action_post` pada faktur yang sudah `posted` ditolak (HTTP 422 `UserError`: "must be in draft"). Karena itu pengiriman ulang tidak boleh memanggil `action_post` membabi buta. |
| Pemulihan pengiriman setengah jadi | Setiap panggilan adalah transaksi terpisah, jadi faktur bisa ada tetapi belum diposting atau belum dibayar. Saat referensi ditemukan: bila `state = draft` → `action_post`; bila `posted` dan `payment_state` bukan `paid` → buat pembayaran; bila sudah `paid` → langsung tandai `terkirim`. Ini melengkapi aturan "cek referensi dulu, jangan buat ganda" di CLAUDE.md. |
| Hak akses | Tidak ada hak yang kurang: ketiga alur berjalan dengan hak pengguna integrasi saat ini. Pengguna ini tidak berhak mengakses `ir.config_parameter`, `ir.cron`, dan `ir.mail_server` (HTTP 403). Berbeda dari dugaan awal, `ir.module.module` ternyata dapat dibaca; pengecekan modul tetap sebaiknya lewat `fields_get`. |
| Header bearer | Dibaca tanpa membedakan huruf besar-kecil; klien Laravel mengirim `Bearer`. |
| Waktu panggilan | Jalan pertama (27 panggilan): rata-rata 38 ms, terlama 305 ms (`create` faktur). Jalan kedua (12 panggilan): rata-rata 10 ms. Satu transaksi pembayaran lengkap ± 0,6 detik. |
| Idempotensi | Jalan kedua `odoo:uji-alur` menemukan semua catatan lewat referensi dan tidak membuat satu pun catatan baru. |
| Berbeda dari CLAUDE.md | (1) `tax_ids` kosong wajib. (2) `account.payment.register` adalah wizard yang butuh context (`active_model`, `active_ids`) dan `dont_redirect_to_payments`. (3) Pemulihan faktur setengah jadi (baris di atas). (4) Format `res.partner.ref` dan nama partner kini `PNH-<id>`/`Penghuni #<id>` dan `VDR-<id>` (CLAUDE.md diperbarui). |

## 4. Penanganan galat

`OdooClient` melempar `OdooException` untuk status 4xx/5xx dan koneksi gagal. Pesannya hanya memuat `name` dan `message` dari Odoo plus status HTTP, tanpa `debug` (traceback), `arguments`, API key, atau header, diubah menjadi satu baris dan dipotong 500 karakter (+ `...`).

| Situasi | Balasan Odoo | Pesan `OdooException` (= `sinkronisasi_erp.pesan_error`) |
|---|---|---|
| API key salah/kedaluwarsa | HTTP 401 `{"name": "werkzeug.exceptions.Unauthorized", "message": "Invalid apikey", ...}` | `Odoo menjawab HTTP 401: werkzeug.exceptions.Unauthorized: Invalid apikey` |
| Hak akses kurang | HTTP 403 `{"name": "odoo.exceptions.AccessError", "message": "You are not allowed to access 'System Parameter' (ir.config_parameter) records. ...", ...}` | `Odoo menjawab HTTP 403: odoo.exceptions.AccessError: You are not allowed to access 'System Parameter' (ir.config_parameter) records. This operation is allowed for the following groups: - Role / Administrator Contact ...` |
| Model tidak ada | HTTP 404 `werkzeug.exceptions.NotFound` | `Odoo menjawab HTTP 404: werkzeug.exceptions.NotFound: the model 'x' does not exist` |
| Aturan bisnis Odoo | HTTP 422 `odoo.exceptions.UserError` | `Odoo menjawab HTTP 422: odoo.exceptions.UserError: The entry INV/2026/00001 (id 1) must be in draft.` |
| Field/argumen salah | HTTP 500 `builtins.ValueError` | `Odoo menjawab HTTP 500: builtins.ValueError: Invalid field 'x' on 'account.move'` |
| Odoo mati / timeout | tidak ada balasan | `Odoo tidak dapat dihubungi: cURL error 28: Operation timed out ...` |
| Konfigurasi kosong | tidak ada request | `Konfigurasi Odoo belum lengkap.` |

**Pemetaan ke web:** pengecualian ditangkap job pengiriman. Baris `sinkronisasi_erp` diubah menjadi `status_sinkronisasi = gagal`, `pesan_error` = pesan di atas (kolom `text`; panjang sudah dibatasi 503 karakter oleh klien), `waktu_kirim` = waktu percobaan. Transaksi web (penerimaan pembayaran, penyelesaian tiket) tidak dibatalkan. Pengelola dapat kirim ulang (F-25); `odoo_record_id` baru diisi setelah berhasil. Status 401 menandakan API key perlu dirotasi, dan pesan galat itu harus terlihat jelas oleh pengelola.

Untuk pengiriman pembayaran, `pesan_error` yang disimpan web diringkas lagi; lihat bagian 8.4.

## 5. Bahan ISU-10 (laporan keuangan)

Angka November 2026 dari data uji (`odoo:uji-alur`, hanya faktur berstatus posted dengan `invoice_date` pada bulan itu):

| Ukuran | Nilai |
|---|---|
| Pemasukan (faktur pelanggan) | Rp1.400.000 |
| Pengeluaran (tagihan vendor) | Rp150.000 |
| Selisih | Rp1.250.000 |

Menu laporan yang dapat dibuka pengguna integrasi (gambaran, bukan penilaian kecukupan):

- Invoicing / Reporting
- Invoicing / Reporting / Management / Analytic Report
- Invoicing / Reporting / Management / Invoice Analysis
- Invoicing / Reporting / Partner Reports
- Invoicing / Reporting / Statement Reports
- Invoicing / Reporting / Taxes & Fiscal
- Maintenance / Reporting / Losses Analysis, Maintenance Requests Analysis, Overall Equipment Effectiveness (OEE)
- Purchase / Reporting / Purchase

Daftar ini tidak memuat laporan berjudul Profit and Loss atau Balance Sheet. Daftar hanya mencerminkan menu yang terlihat oleh pengguna integrasi; pengguna dengan hak Accounting yang lebih tinggi bisa melihat lebih banyak. Penilaian akhir, apakah laporan bawaan Invoicing sudah cukup atau perlu `account_financial_report` dari OCA, diputuskan tim dan dikerjakan manual. Modul pihak ketiga belum dipasang.

## 6. Catatan data uji

- Semua catatan berawalan `KPK-UJI-` di `kpk_odoo` adalah data uji dan tidak dihapus: partner #7 (`PNH-9001`) dan #8 (`VDR-9001`); faktur pelanggan #1 (`INV/2026/00001`, ref `KPK-UJI-PAY-000001`) beserta pembayaran `PBNK1/2026/00001`; tagihan vendor #3 (`KPK-UJI-TKT-0001-B`, Rp150.000) dan #4 (`KPK-UJI-TKT-0002-B`, Rp0); peralatan #1 `KPK-UJI AC Kamar 03`; permintaan perawatan #1 (`KPK-UJI-TKT-0001-M`).
- Basis data Odoo untuk deployment dibuat baru, tanpa data uji.

## 7. Alat bantu

| Perintah | Fungsi |
|---|---|
| `php artisan odoo:periksa` | Hanya membaca. Cek koneksi, pengguna dan grup, model dan field yang dibutuhkan, jurnal, mata uang, dan masa berlaku API key. Exit 1 bila ada GAGAL atau API key kurang dari 14 hari |
| `php artisan odoo:uji-alur --ya` | Hanya `APP_ENV=local`. Membuat catatan uji `KPK-UJI-` dan menunjukkan bahwa menjalankannya ulang tidak menggandakan catatan |
| `php artisan erp:kirim-tertunda` | Mengirim pembayaran yang masih `menunggu` (bagian 8.5) |
| `tests/Feature/Odoo`, `tests/Feature/Integrasi` | Tes dengan `Http::fake()`; tidak memanggil Odoo sungguhan. `tests/Pest.php` memasang `Http::preventStrayRequests()` untuk semua tes Feature |

## 8. Implementasi F-22

### 8.1 Alur kelas

```
Pembayaran::terima()  (DB::transaction)
  └─ SinkronisasiErp dibuat (jenis pembayaran, status menunggu)
       └─ event created → KirimSinkronisasiErp::dispatch()->afterCommit()
            └─ antrean database, satu worker
                 └─ IntegrasiErpService::kirim()
                      └─ OdooClient → POST /json/2/<model>/<method>
```

| Kelas | Tugas |
|---|---|
| `App\Models\SinkronisasiErp` | Memasukkan job saat baris pembayaran berstatus `menunggu` dibuat. Job baru masuk antrean setelah transaksi commit; transaksi yang dibatalkan tidak meninggalkan job |
| `App\Jobs\KirimSinkronisasiErp` | Unik per baris (kunci 10 menit), satu kali percobaan. `failed()` menandai `gagal` untuk galat yang lolos dari service |
| `App\Services\Odoo\IntegrasiErpService` | Kunci per baris (bagian 8.6), urutan panggilan, pemeriksaan kecocokan faktur, pemulihan, dan pencatatan hasil. Hanya baris `sinkronisasi_erp` yang diubah; `pembayaran` dan `tagihan` tidak disentuh, dan tidak ada panggilan Odoo di dalam transaksi web |
| `App\Services\Odoo\OdooClient` | Satu-satunya pemanggil HTTP |

Hanya baris `menunggu` dan `gagal` yang diproses; baris `terkirim` tidak memanggil Odoo. Baris `biaya_perbaikan` dan `pemeliharaan` belum dikirim dan tetap `menunggu`.

Yang dikirim ke Odoo hanya ID penghuni (di `ref` dan nama partner), referensi, periode, jatuh tempo, nominal, dan tanggal bayar. `amount` pada wizard selalu diisi `pembayaran.nominal_bayar`, yang sudah diputuskan sama dengan `tagihan.nominal`; bila berbeda, baris ditandai `gagal` tanpa memanggil Odoo.

Hasil dicatat sebagai berikut. Berhasil: `status_sinkronisasi = terkirim`, `odoo_record_id` = `account.move.id`, `pesan_error` kosong. Gagal: `status_sinkronisasi = gagal`, `pesan_error` diisi, `odoo_record_id` tidak diubah. Keduanya mengisi `waktu_kirim` dengan waktu percobaan.

### 8.2 Langkah

| # | Panggilan | Kapan |
|---|---|---|
| 1 | `res.partner/search_read` (`ref = PNH-<id>`) | Selalu |
| 2 | `res.partner/create` | Partner belum ada |
| 3 | `account.move/search_read` (`out_invoice` + `ref`, `limit` 2) | Selalu |
| 4 | `res.partner/read` (`ref` partner pada faktur) | Faktur sudah ada dan punya partner |
| 5 | `account.move/create` | Faktur belum ada |
| 6 | `account.move/action_post` | Faktur `draft` |
| 7 | `account.move/read` | Setelah posting |
| 8 | `account.journal/search_read` (`type = bank`, `order = id asc`) | Faktur `not_paid`; ID diingat selama satu instance service |
| 9 | `account.payment.register/create` | Faktur `not_paid` |
| 10 | `account.payment.register/action_create_payments` | Faktur `not_paid` |
| 11 | `account.move/read` | Selalu; berhasil bila `posted` dan `paid` atau `in_payment` |

Pencarian faktur memakai `limit` 2 (contoh di bagian 2.1 memakai 1) agar faktur ganda terdeteksi, dan meminta `id`, `state`, `payment_state`, `amount_total`, `invoice_date`, `partner_id`. Langkah 4 dan 5 tidak pernah terjadi dalam pengiriman yang sama, jadi satu pengiriman paling banyak 10 panggilan.

### 8.3 Melanjutkan dari keadaan setengah jadi

| Keadaan faktur dengan referensi itu | Tindakan |
|---|---|
| Belum ada | Buat, posting, bayar |
| Lebih dari satu | `gagal`, periksa manual |
| `cancel` | `gagal`, periksa manual |
| Tidak cocok dengan data web (lihat di bawah) | `gagal`, periksa manual |
| Cocok, `draft` | Posting, bayar |
| Cocok, `posted`, `not_paid` | Bayar |
| Cocok, `posted`, `paid` atau `in_payment` | Langsung `terkirim` |
| Cocok, `partial` | `gagal`, periksa manual |

**Pemeriksaan kecocokan.** Bila basis data web dibangun ulang (`migrate:fresh`) sementara basis data Odoo tidak, ID pembayaran mulai dari awal lagi dan referensinya bisa sudah dipakai faktur lama. Karena itu faktur yang ditemukan, termasuk yang masih `draft`, dianggap milik pembayaran ini hanya bila ketiganya cocok:

| Beda | Syarat cocok |
|---|---|
| `penghuni` | `res.partner.ref` pada faktur = `PNH-<penghuni.id>` |
| `periode` | `invoice_date` = `tagihan.periode` |
| `nominal` | `amount_total` = `tagihan.nominal`, dibandingkan sebagai rupiah bulat |

Faktur yang tidak cocok tidak diposting, dibayar, atau diubah. Pemeriksaan ini tidak membandingkan tanggal bayar atau jatuh tempo: faktur lama yang penghuni, periode, dan nominalnya sama tetap dianggap milik pembayaran ini.

### 8.4 Daftar `pesan_error`

Paling panjang 500 karakter, tanpa traceback, API key, header, atau isi permintaan.

| Situasi | `pesan_error` |
|---|---|
| Konfigurasi kosong | `Konfigurasi Odoo belum lengkap.` |
| Koneksi gagal atau waktu habis | `Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala.` |
| HTTP 401 | `API key Odoo ditolak. Periksa ODOO_API_KEY dan masa berlakunya.` |
| Galat lain dari Odoo | `Odoo menolak: {name}: {message}` |
| Pembayaran tidak ada atau belum diterima | `Pembayaran tidak berstatus diterima.` |
| `nominal_bayar` berbeda dari `tagihan.nominal` | `Nominal pembayaran berbeda dengan nominal tagihan.` |
| Faktur ganda | `Ada lebih dari satu faktur {REF} di Odoo. Periksa manual.` |
| Faktur dibatalkan | `Faktur {REF} dibatalkan di Odoo. Periksa manual.` |
| Faktur tidak cocok dengan data web | `Faktur {REF} di Odoo tidak cocok dengan data web ({beda}). Kemungkinan sisa data uji sebelum reset basis data. Periksa manual.` dengan `{beda}` = satu atau lebih dari `penghuni, periode, nominal` |
| Faktur dibayar sebagian | `Faktur {REF} baru dibayar sebagian di Odoo. Periksa manual.` |
| Tidak ada jurnal bank | `Jurnal bank tidak ditemukan di Odoo.` |
| Belum lunas setelah semua langkah | `Faktur {REF} sudah dibuat tetapi belum lunas di Odoo (status {payment_state}).` |
| Galat tak terduga (dari job) | `Kesalahan tak terduga saat mengirim ke Odoo.` |

Log: `info` per keberhasilan (referensi dan ID faktur), `warning` per kegagalan (referensi dan `pesan_error`), `error` untuk galat tak terduga (referensi dan nama kelas exception).

### 8.5 Menjalankan antrean

- `.env`: `QUEUE_CONNECTION=database` dan `DB_QUEUE_RETRY_AFTER=300`. Nilai kedua harus lebih lama dari satu pengiriman terburuk (10 panggilan, masing-masing paling lama 30 detik).
- Pengembangan: `composer run dev` (menjalankan `php artisan dev`, yang sudah memuat `queue:listen --tries=1 --timeout=0`). Jalankan satu worker saja.
- Di Windows tanpa ekstensi PCNTL, timeout job tidak ditegakkan worker; batas yang berlaku adalah timeout HTTP `OdooClient` (30 detik).
- `php artisan erp:kirim-tertunda`: memasukkan baris pembayaran `menunggu` yang tertinggal ke antrean. Baris yang job-nya masih antre dilewati.
- `php artisan erp:kirim-tertunda --sekarang`: mengirim langsung tanpa worker dan mencetak `referensi | status | pesan_error`; exit code 1 bila ada yang gagal. Boleh dijalankan saat worker berjalan: baris yang sedang dikirim proses lain dilaporkan `sedang diproses, dilewati` (bagian 8.6) dan tidak mengubah exit code.
- Baris `gagal` tidak disentuh perintah ini; kirim ulang adalah F-25.

### 8.6 Kunci per baris

`IntegrasiErpService::kirim()` mengambil kunci atomik `sinkronisasi-erp:<id>` (`Cache::lock`, umur 330 detik) sebelum melakukan apa pun, jadi berlaku untuk semua jalur: worker, `--sekarang`, dan kirim ulang (F-25).

- Kunci tidak didapat: tidak ada panggilan Odoo dan baris tidak diubah. Event `sinkronisasi-erp.dilewati` dikirim dengan ID baris.
- Kunci didapat: baris dibaca ulang dari basis data; yang sudah `terkirim` selesai tanpa memanggil Odoo. Kunci dilepas di `finally`, juga saat gagal.
- Proses yang mati di tengah pengiriman meninggalkan kunci sampai 330 detik. Job antrean yang datang selama itu selesai tanpa mengirim; bila barisnya masih `menunggu`, jalankan `erp:kirim-tertunda`.
- Kunci disimpan di cache store aplikasi (`CACHE_STORE=database`; `array` saat tes). Semua proses harus memakai store yang sama; `array` tidak berlaku antarproses.
