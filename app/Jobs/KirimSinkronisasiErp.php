<?php

namespace App\Jobs;

use App\Enums\StatusSinkronisasi;
use App\Models\SinkronisasiErp;
use App\Services\Odoo\IntegrasiErpService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim satu baris sinkronisasi_erp ke Odoo lewat antrean.
 *
 * Tanpa percobaan ulang otomatis: kegagalan Odoo sudah dicatat sebagai gagal oleh service, dan kirim ulang
 * dilakukan pengelola (F-25) atau perintah erp:kirim-tertunda.
 */
#[Tries(1)]
#[UniqueFor(600)]
#[DeleteWhenMissingModels]
class KirimSinkronisasiErp implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const PESAN_TAK_TERDUGA = 'Kesalahan tak terduga saat mengirim ke Odoo.';

    public function __construct(public SinkronisasiErp $sinkronisasi) {}

    public function uniqueId(): string
    {
        return (string) $this->sinkronisasi->getKey();
    }

    public function handle(IntegrasiErpService $service): void
    {
        $service->kirim($this->sinkronisasi);
    }

    /**
     * Untuk galat yang lolos dari service, termasuk worker yang kehabisan waktu.
     */
    public function failed(?Throwable $e): void
    {
        $baris = $this->sinkronisasi->fresh();

        if ($baris === null || $baris->status_sinkronisasi === StatusSinkronisasi::Terkirim) {
            return;
        }

        $baris->forceFill([
            'status_sinkronisasi' => StatusSinkronisasi::Gagal,
            'pesan_error' => self::PESAN_TAK_TERDUGA,
            'waktu_kirim' => now(),
        ])->save();

        // Hanya nama kelas: pesan galat tak terduga bisa memuat data yang tidak boleh masuk log.
        Log::error(self::PESAN_TAK_TERDUGA, [
            'referensi_unik' => $baris->referensi_unik,
            'exception' => $e === null ? null : $e::class,
        ]);
    }
}
