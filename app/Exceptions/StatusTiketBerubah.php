<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Status tiket sudah berubah dari yang diharapkan aksi vendor (mulai atau selesai), mungkin oleh permintaan lain
 * yang bersamaan atau halaman yang usang. Pesannya siap ditampilkan ke vendor.
 */
class StatusTiketBerubah extends RuntimeException
{
    public const PESAN = 'Status tiket ini sudah berubah. Halaman sudah diperbarui.';

    public static function sudahBerubah(): self
    {
        return new self(self::PESAN);
    }
}
