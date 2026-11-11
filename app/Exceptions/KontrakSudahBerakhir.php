<?php

namespace App\Exceptions;

use DomainException;

/**
 * Dilempar saat kontrak yang sudah berakhir diakhiri atau diperpanjang lagi (F-07).
 */
class KontrakSudahBerakhir extends DomainException
{
    public function __construct(string $message = 'Kontrak ini sudah berakhir.')
    {
        parent::__construct($message);
    }
}
