<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Nomor tiket bentrok dua kali berturut-turut, jadi keluhan tidak tersimpan. Pesannya siap ditampilkan ke penghuni.
 */
class KeluhanGagalDikirim extends RuntimeException
{
    public const PESAN = 'Keluhan gagal dikirim. Coba lagi.';

    public function __construct(?\Throwable $sebelumnya = null)
    {
        parent::__construct(self::PESAN, 0, $sebelumnya);
    }
}
