<?php

use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTagihan;
use App\Exceptions\PembayaranSudahDiputuskan;
use App\Jobs\KirimSinkronisasiErp;
use App\Models\Pembayaran;
use App\Models\SinkronisasiErp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    konfigurasiOdooUji();
});

afterEach(fn () => Carbon::setTestNow());

it('F-22: menerima pembayaran memasukkan tepat satu job untuk baris sinkronisasinya, setelah commit', function () {
    Queue::fake();
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    $p->terima(User::factory()->pengelola()->create());

    $baris = SinkronisasiErp::query()->sole();
    Queue::assertPushed(KirimSinkronisasiErp::class, 1);
    Queue::assertPushed(KirimSinkronisasiErp::class, fn (KirimSinkronisasiErp $job) => $job->sinkronisasi->is($baris)
        && $job->afterCommit === true);
    Http::assertNothingSent();
});

it('terima yang ditolak karena sudah diputuskan tidak memasukkan job', function () {
    Queue::fake();
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $p->tolak(User::factory()->pengelola()->create(), 'Bukti tidak terbaca.');

    expect(fn () => $p->terima(User::factory()->pengelola()->create()))->toThrow(PembayaranSudahDiputuskan::class);

    Queue::assertNothingPushed();
});

it('job baru masuk tabel jobs setelah transaksi commit, dan tidak pernah bila dibatalkan', function () {
    config(['queue.default' => 'database']);
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $pengelola = User::factory()->pengelola()->create();

    expect(fn () => DB::transaction(function () use ($p, $pengelola) {
        $p->terima($pengelola);
        expect(DB::table('jobs')->count())->toBe(0);

        throw new RuntimeException('batalkan');
    }))->toThrow(RuntimeException::class, 'batalkan');

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(SinkronisasiErp::query()->count())->toBe(0);

    Pembayaran::query()->findOrFail($p->id)->terima($pengelola);

    expect(DB::table('jobs')->count())->toBe(1);
    Http::assertNothingSent();
});

it('worker antrean database menjalankan job sampai baris terkirim', function () {
    config(['queue.default' => 'database']);
    fakeOdooPembayaran();
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $p->terima(User::factory()->pengelola()->create());

    expect(SinkronisasiErp::query()->sole()->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu);

    Artisan::call('queue:work', ['--once' => true]);

    expect(SinkronisasiErp::query()->sole()->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('kegagalan Odoo selama job berjalan tidak membatalkan status Lunas dan tidak membuat job gagal', function () {
    config(['queue.default' => 'database']);
    Http::fake(['*' => Http::response('Bad Gateway', 502)]);
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $p->terima(User::factory()->pengelola()->create());

    Artisan::call('queue:work', ['--once' => true]);

    expect(SinkronisasiErp::query()->sole()->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($p->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::Lunas)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('galat tak terduga di worker menandai baris gagal lewat failed()', function () {
    config(['queue.default' => 'database']);
    // Balasan 200 yang bukan JSON lolos dari service sebagai TypeError.
    Http::fake(['*' => Http::response('<html>bukan JSON</html>')]);
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $p->terima(User::factory()->pengelola()->create());

    Artisan::call('queue:work', ['--once' => true]);

    $baris = SinkronisasiErp::query()->sole();
    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Kesalahan tak terduga saat mengirim ke Odoo.')
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('F-23/F-24: menyelesaikan tiket memasukkan tepat satu job untuk setiap baris sinkronisasinya, setelah commit', function (?int $idPeralatan, array $referensi) {
    Queue::fake();

    $tiket = tiketErpUji(idPeralatan: $idPeralatan);

    $baris = $tiket->sinkronisasi()->orderBy('id')->get();
    expect($baris->pluck('referensi_unik')->all())->toBe($referensi);

    Queue::assertPushed(KirimSinkronisasiErp::class, count($referensi));
    foreach ($baris as $satu) {
        expect(Queue::pushed(KirimSinkronisasiErp::class, fn (KirimSinkronisasiErp $job) => $job->sinkronisasi->is($satu)
            && $job->afterCommit === true))->toHaveCount(1);
    }
    Http::assertNothingSent();
})->with([
    'fasilitas tertaut' => [42, ['KPK-TKT-0001-B', 'KPK-TKT-0001-M']],
    'fasilitas belum tertaut' => [null, ['KPK-TKT-0001-B']],
]);

it('job tiket baru masuk tabel jobs setelah commit, dan tidak pernah bila penyelesaian dibatalkan', function () {
    config(['queue.default' => 'database']);

    expect(fn () => DB::transaction(function () {
        tiketErpUji();
        expect(DB::table('jobs')->count())->toBe(0);

        throw new RuntimeException('batalkan');
    }))->toThrow(RuntimeException::class, 'batalkan');

    expect(DB::table('jobs')->count())->toBe(0);

    tiketErpUji(nomor: 'KLH-0002');

    expect(DB::table('jobs')->count())->toBe(2);
    Http::assertNothingSent();
});

it('worker antrean database mengirim baris biaya perbaikan dan pemeliharaan sampai terkirim', function () {
    config(['queue.default' => 'database']);
    fakeOdooBiaya();
    fakeOdooPemeliharaan();
    $tiket = tiketErpUji();

    Artisan::call('queue:work', ['--once' => true]);
    Artisan::call('queue:work', ['--once' => true]);

    expect($tiket->sinkronisasi()->orderBy('id')->pluck('status_sinkronisasi', 'referensi_unik')->map->value->all())
        ->toBe(['KPK-TKT-0001-B' => 'terkirim', 'KPK-TKT-0001-M' => 'terkirim'])
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('baris yang dibuat sudah terkirim atau gagal tidak memasukkan job', function () {
    Queue::fake();

    SinkronisasiErp::factory()->terkirim()->create();
    SinkronisasiErp::factory()->gagal()->create(['referensi_unik' => 'KPK-PAY-999999']);

    Queue::assertNothingPushed();
});

it('failed() menandai gagal dengan pesan umum dan mencatat referensi serta nama kelas saja', function () {
    Queue::fake();
    $baris = SinkronisasiErp::factory()->create();
    Log::spy();

    (new KirimSinkronisasiErp($baris))->failed(new LogicException('rahasia yang tidak boleh dicatat'));

    $baris->refresh();
    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Kesalahan tak terduga saat mengirim ke Odoo.')
        ->and($baris->waktu_kirim->equalTo(now()))->toBeTrue();
    Log::shouldHaveReceived('error')->once()->with('Kesalahan tak terduga saat mengirim ke Odoo.', [
        'referensi_unik' => $baris->referensi_unik,
        'exception' => LogicException::class,
    ]);
});

it('failed() tidak mengubah baris yang sudah terkirim', function () {
    Queue::fake();
    $baris = SinkronisasiErp::factory()->terkirim(12)->create();

    (new KirimSinkronisasiErp($baris))->failed(null);

    $baris->refresh();
    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->pesan_error)->toBeNull();
});

it('job unik per baris sinkronisasi sehingga tidak diantrekan dua kali', function () {
    Queue::fake();
    $baris = SinkronisasiErp::factory()->create();

    KirimSinkronisasiErp::dispatch($baris);

    $job = new KirimSinkronisasiErp($baris);
    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $baris->id);
    // Satu dari event created; dispatch kedua ditolak kunci unik.
    Queue::assertPushed(KirimSinkronisasiErp::class, 1);
});
