<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Akun vendor yang dipilih nonaktif saat tiket ditugaskan. Pesannya siap ditampilkan ke pengelola.
 */
class VendorTidakAktif extends RuntimeException
{
    public const PESAN = 'Akun vendor ini nonaktif. Pilih vendor lain.';

    public static function nonaktif(): self
    {
        return new self(self::PESAN);
    }
}
