<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case Pemilik = 'pemilik';
    case Pengelola = 'pengelola';
    case Penghuni = 'penghuni';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Pemilik => 'Pemilik',
            self::Pengelola => 'Pengelola',
            self::Penghuni => 'Penghuni',
            self::Vendor => 'Vendor',
        };
    }
}
