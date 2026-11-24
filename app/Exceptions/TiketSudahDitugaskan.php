<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Tiket sudah tidak berstatus diajukan: sudah ditugaskan (mungkin oleh permintaan lain yang bersamaan).
 * Pesannya siap ditampilkan ke pengelola.
 */
class TiketSudahDitugaskan extends RuntimeException
{
    public const PESAN = 'Tiket ini sudah ditugaskan. Daftar sudah diperbarui.';

    public static function sudahDitugaskan(): self
    {
        return new self(self::PESAN);
    }
}
