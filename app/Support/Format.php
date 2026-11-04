<?php

namespace App\Support;

/**
 * Format tampilan angka dan nominal sesuai docs/design/DESIGN.md.
 */
class Format
{
    /**
     * Nominal rupiah tanpa spasi dan tanpa desimal: 1400000 menjadi "Rp1.400.000"; null menjadi "-".
     */
    public static function rupiah(int|float|string|null $nilai): string
    {
        if ($nilai === null || $nilai === '' || ! is_numeric($nilai)) {
            return '-';
        }

        $bulat = (int) round((float) $nilai);
        $negatif = $bulat < 0;

        return ($negatif ? '-' : '').'Rp'.number_format(abs($bulat), 0, ',', '.');
    }

    /**
     * Membaca isian tarif ("1.400.000", "Rp 1.400.000", "1400000") menjadi bilangan bulat.
     * Teks tanpa angka menghasilkan null.
     */
    public static function parseRupiah(string $teks): ?int
    {
        $angka = preg_replace('/\D/', '', $teks) ?? '';

        if ($angka === '') {
            return null;
        }

        return (int) $angka;
    }
}
