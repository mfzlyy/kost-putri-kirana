<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Jobs\KirimSinkronisasiErp;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->kunci = konfigurasiOdooUji();
});

/**
 * Baris sinkronisasi pembayaran tanpa job (seperti baris yang dibuat sebelum ada pengiriman otomatis).
 * $sinkron: 'menunggu', 'terkirim', atau 'gagal'.
 */
function barisTanpaJob(string $nomor, string $sinkron = 'menunggu'): SinkronisasiErp
{
    return SinkronisasiErp::withoutEvents(fn () => buatPembayaranDiterima(
        buatKontrakUji($nomor, "Penghuni {$nomor}"), '2026-11-01', '2026-11-03', '2026-11-04 09:00', sinkron: $sinkron,
    )->sinkronisasi);
}

/**
 * Odoo yang selalu menjawab faktur sudah lunas.
 */
function fakeOdooSudahLunas(): void
{
    fakeOdooPembayaran([
        'account.move/search_read' => Http::response(fakturOdoo('paid')),
        'account.move/read' => Http::response(fakturOdoo('paid')),
    ]);
}

it('memasukkan antrean hanya baris pembayaran yang menunggu', function () {
    Queue::fake();
    $menunggu = [barisTanpaJob('01'), barisTanpaJob('02')];
    barisTanpaJob('03', 'terkirim');
    barisTanpaJob('04', 'gagal');
    $tiket = TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0001']);
    SinkronisasiErp::factory()->untukTiket($tiket, JenisTransaksiErp::BiayaPerbaikan)->create();
    Queue::assertNothingPushed();

    $this->artisan('erp:kirim-tertunda')
        ->expectsOutputToContain('2 baris dimasukkan antrean.')
        ->expectsOutputToContain('Pastikan worker antrean berjalan (composer run dev).')
        ->doesntExpectOutputToContain('dilewati')
        ->assertExitCode(0);

    Queue::assertPushed(KirimSinkronisasiErp::class, 2);
    expect(Queue::pushed(KirimSinkronisasiErp::class)->map(fn (KirimSinkronisasiErp $job) => $job->sinkronisasi->id)->all())
        ->toBe([$menunggu[0]->id, $menunggu[1]->id]);
    Http::assertNothingSent();
});

it('baris yang job-nya masih di antrean tidak digandakan dan dilaporkan dilewati', function () {
    Queue::fake();
    barisTanpaJob('01');

    $this->artisan('erp:kirim-tertunda')->assertExitCode(0);
    $this->artisan('erp:kirim-tertunda')
        ->expectsOutputToContain('0 baris dimasukkan antrean.')
        ->expectsOutputToContain('1 baris dilewati karena job-nya masih ada di antrean.')
        ->assertExitCode(0);

    Queue::assertPushed(KirimSinkronisasiErp::class, 1);
});

it('--sekarang mengirim langsung tanpa worker dan mencetak tabel hasil', function () {
    fakeOdooSudahLunas();
    config(['queue.default' => 'database']);
    $satu = barisTanpaJob('01');
    $dua = barisTanpaJob('02');
    $gagal = barisTanpaJob('03', 'gagal');

    $this->artisan('erp:kirim-tertunda --sekarang')
        ->expectsTable(['referensi', 'status', 'pesan_error'], [
            [$satu->referensi_unik, 'terkirim', '-'],
            [$dua->referensi_unik, 'terkirim', '-'],
        ])
        ->assertExitCode(0);

    expect($satu->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($dua->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($gagal->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal);
    $this->assertDatabaseCount('jobs', 0);
});

it('--sekarang melaporkan kegagalan di tabel, exit 1, dan outputnya tidak memuat API key', function () {
    Http::fake(['*' => Http::response([
        'name' => 'odoo.exceptions.AccessError',
        'message' => "ditolak untuk {$this->kunci}",
    ], 403)]);
    $baris = barisTanpaJob('01');

    $kode = Artisan::call('erp:kirim-tertunda', ['--sekarang' => true]);
    $keluaran = Artisan::output();

    expect($kode)->toBe(1)
        ->and($keluaran)->toContain($baris->referensi_unik)
        ->and($keluaran)->toContain('gagal')
        ->and($keluaran)->toContain('Odoo menolak: odoo.exceptions.AccessError')
        ->and($keluaran)->not->toContain($this->kunci);
});

it('--sekarang tetap mengirim baris berikutnya setelah galat tak terduga', function () {
    // Balasan pertama bukan JSON (galat tak terduga); baris kedua berjalan normal.
    fakeOdooPembayaran([
        'res.partner/search_read' => Http::sequence()->push('<html></html>')->push([['id' => 7]]),
        'account.move/search_read' => Http::response(fakturOdoo('paid')),
        'account.move/read' => Http::response(fakturOdoo('paid')),
    ]);
    $satu = barisTanpaJob('01');
    $dua = barisTanpaJob('02');

    $this->artisan('erp:kirim-tertunda --sekarang')
        ->expectsTable(['referensi', 'status', 'pesan_error'], [
            [$satu->referensi_unik, 'gagal', 'Kesalahan tak terduga saat mengirim ke Odoo.'],
            [$dua->referensi_unik, 'terkirim', '-'],
        ])
        ->assertExitCode(1);
});

it('--sekarang tanpa baris menunggu selesai tanpa memanggil Odoo', function () {
    barisTanpaJob('01', 'terkirim');

    $this->artisan('erp:kirim-tertunda --sekarang')
        ->expectsOutputToContain('Tidak ada pembayaran yang menunggu dikirim.')
        ->assertExitCode(0);

    Http::assertNothingSent();
});
