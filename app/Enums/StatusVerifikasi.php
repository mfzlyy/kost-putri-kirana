<?php

namespace App\Enums;

enum StatusVerifikasi: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }
}
