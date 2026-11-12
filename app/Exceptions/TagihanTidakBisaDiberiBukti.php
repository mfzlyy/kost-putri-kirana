<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Tagihan tidak lagi berstatus belum dibayar, jadi bukti baru tidak boleh diunggah (F-12 butir 4).
 * Pesannya siap ditampilkan ke penghuni.
 */
class TagihanTidakBisaDiberiBukti extends RuntimeException
{
    public const PESAN = 'Tagihan ini sedang dicek atau sudah lunas, jadi tidak bisa diberi bukti lain.';

    public static function statusTidakSesuai(): self
    {
        return new self(self::PESAN);
    }
}
