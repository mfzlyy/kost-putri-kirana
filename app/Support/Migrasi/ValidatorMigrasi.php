<?php

namespace App\Support\Migrasi;

use App\Models\Kontrak;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Memvalidasi keempat berkas migrasi (F-09 butir 4). Semua baris diperiksa dan galat dikumpulkan,
 * tidak berhenti di galat pertama. Baris yang merujuk baris tertolak ikut ditolak.
 */
final class ValidatorMigrasi
{
    /** Periode pembayaran paling lama 6 bulan sebelum bulan berjalan (DEP-02). */
    private const BATAS_BULAN_PEMBAYARAN = 6;

    /** @var list<Temuan> */
    private array $temuan = [];

    /** @var array<string, array<int, true>> berkas => [nomor baris => true] */
    private array $ditolak = [];

    /**
     * @param  array<string, list<array{baris: int, data: array<string, string>}>>  $sumber  berkas => baris hasil PembacaCsv
     */
    public function validasi(array $sumber, ?CarbonInterface $hariIni = null): HasilValidasi
    {
        $this->temuan = [];
        $this->ditolak = ['kamar' => [], 'penghuni' => [], 'kontrak' => [], 'pembayaran' => []];
        $hariIni = Kontrak::hariIni($hariIni);

        $kamar = $this->validasiKamar($sumber['kamar']);
        $penghuni = $this->validasiPenghuni($sumber['penghuni']);
        $kontrak = $this->validasiKontrak(
            $sumber['kontrak'],
            $kamar,
            $penghuni,
            $this->semuaKode($sumber['kamar'], 'nomor_kamar'),
            $this->semuaKode($sumber['penghuni'], 'kode_penghuni'),
            $hariIni,
        );
        $pembayaran = $this->validasiPembayaran(
            $sumber['pembayaran'],
            $kontrak,
            $this->semuaKode($sumber['kontrak'], 'kode_kontrak'),
            $hariIni,
        );

        return new HasilValidasi($sumber, $kamar, $penghuni, $kontrak, $pembayaran, $this->ditolak, $this->temuan);
    }

    /**
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @return array<string, array<string, mixed>> nomor_kamar => baris valid
     */
    private function validasiKamar(array $baris): array
    {
        $valid = [];
        $terlihat = [];

        foreach ($baris as ['baris' => $nomor, 'data' => $d]) {
            $kode = $d['nomor_kamar'];
            $galat = [];

            if ($kode === '') {
                $galat[] = ['nomor_kamar', 'Nomor kamar wajib diisi.'];
            } elseif (mb_strlen($kode) > 10) {
                $galat[] = ['nomor_kamar', 'Nomor kamar maksimal 10 karakter.'];
            } elseif (isset($terlihat[$kode])) {
                $galat[] = ['nomor_kamar', 'Nomor kamar ganda di berkas.'];
            }

            if ($kode !== '') {
                $terlihat[$kode] = true;
            }

            $tarif = Format::parseRupiah($d['tarif_bulanan']);
            if ($tarif === null) {
                $galat[] = ['tarif_bulanan', 'Tarif bulanan tidak terbaca sebagai rupiah.'];
            } elseif ($tarif <= 0) {
                $galat[] = ['tarif_bulanan', 'Tarif bulanan harus lebih dari 0.'];
            }

            if ($galat !== []) {
                $this->tolak('kamar', $nomor, $kode, $galat);

                continue;
            }

            $valid[$kode] = [
                'baris' => $nomor,
                'nomor_kamar' => $kode,
                'tarif_bulanan' => $tarif,
                'keterangan' => $d['keterangan'] === '' ? null : $d['keterangan'],
            ];
        }

        return $valid;
    }

    /**
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @return array<string, array<string, mixed>> kode_penghuni => baris valid
     */
    private function validasiPenghuni(array $baris): array
    {
        $valid = [];
        $terlihat = [];

        foreach ($baris as ['baris' => $nomor, 'data' => $d]) {
            $kode = $d['kode_penghuni'];
            $galat = [];

            if ($kode === '') {
                $galat[] = ['kode_penghuni', 'Kode penghuni wajib diisi.'];
            } elseif (preg_match('/^[A-Za-z0-9_.-]{1,30}$/', $kode) !== 1) {
                $galat[] = ['kode_penghuni', 'Kode penghuni hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung (maksimal 30).'];
            } elseif (isset($terlihat[mb_strtolower($kode)])) {
                $galat[] = ['kode_penghuni', 'Kode penghuni ganda di berkas.'];
            }

            if ($kode !== '') {
                $terlihat[mb_strtolower($kode)] = true;
            }

            if ($d['nama'] === '') {
                $galat[] = ['nama', 'Nama wajib diisi.'];
            } elseif (mb_strlen($d['nama']) > 100) {
                $galat[] = ['nama', 'Nama maksimal 100 karakter.'];
            }

            if (preg_match('/^\d{16}$/', $d['no_identitas']) !== 1) {
                $galat[] = ['no_identitas', 'Nomor identitas harus 16 angka.'];
            }

            $telepon = Format::normalisasiTelepon($d['no_telepon']);
            if (preg_match('/^0[0-9]{9,13}$/', $telepon) !== 1) {
                $galat[] = ['no_telepon', 'Nomor telepon harus diawali 0 dan terdiri dari 10 sampai 14 angka.'];
            }

            $status = mb_strtolower($d['status']);
            if (! in_array($status, ['aktif', 'nonaktif'], true)) {
                $galat[] = ['status', 'Status harus aktif atau nonaktif.'];
            }

            if ($galat !== []) {
                $this->tolak('penghuni', $nomor, $kode, $galat);

                continue;
            }

            $valid[$kode] = [
                'baris' => $nomor,
                'kode_penghuni' => $kode,
                'nama' => $d['nama'],
                'no_identitas' => $d['no_identitas'],
                'no_telepon' => $telepon,
                'status' => $status,
            ];
        }

        return $valid;
    }

