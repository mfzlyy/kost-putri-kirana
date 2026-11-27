<?php

namespace App\Console\Commands;

use App\Enums\StatusSinkronisasi;
use App\Jobs\KirimSinkronisasiErp;
use App\Models\SinkronisasiErp;
use App\Services\Odoo\IntegrasiErpService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Mengirim baris sinkronisasi_erp (semua jenis) yang masih menunggu, misalnya baris tanpa job di antrean.
 * Baris gagal tidak disentuh: kirim ulang baris gagal adalah F-25.
 */
class ErpKirimTertunda extends Command
{
    protected $signature = 'erp:kirim-tertunda {--sekarang : Kirim langsung tanpa worker antrean; baris yang sedang dikirim proses lain dilewati}';

    protected $description = 'Kirim transaksi yang masih menunggu sinkronisasi ke Odoo';

    public function handle(): int
    {
        $tertunda = SinkronisasiErp::query()->menunggu()->orderBy('id')->get();

        return $this->option('sekarang') ? $this->kirimSekarang($tertunda) : $this->masukkanAntrean($tertunda);
    }

    /**
     * @param  Collection<int, SinkronisasiErp>  $tertunda
     */
    private function masukkanAntrean(Collection $tertunda): int
    {
        // Job unik: baris yang job-nya masih ada di antrean tidak dimasukkan dua kali.
        $dilewati = 0;
        Event::listen(function (UniqueJobSkipped $event) use (&$dilewati) {
            $dilewati++;
        });

        foreach ($tertunda as $baris) {
            KirimSinkronisasiErp::dispatch($baris);
        }

        $this->info(($tertunda->count() - $dilewati).' baris dimasukkan antrean.');

        if ($dilewati > 0) {
            $this->line("{$dilewati} baris dilewati karena job-nya masih ada di antrean.");
        }

        $this->line('Pastikan worker antrean berjalan (composer run dev).');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, SinkronisasiErp>  $tertunda
     */
    private function kirimSekarang(Collection $tertunda): int
    {
        if ($tertunda->isEmpty()) {
            $this->info('Tidak ada transaksi yang menunggu dikirim.');

            return self::SUCCESS;
        }

        // Baris yang kunci baris atau kunci partnernya dipegang proses lain tidak dikirim di sini.
        $dilewati = [];
        Event::listen(IntegrasiErpService::PERISTIWA_DILEWATI, function (int $id) use (&$dilewati) {
            $dilewati[] = $id;
        });

        foreach ($tertunda as $baris) {
            try {
                KirimSinkronisasiErp::dispatchSync($baris);
            } catch (Throwable) {
                // Sudah ditandai gagal dan dicatat oleh failed() pada job; lanjut ke baris berikutnya.
            }
        }

        $hasil = $tertunda->fresh();

        $this->table(
            ['referensi', 'status', 'pesan_error'],
            $hasil->map(fn (SinkronisasiErp $baris) => in_array($baris->id, $dilewati, true)
                ? [$baris->referensi_unik, 'sedang diproses, dilewati', '-']
                : [$baris->referensi_unik, $baris->status_sinkronisasi->value, $baris->pesan_error ?? '-'],
            )->all(),
        );

        $adaGagal = $hasil->whereNotIn('id', $dilewati)
            ->contains(fn (SinkronisasiErp $baris) => $baris->status_sinkronisasi === StatusSinkronisasi::Gagal);

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }
}
