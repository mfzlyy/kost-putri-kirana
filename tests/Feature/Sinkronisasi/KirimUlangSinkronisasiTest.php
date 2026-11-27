<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Exceptions\SinkronisasiTidakBisaDikirimUlang;
use App\Jobs\KirimSinkronisasiErp;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:40:00', 'Asia/Jakarta'));
    Queue::fake();
    PengaturanOperasional::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Baris sinkronisasi pembayaran pada $status, dibuat tanpa event supaya belum ada job pengirim.
 */
function barisKirimUlang(string $kamar = '02', string $status = 'gagal'): SinkronisasiErp
{
    return SinkronisasiErp::withoutEvents(fn () => buatPembayaranDiterima(
        buatKontrakUji($kamar, 'Rina Maharani'), '2026-11-01', '2026-11-03', '2026-11-04 09:00', 1250000, sinkron: $status,
    )->sinkronisasi);
}

function komponenKirimUlang(?User $pengelola = null, array $query = []): Testable
{
    return Livewire::withQueryParams($query)
        ->actingAs($pengelola ?? User::factory()->pengelola()->create())
        ->test('pages::sinkronisasi-erp.index');
}

function toastBerisi(string $varian, string $teks): Closure
{
    return fn (string $nama, array $data) => str_contains(json_encode($data), $varian)
        && str_contains(json_encode($data), $teks);
}

/** Job pengirim yang di-push untuk baris itu. */
function jobUntuk(SinkronisasiErp $baris): int
{
    return Queue::pushed(KirimSinkronisasiErp::class, fn (KirimSinkronisasiErp $job) => $job->sinkronisasi->id === $baris->id)->count();
}

it('F-25 butir 3: kirim ulang mengembalikan baris ke menunggu dan mengantrekan tepat satu job', function () {
    $baris = barisKirimUlang();
    $dicoba = $baris->fresh()->waktu_kirim;
    $lain = barisKirimUlang('03');
    Carbon::setTestNow(now()->addHour());

    $komponen = komponenKirimUlang()
        ->assertSee($baris->referensi_unik)
        ->call('kirimUlang', $baris->id)
        ->assertDispatched('toast-show', toastBerisi('success', $baris->referensi_unik.' dikirim ulang.'))
        ->assertDispatched('antrean-sinkronisasi', jumlah: 1)
        ->assertDontSee($baris->referensi_unik.'</td>', false)
        ->assertSee($lain->referensi_unik);

    $sesudah = $baris->fresh();

    expect($sesudah->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu)
        ->and($sesudah->pesan_error)->toBeNull()
        ->and($sesudah->waktu_kirim->equalTo($dicoba))->toBeTrue()
        ->and($sesudah->referensi_unik)->toBe($baris->referensi_unik)
        ->and($sesudah->odoo_record_id)->toBeNull()
        ->and($komponen->instance()->hitungan)->toBe(['semua' => 2, 'gagal' => 1, 'menunggu' => 1, 'terkirim' => 0])
        ->and($komponen->instance()->halaman->pluck('id')->all())->toBe([$lain->id])
        ->and(jobUntuk($baris))->toBe(1)
        ->and($lain->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal);

    Queue::assertPushed(KirimSinkronisasiErp::class, 1);
    Http::assertNothingSent();
});

it('F-25: baris tiket yang gagal juga bisa dikirim ulang', function (JenisTransaksiErp $jenis) {
    $baris = SinkronisasiErp::withoutEvents(fn () => SinkronisasiErp::factory()
        ->untukTiket(TiketKeluhan::factory()->selesai()->create(), $jenis)
        ->gagal()
        ->create());

    komponenKirimUlang()->call('kirimUlang', $baris->id);

    expect($baris->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu)
        ->and(jobUntuk($baris))->toBe(1);
})->with([JenisTransaksiErp::BiayaPerbaikan, JenisTransaksiErp::Pemeliharaan]);

it('F-25: pembayaran tetap diterima dan tagihan tetap lunas selama dan sesudah kirim ulang', function () {
    $baris = barisKirimUlang();
    $pembayaran = $baris->pembayaran;
    $sebelum = [$pembayaran->fresh()->getAttributes(), $pembayaran->tagihan->fresh()->getAttributes()];

    komponenKirimUlang()->call('kirimUlang', $baris->id);

    expect([$pembayaran->fresh()->getAttributes(), $pembayaran->tagihan->fresh()->getAttributes()])->toBe($sebelum);
});

it('F-25: di chip Semua baris tetap tampil sebagai Menunggu dan polling menyala', function () {
    $baris = barisKirimUlang();

    komponenKirimUlang(query: ['status' => 'semua'])
        ->assertDontSee('wire:poll', false)
        ->call('kirimUlang', $baris->id)
        ->assertSee($baris->referensi_unik)
        ->assertSee('wire:poll.5s.visible="muatUlang"', false)
        ->assertDontSee('data-test="kirim-ulang', false);
});

