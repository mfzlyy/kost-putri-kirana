<?php

namespace App\Console\Commands;

use App\Models\Fasilitas;
use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Menautkan fasilitas web ke peralatan Odoo Maintenance (fasilitas.odoo_equipment_id) menurut nama.
 * Odoo hanya dibaca; peralatan dibuat manual oleh pengelola (F-28). Tanpa --simpan tidak ada yang diubah.
 */
class ErpCocokkanPeralatan extends Command
{
    protected $signature = 'erp:cocokkan-peralatan
        {--simpan : Simpan odoo_equipment_id untuk fasilitas yang cocok}
        {--timpa : Bersama --simpan, ganti tautan yang berbeda dengan ID dari Odoo}';

    protected $description = 'Tautkan fasilitas ke peralatan Odoo menurut nama (tanpa opsi: pratinjau)';

    private const COCOK = 'cocok';

    private const SUDAH_TERTAUT = 'sudah tertaut';

    private const BERBEDA = 'berbeda';

    private const GANDA = 'ganda';

    private const TIDAK_DITEMUKAN = 'tidak ditemukan';

    public function handle(OdooClient $odoo): int
    {
        $simpan = (bool) $this->option('simpan');
        $timpa = (bool) $this->option('timpa');

        try {
            $peralatan = $odoo->searchRead('maintenance.equipment', [], ['id', 'name'], limit: 1000);
        } catch (OdooException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $idPerNama = [];

        foreach ($peralatan as $satu) {
            $idPerNama[$this->normalkan((string) $satu['name'])][] = (int) $satu['id'];
        }

        $idDiOdoo = array_map(fn (array $satu) => (int) $satu['id'], $peralatan);
        $jumlah = array_fill_keys([self::COCOK, self::SUDAH_TERTAUT, self::BERBEDA, self::GANDA, self::TIDAK_DITEMUKAN], 0);
        $baris = [];
        $disimpan = 0;

        foreach (Fasilitas::query()->with('kamar')->orderBy('id')->get() as $fasilitas) {
            $nama = $fasilitas->namaPeralatanErp();
            $cocok = $idPerNama[$this->normalkan($nama)] ?? [];
            sort($cocok);

            [$hasil, $keterangan] = $this->nilai($fasilitas->odoo_equipment_id, $cocok, $idDiOdoo);
            $jumlah[$hasil]++;
            $baris[] = [$fasilitas->id, $fasilitas->lokasiErp(), $nama, $keterangan];

            if ($simpan && ($hasil === self::COCOK || ($hasil === self::BERBEDA && $timpa))) {
                $fasilitas->update(['odoo_equipment_id' => $cocok[0]]);
                $disimpan++;
            }
        }

        if ($baris === []) {
            $this->info('Belum ada fasilitas di web.');

            return self::SUCCESS;
        }

        $this->table(['fasilitas id', 'lokasi', 'nama yang dicari di Odoo', 'hasil'], $baris);

        $this->line('Ringkasan: '.collect($jumlah)->map(fn (int $n, string $hasil) => "{$hasil} {$n}")->implode(', ').'.');
        $this->line($simpan ? "Disimpan: {$disimpan} fasilitas." : 'Pratinjau: tidak ada data yang diubah.');

        if ($timpa && ! $simpan) {
            $this->warn('--timpa hanya berlaku bersama --simpan.');
        }

        if ($jumlah[self::BERBEDA] > 0 && ! ($simpan && $timpa)) {
            $this->line('Tautan yang berbeda hanya diubah dengan --simpan --timpa.');
        }

        $this->line("Buat peralatan dengan nama persis seperti kolom 'nama yang dicari' di Odoo → Maintenance → Equipments, lalu jalankan dengan --simpan.");
        $this->line('Fasilitas yang ditautkan setelah tiketnya selesai tidak membuat baris pemeliharaan baru.');

        return self::SUCCESS;
    }

    /**
     * Hasil pencocokan satu fasilitas: [kelompok untuk ringkasan, teks kolom "hasil"].
     *
     * @param  list<int>  $cocok  ID peralatan yang namanya sama, urut naik
     * @param  list<int>  $idDiOdoo  semua ID peralatan di Odoo
     * @return array{0: string, 1: string}
     */
    private function nilai(?int $tertaut, array $cocok, array $idDiOdoo): array
    {
        if (count($cocok) > 1) {
            return [self::GANDA, 'ganda: '.collect($cocok)->map(fn (int $id) => "#{$id}")->implode(', ')];
        }

        if ($cocok === []) {
            return match (true) {
                $tertaut === null => [self::TIDAK_DITEMUKAN, 'tidak ditemukan'],
                in_array($tertaut, $idDiOdoo, true) => [self::SUDAH_TERTAUT, "sudah tertaut #{$tertaut} (nama berbeda)"],
                default => [self::TIDAK_DITEMUKAN, "tidak ditemukan (web #{$tertaut} tidak ada di Odoo)"],
            };
        }

        $id = $cocok[0];

        return match ($tertaut) {
            null => [self::COCOK, "cocok #{$id}"],
            $id => [self::SUDAH_TERTAUT, "sudah tertaut #{$id}"],
            default => [self::BERBEDA, "berbeda: web #{$tertaut}, Odoo #{$id}"],
        };
    }

    /**
     * Pembanding nama: tanpa spasi tepi, spasi berurutan menjadi satu, dan tidak peka huruf besar-kecil.
     */
    private function normalkan(string $nama): string
    {
        return Str::lower(Str::squish($nama));
    }
}
