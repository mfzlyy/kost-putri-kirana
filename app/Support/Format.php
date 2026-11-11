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

    /**
     * Menyeragamkan nomor telepon: spasi, tanda hubung, titik, dan kurung dibuang, awalan +62 atau 62 menjadi 0.
     * "+62 812-3456-7801" menjadi "081234567801".
     */
    public static function normalisasiTelepon(string $teks): string
    {
        $nomor = preg_replace('/[\s\-.()]+/', '', $teks) ?? '';

        if (str_starts_with($nomor, '+62')) {
            return '0'.substr($nomor, 3);
        }

        if (str_starts_with($nomor, '62')) {
            return '0'.substr($nomor, 2);
        }

        return $nomor;
    }

    /**
     * Nomor telepon dikelompokkan 4 angka dari depan: "081234567801" menjadi "0812 3456 7801"; kosong menjadi "-".
     */
    public static function telepon(?string $nomor): string
    {
        $nomor = preg_replace('/\s+/', '', (string) $nomor) ?? '';

        return $nomor === '' ? '-' : self::kelompokEmpat($nomor);
    }

    /**
     * Memecah deretan karakter menjadi kelompok 4 dari depan, dipisah spasi ("3216012345678901" menjadi "3216 0123 4567 8901").
     */
    public static function kelompokEmpat(string $teks): string
    {
        return implode(' ', mb_str_split($teks, 4));
    }
}
