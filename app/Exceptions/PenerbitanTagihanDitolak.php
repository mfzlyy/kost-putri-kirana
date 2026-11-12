<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Penerbitan tagihan (F-10) tidak boleh dilanjutkan. Pesannya siap ditampilkan ke pengelola.
 */
class PenerbitanTagihanDitolak extends RuntimeException
{
    public static function jatuhTempoBelumDiatur(): self
    {
        return new self('Atur tanggal jatuh tempo di Pengaturan dulu.');
    }

    public static function periodeDiLuarRentang(): self
    {
        return new self('Periode di luar rentang: pilih dari 12 bulan lalu sampai bulan depan.');
    }
}
