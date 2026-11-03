<?php

namespace App\Enums;

enum StatusTiket: string
{
    case Diajukan = 'diajukan';
    case Ditugaskan = 'ditugaskan';
    case Dikerjakan = 'dikerjakan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Ditugaskan => 'Ditugaskan',
            self::Dikerjakan => 'Dikerjakan',
            self::Selesai => 'Selesai',
        };
    }

    /**
     * Status berikutnya sesuai urutan ATB-07; null bila sudah selesai.
     */
    public function berikutnya(): ?self
    {
        return match ($this) {
            self::Diajukan => self::Ditugaskan,
            self::Ditugaskan => self::Dikerjakan,
            self::Dikerjakan => self::Selesai,
            self::Selesai => null,
        };
    }
}
