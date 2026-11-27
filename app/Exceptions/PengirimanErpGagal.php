<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pengiriman ke Odoo tidak bisa dilanjutkan karena aturan bisnis (misalnya faktur dibatalkan atau ganda).
 * Pesannya disimpan apa adanya di sinkronisasi_erp.pesan_error, jadi tidak boleh memuat data pribadi.
 */
class PengirimanErpGagal extends RuntimeException {}
