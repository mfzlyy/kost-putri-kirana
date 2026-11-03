<?php

namespace App\Enums;

enum StatusSinkronisasi: string
{
    case Menunggu = 'menunggu';
    case Terkirim = 'terkirim';
    case Gagal = 'gagal';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Terkirim => 'Terkirim',
            self::Gagal => 'Gagal',
        };
    }
}
