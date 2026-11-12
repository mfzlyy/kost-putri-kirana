<?php

namespace App\Console\Commands;

use App\Support\Migrasi\PembacaCsv;
use Faker\Factory;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Mengganti nama, nomor identitas, dan telepon penghuni sebelum data dimuat (F-09 butir 2, NFR-08).
 * Kode, nomor kamar, tarif, tanggal, periode, nominal, dan status dipertahankan agar rekap bisa dicocokkan.
 * Tabel padanan nama asli dan nama fiktif tidak disimpan di mana pun, dan isi baris tidak pernah dicetak.
 */
class MigrasiAnonimkan extends Command
{
    protected $signature = 'migrasi:anonimkan
        {sumber : Folder berisi kamar.csv, penghuni.csv, kontrak.csv, pembayaran.csv}
        {tujuan : Folder hasil anonimisasi (dibuat bila belum ada)}
        {--seed= : Angka seed supaya hasil bisa diulang}
        {--timpa : Timpa CSV yang sudah ada di folder tujuan}';

    protected $description = 'Anonimkan data migrasi (nama, nomor identitas, telepon) sebelum diimpor';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Anonimisasi hanya dijalankan di laptop pengembang.');

            return self::FAILURE;
        }

        $sumber = PembacaCsv::jalur((string) $this->argument('sumber'));
        $tujuan = PembacaCsv::jalur((string) $this->argument('tujuan'));

        if (! is_dir($sumber)) {
            $this->error('Folder sumber tidak ditemukan.');

            return self::FAILURE;
        }

        if (realpath($sumber) === realpath($tujuan)) {
            $this->error('Folder tujuan tidak boleh sama dengan folder sumber.');

            return self::FAILURE;
        }

        $seed = $this->option('seed');
        if ($seed !== null && preg_match('/^\d+$/', (string) $seed) !== 1) {
            $this->error('Opsi --seed harus berupa angka.');

            return self::FAILURE;
        }

        if (! $this->option('timpa') && is_dir($tujuan) && glob($tujuan.DIRECTORY_SEPARATOR.'*.csv') !== []) {
            $this->error('Folder tujuan sudah berisi CSV. Pakai folder lain atau tambahkan --timpa.');

            return self::FAILURE;
        }

        try {
            $berkas = [];
            foreach (PembacaCsv::KOLOM as $nama => $kolom) {
                $berkas[$nama] = PembacaCsv::baca($sumber.DIRECTORY_SEPARATOR.$nama.'.csv', $kolom);
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! is_dir($tujuan) && ! mkdir($tujuan, 0775, true) && ! is_dir($tujuan)) {
            $this->error('Folder tujuan tidak bisa dibuat.');

            return self::FAILURE;
        }

        $berkas['penghuni'] = $this->anonimkanPenghuni($berkas['penghuni'], $seed === null ? null : (int) $seed);

        $jumlah = [];
        foreach ($berkas as $nama => $baris) {
            PembacaCsv::tulis($tujuan.DIRECTORY_SEPARATOR.$nama.'.csv', PembacaCsv::KOLOM[$nama], $this->sisipkanBarisKosong($baris));
            $jumlah[$nama] = count($baris);
        }

        file_put_contents(
            $tujuan.DIRECTORY_SEPARATOR.'_anonim.json',
            json_encode(['dibuat_pada' => now()->toIso8601String(), 'jumlah_baris' => $jumlah], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->info('Anonimisasi selesai.');
        foreach ($jumlah as $nama => $banyak) {
            $this->line("  {$nama}.csv: {$banyak} baris");
        }
        $this->warn('Hapus folder sumber setelah impor selesai.');

        return self::SUCCESS;
    }

    /**
     * Hanya nilai yang terisi yang diganti; sel kosong tetap kosong supaya galat sumber tetap terlapor saat impor.
     *
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @return list<array{baris: int, data: array<string, string>}>
     */
    private function anonimkanPenghuni(array $baris, ?int $seed): array
    {
        $faker = Factory::create('id_ID');
        $seed === null ? $faker->seed() : $faker->seed($seed);

        $nama = [];
        $identitas = [];
        $telepon = [];

        foreach ($baris as &$b) {
            $d = &$b['data'];

            if ($d['nama'] !== '') {
                $d['nama'] = $this->unik($nama, fn () => $faker->firstNameFemale().' '.$faker->lastName());
            }

            if ($d['no_identitas'] !== '') {
                // Kode provinsi 00 tidak ada, jadi jelas fiktif; impor menolak berkas yang NIK-nya tidak berawalan 00.
                $d['no_identitas'] = $this->unik($identitas, fn () => '00'.$faker->numerify('##############'));
            }

            if ($d['no_telepon'] !== '') {
                $d['no_telepon'] = $this->unik($telepon, fn () => '08000000'.$faker->numerify('####'));
            }

            unset($d);
        }
        unset($b);

        return $baris;
    }

    /**
     * @param  array<string, true>  $dipakai
     * @param  callable(): string  $buat
     */
    private function unik(array &$dipakai, callable $buat): string
    {
        for ($percobaan = 0; $percobaan < 1000; $percobaan++) {
            $nilai = $buat();

            if (! isset($dipakai[$nilai])) {
                $dipakai[$nilai] = true;

                return $nilai;
            }
        }

        throw new RuntimeException('Gagal membuat nilai fiktif yang unik.');
    }

    /**
     * Baris kosong di sumber ditulis sebagai baris kosong agar nomor baris di laporan kesalahan tetap sama dengan Google Sheets.
     *
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @return list<array<string, string>|null>
     */
    private function sisipkanBarisKosong(array $baris): array
    {
        $hasil = [];
        $berikut = 2;

        foreach ($baris as ['baris' => $nomor, 'data' => $data]) {
            for (; $berikut < $nomor; $berikut++) {
                $hasil[] = null;
            }

            $hasil[] = $data;
            $berikut = $nomor + 1;
        }

        return $hasil;
    }
}
