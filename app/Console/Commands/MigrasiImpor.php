<?php

namespace App\Console\Commands;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\Migrasi\LaporanMigrasi;
use App\Support\Migrasi\PembacaCsv;
use App\Support\Migrasi\PemuatMigrasi;
use App\Support\Migrasi\Rekonsiliasi;
use App\Support\Migrasi\ValidatorMigrasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Impor sekali jalan dari CSV (hasil ekspor Google Sheets yang sudah dianonimkan) ke basis data kosong (F-09).
 * Keluaran konsol tidak pernah memuat nama, nomor identitas, atau telepon.
 */
class MigrasiImpor extends Command
{
    protected $signature = 'migrasi:impor
        {folder : Folder hasil migrasi:anonimkan}
        {--uji : Jalankan seluruh proses lalu batalkan, tidak ada yang disimpan}';

    protected $description = 'Validasi dan muat data awal dari CSV yang sudah dianonimkan, lalu cetak rekonsiliasi';

    public function handle(ValidatorMigrasi $validator, PemuatMigrasi $pemuat, Rekonsiliasi $rekonsiliasi, LaporanMigrasi $laporan): int
    {
        $uji = (bool) $this->option('uji');
        $folder = PembacaCsv::jalur((string) $this->argument('folder'));

        try {
            $sumber = $this->periksaPrasyaratBerkas($folder);
            $pengelola = $this->periksaPrasyaratBasisData();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($uji ? 'MODE UJI — tidak ada data yang disimpan.' : 'Impor migrasi data awal');

        $hasil = $validator->validasi($sumber);
        $stempel = now()->format('Ymd-His');

        DB::beginTransaction();

        try {
            $pemuat->muat($hasil, $pengelola);
            $baris = $rekonsiliasi->hitung($hasil);

            $uji ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            // Pesan galat basis data bisa memuat nilai baris; hanya jenisnya yang dicetak.
            $this->error('Pemuatan gagal dan dibatalkan; tidak ada data yang tersimpan ('.class_basename($e).').');

            return self::FAILURE;
        }

        $jalurKesalahan = $laporan->tulisKesalahan($hasil->temuan, $stempel);
        $jalurRekonsiliasi = $laporan->tulisRekonsiliasi($baris, $stempel);

        $this->newLine();
        $this->line('Baris ditolak: '.$hasil->jumlahDitolak().' | galat: '.$hasil->jumlahGalat().' | peringatan: '.$hasil->jumlahPeringatan());
        $this->newLine();
        $this->table(['Uraian', 'Sumber', 'Dimuat', 'Ditolak', 'Selisih'], $laporan->tampilkan($baris));

        $this->newLine();
        $this->line($rekonsiliasi->cocok($baris) ? 'Rekonsiliasi cocok: selisih 0.' : 'Ada selisih. Periksa laporan kesalahan.');

        if ($hasil->jumlahDitolak() > 0) {
            $this->warn($hasil->jumlahDitolak()." baris ditolak dan tidak dimuat. Lihat laporan kesalahan: {$jalurKesalahan}");
        } elseif ($hasil->jumlahPeringatan() > 0) {
            $this->line("Ada peringatan. Lihat laporan kesalahan: {$jalurKesalahan}");
        }

        $this->line("Laporan kesalahan: {$jalurKesalahan}");
        $this->line("Laporan rekonsiliasi: {$jalurRekonsiliasi}");

        if ($uji) {
            $this->warn('MODE UJI — tidak ada data yang disimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * Folder harus sudah dianonimkan: ada _anonim.json dan semua nomor identitas berawalan 00 (F-09 butir 2).
     *
     * @return array<string, list<array{baris: int, data: array<string, string>}>>
     *
     * @throws RuntimeException
     */
    private function periksaPrasyaratBerkas(string $folder): array
    {
        $belumAnonim = 'Data belum dianonimkan. Jalankan php artisan migrasi:anonimkan dulu.';

        if (! is_dir($folder)) {
            throw new RuntimeException('Folder tidak ditemukan.');
        }

        if (! is_file($folder.DIRECTORY_SEPARATOR.'_anonim.json')) {
            throw new RuntimeException($belumAnonim);
        }

        $sumber = [];
        foreach (PembacaCsv::KOLOM as $nama => $kolom) {
            $sumber[$nama] = PembacaCsv::baca($folder.DIRECTORY_SEPARATOR.$nama.'.csv', $kolom);
        }

        foreach ($sumber['penghuni'] as $baris) {
            if ($baris['data']['no_identitas'] !== '' && ! str_starts_with($baris['data']['no_identitas'], '00')) {
                throw new RuntimeException($belumAnonim);
            }
        }

        return $sumber;
    }

    /**
     * Basis data harus kosong dari data hunian, dan pengaturan serta akun pengelola harus sudah ada.
     *
     * @throws RuntimeException
     */
    private function periksaPrasyaratBasisData(): User
    {
        $terisi = Kamar::query()->exists()
            || Penghuni::query()->exists()
            || Kontrak::query()->exists()
            || Tagihan::query()->exists()
            || Pembayaran::query()->exists()
            || User::query()->where('peran', PeranPengguna::Penghuni->value)->exists();

        if ($terisi) {
            throw new RuntimeException('Basis data sudah berisi data hunian. Migrasi hanya untuk basis data kosong. Lihat docs/migrasi/README.md.');
        }

        if (! DB::table('pengaturan_operasional')->exists()) {
            throw new RuntimeException('Baris pengaturan_operasional belum ada. Jalankan php artisan db:seed --class=PengaturanOperasionalSeeder.');
        }

        $pengelola = User::query()
            ->where('peran', PeranPengguna::Pengelola->value)
            ->where('status_akun', StatusAkun::Aktif->value)
            ->orderBy('id')
            ->first();

        return $pengelola ?? throw new RuntimeException('Belum ada akun pengelola aktif. Jalankan php artisan db:seed --class=AkunAwalSeeder.');
    }
}
