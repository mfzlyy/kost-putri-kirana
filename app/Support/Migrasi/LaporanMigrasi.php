<?php

namespace App\Support\Migrasi;

use App\Support\Format;
use Illuminate\Support\Facades\Storage;

/**
 * Menulis laporan kesalahan (F-09 butir 4) dan rekonsiliasi (F-09 butir 3) ke storage/app/private/migrasi/laporan.
 * Isi laporan hanya kode baris dan pesan baku; nilai nomor identitas, nama, dan telepon tidak pernah ditulis.
 */
final class LaporanMigrasi
{
    public const FOLDER = 'migrasi/laporan';

    /**
     * @param  list<Temuan>  $temuan
     * @return string jalur berkas laporan
     */
    public function tulisKesalahan(array $temuan, string $stempel): string
    {
        $baris = array_map(fn (Temuan $t) => [$t->berkas, $t->baris, $t->kode, $t->kolom, $t->pesan, $t->jenis], $temuan);

        return $this->simpan("kesalahan-{$stempel}.csv", ['berkas', 'baris', 'kode', 'kolom', 'pesan', 'jenis'], $baris);
    }

    /**
     * @param  list<array{uraian: string, sumber: int, dimuat: int, ditolak: int, selisih: int, rupiah: bool}>  $rekonsiliasi
     * @return string jalur berkas laporan
     */
    public function tulisRekonsiliasi(array $rekonsiliasi, string $stempel): string
    {
        return $this->simpan("rekonsiliasi-{$stempel}.csv", ['uraian', 'sumber', 'dimuat', 'ditolak', 'selisih'], $this->tampilkan($rekonsiliasi));
    }

    /**
     * Baris rekonsiliasi siap tampil: angka rupiah ditulis dengan Format::rupiah.
     *
     * @param  list<array{uraian: string, sumber: int, dimuat: int, ditolak: int, selisih: int, rupiah: bool}>  $rekonsiliasi
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public function tampilkan(array $rekonsiliasi): array
    {
        return array_map(function (array $b) {
            $angka = fn (int $nilai) => $b['rupiah'] ? Format::rupiah($nilai) : (string) $nilai;

            return [$b['uraian'], $angka($b['sumber']), $angka($b['dimuat']), $angka($b['ditolak']), $angka($b['selisih'])];
        }, $rekonsiliasi);
    }

    /**
     * @param  list<string>  $header
     * @param  list<array<int, scalar>>  $baris
     */
    private function simpan(string $nama, array $header, array $baris): string
    {
        $aliran = fopen('php://temp', 'w+b');

        fputcsv($aliran, $header, ',', '"', '');
        foreach ($baris as $kolom) {
            fputcsv($aliran, $kolom, ',', '"', '');
        }

        rewind($aliran);
        $isi = stream_get_contents($aliran);
        fclose($aliran);

        $disk = Storage::disk('local');
        $disk->put(self::FOLDER.'/'.$nama, $isi);

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $disk->path(self::FOLDER.'/'.$nama));
    }
}