    /**
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @param  array<string, array<string, mixed>>  $kamar
     * @param  array<string, array<string, mixed>>  $penghuni
     * @param  array<string, true>  $kodeKamarSumber
     * @param  array<string, true>  $kodePenghuniSumber
     * @return array<string, array<string, mixed>> kode_kontrak => baris valid
     */
    private function validasiKontrak(array $baris, array $kamar, array $penghuni, array $kodeKamarSumber, array $kodePenghuniSumber, CarbonImmutable $hariIni): array
    {
        $valid = [];
        $terlihat = [];
        $kamarAktif = [];
        $penghuniAktif = [];

        foreach ($baris as ['baris' => $nomor, 'data' => $d]) {
            $kode = $d['kode_kontrak'];
            $galat = [];

            if ($kode === '') {
                $galat[] = ['kode_kontrak', 'Kode kontrak wajib diisi.'];
            } elseif (isset($terlihat[$kode])) {
                $galat[] = ['kode_kontrak', 'Kode kontrak ganda di berkas.'];
            }

            if ($kode !== '') {
                $terlihat[$kode] = true;
            }

            $galat = array_merge(
                $galat,
                $this->periksaRujukan('kode_penghuni', $d['kode_penghuni'], 'penghuni.csv', $penghuni, $kodePenghuniSumber),
                $this->periksaRujukan('nomor_kamar', $d['nomor_kamar'], 'kamar.csv', $kamar, $kodeKamarSumber),
            );

            $mulai = Format::parseTanggal($d['tanggal_mulai']);
            $berakhir = Format::parseTanggal($d['tanggal_berakhir']);

            if ($mulai === null) {
                $galat[] = ['tanggal_mulai', 'Tanggal mulai tidak valid (pakai YYYY-MM-DD atau DD/MM/YYYY).'];
            }

            if ($berakhir === null) {
                $galat[] = ['tanggal_berakhir', 'Tanggal berakhir tidak valid (pakai YYYY-MM-DD atau DD/MM/YYYY).'];
            }

            if ($mulai !== null && $berakhir !== null && $berakhir->toDateString() <= $mulai->toDateString()) {
                $galat[] = ['tanggal_berakhir', 'Tanggal berakhir harus setelah tanggal mulai.'];
            }

            $tarif = Format::parseRupiah($d['tarif_bulanan']);
            if ($tarif === null) {
                $galat[] = ['tarif_bulanan', 'Tarif bulanan tidak terbaca sebagai rupiah.'];
            } elseif ($tarif <= 0) {
                $galat[] = ['tarif_bulanan', 'Tarif bulanan harus lebih dari 0.'];
            }

            $status = mb_strtolower($d['status']);
            if (! in_array($status, ['aktif', 'berakhir'], true)) {
                $galat[] = ['status', 'Status harus aktif atau berakhir.'];
            }

            if ($galat === [] && $status === 'aktif') {
                if (isset($kamarAktif[$d['nomor_kamar']])) {
                    $galat[] = ['nomor_kamar', "Kamar sudah punya kontrak aktif lain ({$kamarAktif[$d['nomor_kamar']]})."];
                }

                if (isset($penghuniAktif[$d['kode_penghuni']])) {
                    $galat[] = ['kode_penghuni', "Penghuni sudah punya kontrak aktif lain ({$penghuniAktif[$d['kode_penghuni']]})."];
                }

                if ($penghuni[$d['kode_penghuni']]['status'] === 'nonaktif') {
                    $galat[] = ['kode_penghuni', 'Penghuni nonaktif tidak boleh punya kontrak aktif.'];
                }
            }

            if ($galat !== []) {
                $this->tolak('kontrak', $nomor, $kode, $galat);

                continue;
            }

            if ($status === 'aktif') {
                $kamarAktif[$d['nomor_kamar']] = $kode;
                $penghuniAktif[$d['kode_penghuni']] = $kode;

                if ($berakhir->toDateString() < $hariIni->toDateString()) {
                    $this->peringatkan('kontrak', $nomor, $kode, 'tanggal_berakhir', 'Kontrak aktif sudah lewat tanggal berakhir; akan diakhiri oleh kontrak:akhiri-kedaluwarsa.');
                }
            }

            $valid[$kode] = [
                'baris' => $nomor,
                'kode_kontrak' => $kode,
                'kode_penghuni' => $d['kode_penghuni'],
                'nomor_kamar' => $d['nomor_kamar'],
                'tanggal_mulai' => $mulai,
                'tanggal_berakhir' => $berakhir,
                'tarif_bulanan' => $tarif,
                'status' => $status,
            ];
        }

        return $valid;
    }

