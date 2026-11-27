<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Baris sinkronisasi sudah tidak berstatus gagal: sudah dikirim ulang atau terkirim, mungkin oleh proses lain.
 * Pesannya siap ditampilkan ke pengelola.
 */
class SinkronisasiTidakBisaDikirimUlang extends RuntimeException
{
    public const PESAN = 'Status transaksi ini sudah berubah. Daftar sudah diperbarui.';

    public static function statusBerubah(): self
    {
        return new self(self::PESAN);
    }
}
