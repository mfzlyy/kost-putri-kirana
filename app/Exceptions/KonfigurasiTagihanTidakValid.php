<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Nilai kirana.tagihan.parsial (TAGIHAN_PARSIAL di .env) bukan "penuh" atau "prorata".
 */
class KonfigurasiTagihanTidakValid extends RuntimeException
{
    public function __construct(mixed $nilai)
    {
        $teks = is_scalar($nilai) ? (string) $nilai : get_debug_type($nilai);

        parent::__construct("TAGIHAN_PARSIAL \"{$teks}\" tidak dikenal. Isi dengan \"penuh\" atau \"prorata\" di .env.");
    }
}