    /**
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @param  array<string, array<string, mixed>>  $kontrak
     * @param  array<string, true>  $kodeKontrakSumber
     * @return list<array<string, mixed>> baris valid, urut seperti di berkas
     */
    private function validasiPembayaran(array $baris, array $kontrak, array $kodeKontrakSumber, CarbonImmutable $hariIni): array
    {
        $valid = [];
        $terlihat = [];
        $batas = $hariIni->startOfMonth()->subMonths(self::BATAS_BULAN_PEMBAYARAN)->toDateString();

        foreach ($baris as ['baris' => $nomor, 'data' => $d]) {
            $kode = $d['kode_kontrak'];
            $galat = $this->periksaRujukan('kode_kontrak', $kode, 'kontrak.csv', $kontrak, $kodeKontrakSumber);

            $periode = null;
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $d['periode']) === 1) {
                $periode = $d['periode'].'-01';
            } else {
                $galat[] = ['periode', 'Periode harus berformat YYYY-MM.'];
            }

            if ($periode !== null && $kode !== '') {
                if (isset($terlihat[$kode.'|'.$periode])) {
                    $galat[] = ['periode', 'Periode ganda untuk kontrak yang sama.'];
                }
                $terlihat[$kode.'|'.$periode] = true;
            }

            $nominal = Format::parseRupiah($d['nominal']);
            if ($nominal === null) {
                $galat[] = ['nominal', 'Nominal tidak terbaca sebagai rupiah.'];
            } elseif ($nominal <= 0) {
                $galat[] = ['nominal', 'Nominal harus lebih dari 0.'];
            }

            $jatuhTempo = Format::parseTanggal($d['jatuh_tempo']);
            if ($jatuhTempo === null) {
                $galat[] = ['jatuh_tempo', 'Jatuh tempo tidak valid (pakai YYYY-MM-DD atau DD/MM/YYYY).'];
            }

            $tanggalBayar = null;
            if ($d['tanggal_bayar'] !== '') {
                $tanggalBayar = Format::parseTanggal($d['tanggal_bayar']);

                if ($tanggalBayar === null) {
                    $galat[] = ['tanggal_bayar', 'Tanggal bayar tidak valid (pakai YYYY-MM-DD atau DD/MM/YYYY).'];
                }
            }

            if ($galat !== []) {
                $this->tolak('pembayaran', $nomor, $kode, $galat);

                continue;
            }

            if ($periode < $batas) {
                $this->peringatkan('pembayaran', $nomor, $kode, 'periode', 'Periode di luar 6 bulan terakhir; pastikan diizinkan pemilik (DEP-02).');
            }

            $valid[] = [
                'baris' => $nomor,
                'kode_kontrak' => $kode,
                'periode' => CarbonImmutable::parse($periode),
                'nominal' => $nominal,
                'jatuh_tempo' => $jatuhTempo,
                'tanggal_bayar' => $tanggalBayar,
            ];
        }

        return $valid;
    }

    /**
     * Rujukan harus menunjuk baris VALID. Kode yang ada di sumber tetapi tertolak menolak baris ini secara berantai.
     *
     * @param  array<string, array<string, mixed>>  $valid
     * @param  array<string, true>  $kodeSumber
     * @return list<array{0: string, 1: string}>
     */
    private function periksaRujukan(string $kolom, string $kode, string $berkas, array $valid, array $kodeSumber): array
    {
        if ($kode === '') {
            return [[$kolom, ucfirst(str_replace('_', ' ', $kolom)).' wajib diisi.']];
        }

        if (isset($valid[$kode])) {
            return [];
        }

        if (isset($kodeSumber[$kode])) {
            return [[$kolom, "Merujuk {$berkas} {$kode} yang tidak dimuat."]];
        }

        return [[$kolom, "Kode {$kode} tidak dikenal di {$berkas}."]];
    }

    /**
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @return array<string, true>
     */
    private function semuaKode(array $baris, string $kolom): array
    {
        $kode = [];

        foreach ($baris as $b) {
            if ($b['data'][$kolom] !== '') {
                $kode[$b['data'][$kolom]] = true;
            }
        }

        return $kode;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $galat
     */
    private function tolak(string $berkas, int $baris, string $kode, array $galat): void
    {
        $this->ditolak[$berkas][$baris] = true;

        foreach ($galat as [$kolom, $pesan]) {
            $this->temuan[] = new Temuan("{$berkas}.csv", $baris, $kode, $kolom, $pesan);
        }
    }

    private function peringatkan(string $berkas, int $baris, string $kode, string $kolom, string $pesan): void
    {
        $this->temuan[] = new Temuan("{$berkas}.csv", $baris, $kode, $kolom, $pesan, Temuan::PERINGATAN);
    }
}
