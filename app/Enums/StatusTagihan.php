<?php

namespace App\Enums;

enum StatusTagihan: string
{
    case BelumDibayar = 'belum_dibayar';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Lunas = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::BelumDibayar => 'Belum dibayar',
            self::MenungguVerifikasi => 'Menunggu verifikasi',
            self::Lunas => 'Lunas',
        };
    }
}