it('F-25: mengirim ulang satu-satunya baris di halaman 2 kembali ke halaman 1', function () {
    $terlama = barisKirimUlang('21');
    $terlama->forceFill(['waktu_kirim' => now()->subMonth()])->save();
    foreach (range(1, 20) as $n) {
        barisKirimUlang(sprintf('%02d', $n));
    }

    $komponen = komponenKirimUlang()->call('gotoPage', 2)->call('kirimUlang', $terlama->id);

    expect($komponen->instance()->halaman->currentPage())->toBe(1)
        ->and($komponen->instance()->halaman)->toHaveCount(20);
});

it('F-25: kirim ulang baris yang bukan gagal memberi peringatan tanpa job', function (string $status) {
    $baris = barisKirimUlang(status: $status);
    $sebelum = $baris->fresh()->getAttributes();

    komponenKirimUlang()
        ->call('kirimUlang', $baris->id)
        ->assertDispatched('toast-show', toastBerisi('warning', 'Status transaksi ini sudah berubah. Daftar sudah diperbarui.'))
        ->assertDispatched('antrean-sinkronisasi', jumlah: 0);

    expect($baris->fresh()->getAttributes())->toBe($sebelum);
    Queue::assertNothingPushed();
})->with(['menunggu', 'terkirim']);

it('F-25: baris yang berubah setelah halaman dibuka memuat ulang daftar', function () {
    $baris = barisKirimUlang();
    $komponen = komponenKirimUlang()->assertSee($baris->referensi_unik);

    $baris->forceFill(['status_sinkronisasi' => StatusSinkronisasi::Terkirim, 'odoo_record_id' => 9])->save();

    $komponen->call('kirimUlang', $baris->id)
        ->assertDispatched('toast-show', toastBerisi('warning', 'Status transaksi ini sudah berubah.'))
        ->assertSee('Tidak ada transaksi yang gagal.');

    Queue::assertNothingPushed();
});

it('F-25: klik ganda hanya mengantrekan satu job', function () {
    $baris = barisKirimUlang();

    komponenKirimUlang()
        ->call('kirimUlang', $baris->id)
        ->call('kirimUlang', $baris->id)
        ->assertDispatched('toast-show', toastBerisi('warning', 'Status transaksi ini sudah berubah.'));

    expect($baris->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu)
        ->and(jobUntuk($baris))->toBe(1);
});

it('F-25: ID yang tidak ada dijawab 404 tanpa job', function () {
    komponenKirimUlang()->call('kirimUlang', 987654)->assertStatus(404);

    Queue::assertNothingPushed();
});

it('F-25: aksi kirim ulang oleh non-pengelola ditolak tanpa perubahan', function (string $peran) {
    $baris = barisKirimUlang();
    $sebelum = $baris->fresh()->getAttributes();
    $komponen = komponenKirimUlang();

    // Komponen sudah terbuka, lalu permintaannya dikirim oleh akun lain.
    $this->actingAs(User::factory()->{$peran}()->create());

    $komponen->call('kirimUlang', $baris->id)->assertForbidden();

    expect($baris->fresh()->getAttributes())->toBe($sebelum);
    Queue::assertNothingPushed();
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-25: pengelola yang dinonaktifkan di tengah sesi tidak bisa mengirim ulang', function () {
    $baris = barisKirimUlang();
    $pengelola = User::factory()->pengelola()->create();
    $komponen = komponenKirimUlang($pengelola);

    $pengelola->nonaktifkan();

    $komponen->call('kirimUlang', $baris->id)->assertForbidden();

    expect($baris->fresh()->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal);
    Queue::assertNothingPushed();
});

it('F-25: model kirimUlang memakai status terkini di basis data, bukan salinan lama', function () {
    $baris = barisKirimUlang();
    $salinanLama = SinkronisasiErp::query()->findOrFail($baris->id);
    $pengelola = User::factory()->pengelola()->create();

    $baris->kirimUlang($pengelola);

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu)
        ->and(fn () => $salinanLama->kirimUlang($pengelola))
        ->toThrow(SinkronisasiTidakBisaDikirimUlang::class, SinkronisasiTidakBisaDikirimUlang::PESAN)
        ->and(jobUntuk($baris))->toBe(1);
});

it('F-25: kirim ulang dicatat di log dengan referensi dan ID pengelola saja', function () {
    $baris = barisKirimUlang();
    $pengelola = User::factory()->pengelola()->create();
    Log::spy();

    $baris->kirimUlang($pengelola);

    Log::shouldHaveReceived('info')->once()->with('Sinkronisasi ERP dikirim ulang.', [
        'referensi_unik' => $baris->referensi_unik,
        'pengelola_id' => $pengelola->id,
    ]);
});
