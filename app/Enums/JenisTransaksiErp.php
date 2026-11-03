<?php

namespace App\Enums;

enum JenisTransaksiErp: string
{
    case Pembayaran = 'pembayaran';
    case BiayaPerbaikan = 'biaya_perbaikan';
    case Pemeliharaan = 'pemeliharaan';

    public function label(): string
    {
        return match ($this) {
            self::Pembayaran => 'Pembayaran',
            self::BiayaPerbaikan => 'Biaya perbaikan',
            self::Pemeliharaan => 'Pemeliharaan',
        };
    }
}
