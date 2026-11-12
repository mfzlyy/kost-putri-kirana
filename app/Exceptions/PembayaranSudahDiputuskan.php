<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Bukti bayar sudah tidak berstatus menunggu: sudah diterima atau ditolak (mungkin oleh permintaan lain
 * yang bersamaan). Pesannya siap ditampilkan ke pengelola.
 */
class PembayaranSudahDiputuskan extends RuntimeException
{
    public const PESAN = 'Bukti ini sudah diputuskan. Daftar sudah diperbarui.';

    public static function sudahDiputuskan(): self
    {
        return new self(self::PESAN);
    }
}
