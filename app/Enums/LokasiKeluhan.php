<?php

namespace App\Enums;

enum LokasiKeluhan: string
{
    case Kamar = 'kamar';
    case AreaBersama = 'area_bersama';

    public function label(): string
    {
        return match ($this) {
            self::Kamar => 'Kamar',
            self::AreaBersama => 'Area bersama',
        };
    }
}
