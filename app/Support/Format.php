<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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
     * Waktu unggah untuk daftar verifikasi, per tanggal kalender di zona waktu aplikasi: "hari ini 07.30",
     * "kemarin 19.42", nama hari untuk 2 sampai 6 hari lalu ("Minggu 19.42"), selain itu "3 Nov, 07.30".
     */
    public static function waktuUnggah(CarbonInterface $waktu, ?CarbonInterface $sekarang = null): string
    {
        $zona = config('app.timezone');
        $waktu = CarbonImmutable::instance($waktu)->setTimezone($zona)->locale('id');
        $hariIni = CarbonImmutable::instance($sekarang ?? now())->setTimezone($zona)->startOfDay();
        $selisih = (int) $waktu->copy()->startOfDay()->diffInDays($hariIni, false);
        $jam = $waktu->format('H.i');

        return match (true) {
            $selisih <= 0 => 'hari ini '.$jam,
            $selisih === 1 => 'kemarin '.$jam,
            $selisih <= 6 => $waktu->translatedFormat('l').' '.$jam,
            default => $waktu->translatedFormat('j M').', '.$jam,
        };
    }

    /**
     * Ukuran berkas untuk tampilan: 1887436 menjadi "1,8 MB", 320000 menjadi "313 KB".
     */
    public static function ukuranBerkas(int $byte): string
    {
        if ($byte >= 1048576) {
            return number_format($byte / 1048576, 1, ',', '.').' MB';
        }

        return number_format(max(1, (int) round($byte / 1024)), 0, ',', '.').' KB';
    }

    /**
     * Membaca isian tarif ("1.400.000", "Rp 1.400.000", "1400000", "1.400.000,00") menjadi bilangan bulat.
     * Desimal ",00" atau ".00" di ujung dibuang; desimal lain (",50") dan teks tanpa angka menghasilkan null.
     */
    public static function parseRupiah(string $teks): ?int
    {
        $teks = preg_replace('/[^\d.,]/', '', $teks) ?? '';

        if (preg_match('/[.,](\d{1,2})$/', $teks, $desimal) === 1) {
            if ($desimal[1] !== '00') {
                return null;
            }

            $teks = substr($teks, 0, -3);
        }

        $angka = preg_replace('/\D/', '', $teks) ?? '';

        if ($angka === '') {
            return null;
        }

        return (int) $angka;
    }

    /**
     * Membaca tanggal berformat YYYY-MM-DD atau DD/MM/YYYY (ekspor Google Sheets berlocale Indonesia).
     * Tanggal yang tidak ada di kalender (31/02/2026) atau format lain menghasilkan null.
     */
    public static function parseTanggal(string $teks): ?CarbonImmutable
    {
        $teks = trim($teks);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $teks, $cocok) === 1) {
            [, $tahun, $bulan, $hari] = $cocok;
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $teks, $cocok) === 1) {
            [, $hari, $bulan, $tahun] = $cocok;
        } else {
            return null;
        }

        if (! checkdate((int) $bulan, (int) $hari, (int) $tahun)) {
            return null;
        }

        return CarbonImmutable::create((int) $tahun, (int) $bulan, (int) $hari, 0, 0, 0, config('app.timezone'));
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
