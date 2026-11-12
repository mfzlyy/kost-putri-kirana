<?php

namespace App\Support\Migrasi;

use RuntimeException;

/**
 * Membaca dan menulis berkas CSV migrasi (F-09). UTF-8, pemisah koma atau titik koma dideteksi dari baris header,
 * BOM diabaikan, baris yang semua kolomnya kosong dilewati.
 */
final class PembacaCsv
{
    /**
     * Kolom wajib tiap berkas, sesuai docs/migrasi/README.md.
     *
     * @var array<string, list<string>>
     */
    public const KOLOM = [
        'kamar' => ['nomor_kamar', 'tarif_bulanan', 'keterangan'],
        'penghuni' => ['kode_penghuni', 'nama', 'no_identitas', 'no_telepon', 'status'],
        'kontrak' => ['kode_kontrak', 'kode_penghuni', 'nomor_kamar', 'tanggal_mulai', 'tanggal_berakhir', 'tarif_bulanan', 'status'],
        'pembayaran' => ['kode_kontrak', 'periode', 'nominal', 'jatuh_tempo', 'tanggal_bayar'],
    ];

    /**
     * Nomor baris pada hasil adalah nomor baris di berkas (header = 1), sama dengan nomor baris di Google Sheets.
     *
     * @param  list<string>  $kolomWajib
     * @return list<array{baris: int, data: array<string, string>}>
     *
     * @throws RuntimeException
     */
    public static function baca(string $jalur, array $kolomWajib): array
    {
        if (! is_file($jalur)) {
            throw new RuntimeException('Berkas '.basename($jalur).' tidak ditemukan.');
        }

        $berkas = fopen($jalur, 'rb');

        try {
            $barisPertama = fgets($berkas);

            if ($barisPertama === false) {
                throw new RuntimeException('Berkas '.basename($jalur).' kosong.');
            }

            $barisPertama = self::buangBom($barisPertama);
            $pemisah = substr_count($barisPertama, ';') > substr_count($barisPertama, ',') ? ';' : ',';

            rewind($berkas);
            $header = fgetcsv($berkas, null, $pemisah, '"', '');
            $header[0] = self::buangBom((string) ($header[0] ?? ''));
            $header = array_map(fn ($nama) => mb_strtolower(trim((string) $nama)), $header);

            $hilang = array_values(array_diff($kolomWajib, $header));

            if ($hilang !== []) {
                throw new RuntimeException('Berkas '.basename($jalur).' kehilangan kolom: '.implode(', ', $hilang).'.');
            }

            $hasil = [];
            $nomor = 1;

            while (($rekaman = fgetcsv($berkas, null, $pemisah, '"', '')) !== false) {
                $nomor++;

                $nilai = array_map(fn ($sel) => trim((string) $sel), $rekaman);

                if (implode('', $nilai) === '') {
                    continue;
                }

                $data = [];
                foreach ($header as $indeks => $nama) {
                    if ($nama !== '' && ! array_key_exists($nama, $data)) {
                        $data[$nama] = $nilai[$indeks] ?? '';
                    }
                }

                $hasil[] = ['baris' => $nomor, 'data' => $data];
            }

            return $hasil;
        } finally {
            fclose($berkas);
        }
    }

    /**
     * Menulis CSV berpemisah koma tanpa BOM. $baris yang bernilai null ditulis sebagai baris kosong
     * agar nomor baris di berkas tujuan tetap sama dengan sumber.
     *
     * @param  list<string>  $header
     * @param  iterable<array<string, string>|null>  $baris
     */
    public static function tulis(string $jalur, array $header, iterable $baris): void
    {
        $berkas = fopen($jalur, 'wb');

        try {
            fputcsv($berkas, $header, ',', '"', '');

            foreach ($baris as $data) {
                fputcsv($berkas, $data === null
                    ? array_fill(0, count($header), '')
                    : array_map(fn ($kolom) => $data[$kolom] ?? '', $header), ',', '"', '');
            }
        } finally {
            fclose($berkas);
        }
    }

    /**
     * Jalur relatif dihitung dari folder proyek; jalur absolut dipakai apa adanya.
     */
    public static function jalur(string $jalur): string
    {
        $absolut = str_starts_with($jalur, '/') || str_starts_with($jalur, '\\') || preg_match('/^[A-Za-z]:[\/\\\\]/', $jalur) === 1;

        return rtrim($absolut ? $jalur : base_path($jalur), '/\\');
    }

    private static function buangBom(string $teks): string
    {
        return str_starts_with($teks, "\xEF\xBB\xBF") ? substr($teks, 3) : $teks;
    }
}
