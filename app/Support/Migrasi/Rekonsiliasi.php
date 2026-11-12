<?php

namespace App\Support\Migrasi;

use App\Enums\StatusKontrak;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi sumber vs basis data (F-09 butir 3, TB-03). Kolom "Dimuat" dihitung ulang lewat query ke basis data
 * setelah insert, bukan dari penghitung di pemuat. Selisih = Sumber - Dimuat - Ditolak dan harus 0.
 */
final class Rekonsiliasi
{
    public const PERIODE_TIDAK_VALID = '(periode tidak valid)';

    /**
     * @return list<array{uraian: string, sumber: int, dimuat: int, ditolak: int, selisih: int, rupiah: bool}>
     */
    public function hitung(HasilValidasi $hasil): array
    {
        $baris = [];

        $baris[] = $this->baris('Kamar', count($hasil->sumber['kamar']), Kamar::query()->count(), count($hasil->ditolak['kamar']));
        $baris[] = $this->baris('Penghuni', count($hasil->sumber['penghuni']), Penghuni::query()->count(), count($hasil->ditolak['penghuni']));

        foreach (['aktif' => 'Kontrak aktif', 'berakhir' => 'Kontrak berakhir'] as $status => $label) {
            $sumber = $this->barisKontrakBerstatus($hasil->sumber['kontrak'], $status);
            $ditolak = array_filter($sumber, fn (array $b) => isset($hasil->ditolak['kontrak'][$b['baris']]));

            $baris[] = $this->baris(
                $label,
                count($sumber),
                Kontrak::query()->where('status_kontrak', StatusKontrak::from($status)->value)->count(),
                count($ditolak),
            );
        }

        return array_merge($baris, $this->barisPeriode($hasil));
    }

    /**
     * @param  list<array{uraian: string, sumber: int, dimuat: int, ditolak: int, selisih: int, rupiah: bool}>  $baris
     */
    public function cocok(array $baris): bool
    {
        return collect($baris)->every(fn (array $b) => $b['selisih'] === 0);
    }

    /**
     * @return list<array{uraian: string, sumber: int, dimuat: int, ditolak: int, selisih: int, rupiah: bool}>
     */
    private function barisPeriode(HasilValidasi $hasil): array
    {
        $sumber = [];

        foreach ($hasil->sumber['pembayaran'] as ['baris' => $nomor, 'data' => $d]) {
            $periode = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $d['periode']) === 1 ? $d['periode'] : self::PERIODE_TIDAK_VALID;
            $nominal = Format::parseRupiah($d['nominal']) ?? 0;
            $ditolak = isset($hasil->ditolak['pembayaran'][$nomor]);
            $lunas = $d['tanggal_bayar'] !== '';

            $sumber[$periode] ??= [
                'tagihan' => [0, 0], 'nominal' => [0, 0], 'lunas' => [0, 0], 'dibayar' => [0, 0],
            ];

            // [semua baris sumber, baris ditolak]
            foreach ([['tagihan', 1], ['nominal', $nominal]] as [$kunci, $nilai]) {
                $sumber[$periode][$kunci][0] += $nilai;
                $sumber[$periode][$kunci][1] += $ditolak ? $nilai : 0;
            }

            if ($lunas) {
                foreach ([['lunas', 1], ['dibayar', $nominal]] as [$kunci, $nilai]) {
                    $sumber[$periode][$kunci][0] += $nilai;
                    $sumber[$periode][$kunci][1] += $ditolak ? $nilai : 0;
                }
            }
        }

        $tagihan = DB::table('tagihan')
            ->selectRaw("to_char(periode, 'YYYY-MM') as periode, count(*) as jumlah, sum(nominal) as total, count(*) filter (where status_tagihan = 'lunas') as lunas")
            ->groupBy('periode')
            ->get()
            ->keyBy('periode');

        $dibayar = DB::table('pembayaran')
            ->join('tagihan', 'tagihan.id', '=', 'pembayaran.tagihan_id')
            ->where('pembayaran.status_verifikasi', 'diterima')
            ->selectRaw("to_char(tagihan.periode, 'YYYY-MM') as periode, sum(pembayaran.nominal_bayar) as total")
            ->groupBy('periode')
            ->get()
            ->keyBy('periode');

        $periode = array_unique(array_merge(array_keys($sumber), $tagihan->keys()->all(), $dibayar->keys()->all()));
        usort($periode, function (string $a, string $b) {
            return [$a === self::PERIODE_TIDAK_VALID, $a] <=> [$b === self::PERIODE_TIDAK_VALID, $b];
        });

        $baris = [];

        foreach ($periode as $p) {
            $s = $sumber[$p] ?? ['tagihan' => [0, 0], 'nominal' => [0, 0], 'lunas' => [0, 0], 'dibayar' => [0, 0]];

            $baris[] = $this->baris("{$p} · Jumlah tagihan", $s['tagihan'][0], (int) ($tagihan[$p]->jumlah ?? 0), $s['tagihan'][1]);
            $baris[] = $this->baris("{$p} · Total nominal tagihan", $s['nominal'][0], (int) round((float) ($tagihan[$p]->total ?? 0)), $s['nominal'][1], true);
            $baris[] = $this->baris("{$p} · Jumlah lunas", $s['lunas'][0], (int) ($tagihan[$p]->lunas ?? 0), $s['lunas'][1]);
            $baris[] = $this->baris("{$p} · Total dibayar", $s['dibayar'][0], (int) round((float) ($dibayar[$p]->total ?? 0)), $s['dibayar'][1], true);
        }

        return $baris;
    }

    /**
     * @param  list<array{baris: int, data: array<string, string>}>  $kontrak
     * @return list<array{baris: int, data: array<string, string>}>
     */
    private function barisKontrakBerstatus(array $kontrak, string $status): array
    {
        return array_values(array_filter($kontrak, fn (array $b) => mb_strtolower($b['data']['status']) === $status));
    }

    /**
     * @return array{uraian: string, sumber: int, dimuat: int, ditolak: int, selisih: int, rupiah: bool}
     */
    private function baris(string $uraian, int $sumber, int $dimuat, int $ditolak, bool $rupiah = false): array
    {
        return [
            'uraian' => $uraian,
            'sumber' => $sumber,
            'dimuat' => $dimuat,
            'ditolak' => $ditolak,
            'selisih' => $sumber - $dimuat - $ditolak,
            'rupiah' => $rupiah,
        ];
    }
}
