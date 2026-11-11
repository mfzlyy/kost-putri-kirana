<?php

namespace App\Exceptions;

use DomainException;

/**
 * Dilempar saat kontrak baru bentrok dengan kontrak aktif (ATB-01, ASM-04).
 * $field menunjuk isian form yang harus menampilkan pesannya: kamar atau penghuni.
 */
class KontrakBentrok extends DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
