-- Dijalankan sekali oleh image postgres resmi saat volume data masih kosong.
-- Membuat basis data terpisah untuk pengujian (php artisan test), dimiliki oleh user kpk.
CREATE DATABASE kost_putri_kirana_test OWNER kpk;
