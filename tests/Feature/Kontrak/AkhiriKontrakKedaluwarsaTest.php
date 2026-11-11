<?php

use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Tagihan;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-11 00:10:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

it('F-07 butir 5: kontrak aktif yang berakhir kemarin diakhiri; hari ini dan besok tetap aktif', function () {
    $kemarin = buatKontrakUji('01', 'Kemarin', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-10']);
    $hariIni = buatKontrakUji('02', 'Hari Ini', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-11']);
    $besok = buatKontrakUji('03', 'Besok', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-12']);

    $this->artisan('kontrak:akhiri-kedaluwarsa')
        ->expectsOutput('1 kontrak diakhiri otomatis.')
        ->assertSuccessful();

    expect($kemarin->fresh()->status_kontrak)->toBe(StatusKontrak::Berakhir)
        ->and($hariIni->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif)
        ->and($besok->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif)
        ->and($kemarin->kamar->fresh()->status_hunian)->toBe(Kamar::HUNIAN_KOSONG)
        ->and($hariIni->kamar->fresh()->status_hunian)->toBe(Kamar::HUNIAN_TERISI);
});

it('F-07: command tidak mengubah tanggal kontrak, tagihan, maupun status penghuni', function () {
    $kontrak = buatKontrakUji('01', 'Kemarin', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-10']);
    $tagihan = Tagihan::factory()->belumDibayar()->create(['kontrak_id' => $kontrak->id]);

    $this->artisan('kontrak:akhiri-kedaluwarsa')->assertSuccessful();

    expect($kontrak->fresh()->tanggal_berakhir->toDateString())->toBe('2026-11-10')
        ->and($kontrak->fresh()->tanggal_mulai->toDateString())->toBe('2026-01-01')
        ->and($tagihan->fresh()->status_tagihan)->toBe($tagihan->status_tagihan)
        ->and($kontrak->penghuni->fresh()->status_penghuni)->toBe(StatusPenghuni::Aktif);
});

it('F-07: aman dijalankan berkali-kali dan melaporkan jumlah yang benar', function () {
    buatKontrakUji('01', 'Satu', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-10']);
    buatKontrakUji('02', 'Dua', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-10-31']);
    buatKontrakUji('03', 'Tiga', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-01-31']);
    buatKontrakUji('04', 'Sudah Selesai', ['tanggal_mulai' => '2025-01-01', 'tanggal_berakhir' => '2025-12-31', 'status_kontrak' => StatusKontrak::Berakhir]);

    $this->artisan('kontrak:akhiri-kedaluwarsa')->expectsOutput('2 kontrak diakhiri otomatis.')->assertSuccessful();
    $this->artisan('kontrak:akhiri-kedaluwarsa')->expectsOutput('0 kontrak diakhiri otomatis.')->assertSuccessful();

    expect(Kontrak::query()->aktif()->count())->toBe(1)
        ->and(Kontrak::query()->where('status_kontrak', StatusKontrak::Berakhir->value)->count())->toBe(3);
});

it('F-07: log hanya memuat jumlah tanpa nama atau data penghuni', function () {
    buatKontrakUji('01', 'Nama Rahasia', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-10']);

    Log::spy();

    $this->artisan('kontrak:akhiri-kedaluwarsa')->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $pesan) => str_contains($pesan, '1') && ! str_contains($pesan, 'Nama Rahasia')
    );
});

it('F-07: batas hari ditentukan menurut tanggal di zona Asia/Jakarta', function () {
    // 23:30 WIB tanggal 10 = 16:30 UTC tanggal 10; di Jakarta "hari ini" masih tanggal 10.
    Carbon::setTestNow(Carbon::parse('2026-11-10 23:30:00', 'Asia/Jakarta'));
    $kontrak = buatKontrakUji('01', 'Berakhir Tanggal Sepuluh', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-10']);

    $this->artisan('kontrak:akhiri-kedaluwarsa')->expectsOutput('0 kontrak diakhiri otomatis.');
    expect($kontrak->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif);

    Carbon::setTestNow(Carbon::parse('2026-11-11 00:10:00', 'Asia/Jakarta'));
    $this->artisan('kontrak:akhiri-kedaluwarsa')->expectsOutput('1 kontrak diakhiri otomatis.');
    expect($kontrak->fresh()->status_kontrak)->toBe(StatusKontrak::Berakhir);
});

it('F-07: jadwal harian 00:10 Asia/Jakarta terdaftar tanpa tumpang tindih', function () {
    $acara = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'kontrak:akhiri-kedaluwarsa'));

    expect($acara)->not->toBeNull()
        ->and($acara->expression)->toBe('10 0 * * *')
        ->and($acara->timezone)->toBe('Asia/Jakarta')
        ->and($acara->withoutOverlapping)->toBeTrue();

    $this->artisan('schedule:list')->expectsOutputToContain('kontrak:akhiri-kedaluwarsa')->assertSuccessful();
});
