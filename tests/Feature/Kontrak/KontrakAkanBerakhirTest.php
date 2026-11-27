<?php

use App\Enums\StatusKontrak;
use App\Models\PengaturanOperasional;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function bagianAkanBerakhir(User $user)
{
    return Livewire::actingAs($user)->test('pages::kontrak.index');
}

it('F-08 butir 1-2: kontrak dalam batas 30 hari tampil dengan penghuni, kamar, tanggal berakhir, dan sisa hari', function () {
    buatKontrakUji('03', 'Salsa Nabila', ['tanggal_berakhir' => '2026-12-01']); // 20 hari lagi
    buatKontrakUji('04', 'Di Luar Batas', ['tanggal_berakhir' => '2026-12-12']); // 31 hari lagi

    $komponen = bagianAkanBerakhir(User::factory()->pengelola()->create());

    $komponen->assertSee('Segera Berakhir')
        ->assertDontSee('Batas diatur di Pengaturan')
        ->assertSee('Salsa Nabila')
        ->assertSee('Berakhir 1 Desember 2026')
        ->assertSee('20 Hari Lagi');

    expect($komponen->instance()->akanBerakhir->map(fn ($kontrak) => $kontrak->penghuni->user->nama)->all())
        ->toBe(['Salsa Nabila']);
});

it('F-08: kontrak yang berakhir tepat pada hari ke-30 masih tampil, hari ke-31 tidak', function () {
    buatKontrakUji('01', 'Hari Ke Tiga Puluh', ['tanggal_berakhir' => '2026-12-11']);
    buatKontrakUji('02', 'Hari Ke Tiga Puluh Satu', ['tanggal_berakhir' => '2026-12-12']);

    $komponen = bagianAkanBerakhir(User::factory()->pengelola()->create());

    expect($komponen->instance()->akanBerakhir->pluck('kamar.nomor_kamar')->all())->toBe(['01']);
    $komponen->assertSee('30 Hari Lagi');
});

it('F-08: kontrak yang berakhir hari ini tampil sebagai Berakhir Hari Ini', function () {
    buatKontrakUji('01', 'Berakhir Hari Ini', ['tanggal_berakhir' => '2026-11-11']);

    bagianAkanBerakhir(User::factory()->pengelola()->create())
        ->assertSee('Berakhir Hari Ini');
});

it('F-08: kontrak aktif yang tanggalnya sudah lewat tampil sebagai Lewat n hari sampai command berjalan', function () {
    buatKontrakUji('06', 'Laila Kusuma', ['tanggal_berakhir' => '2026-11-07']);

    bagianAkanBerakhir(User::factory()->pengelola()->create())
        ->assertSee('Laila Kusuma')
        ->assertSee('Lewat 4 Hari');
});

it('F-08: urutan menurut tanggal berakhir menaik', function () {
    buatKontrakUji('01', 'Terakhir', ['tanggal_berakhir' => '2026-12-05']);
    buatKontrakUji('02', 'Pertama', ['tanggal_berakhir' => '2026-11-07']);
    buatKontrakUji('03', 'Kedua', ['tanggal_berakhir' => '2026-11-20']);

    $komponen = bagianAkanBerakhir(User::factory()->pengelola()->create());

    expect($komponen->instance()->akanBerakhir->map(fn ($kontrak) => $kontrak->penghuni->user->nama)->all())
        ->toBe(['Pertama', 'Kedua', 'Terakhir']);
});

it('F-08: kontrak berstatus berakhir tidak tampil', function () {
    buatKontrakUji('01', 'Sudah Selesai', ['tanggal_berakhir' => '2026-11-20', 'status_kontrak' => StatusKontrak::Berakhir]);

    $komponen = bagianAkanBerakhir(User::factory()->pengelola()->create());

    expect($komponen->instance()->akanBerakhir)->toHaveCount(0);
});

it('F-08 butir 3: tanpa kontrak yang akan berakhir tampil pesan kosong', function () {
    buatKontrakUji('01', 'Masih Lama', ['tanggal_berakhir' => '2027-06-30']);

    bagianAkanBerakhir(User::factory()->pengelola()->create())
        ->assertSee('Tidak ada kontrak yang akan berakhir.');
});

it('F-08 butir 3: bagian akan berakhir juga tampil bagi pemilik', function () {
    buatKontrakUji('01', 'Salsa Nabila', ['tanggal_berakhir' => '2026-11-20']);

    bagianAkanBerakhir(User::factory()->pemilik()->create())
        ->assertSee('Segera Berakhir')
        ->assertSee('Salsa Nabila');
});

it('ISU-02: batas yang dibaca dari pengaturan menentukan jangkauan daftar', function () {
    buatKontrakUji('01', 'Dalam Enam Puluh Hari', ['tanggal_berakhir' => '2027-01-05']); // 55 hari lagi

    PengaturanOperasional::aktif()->update(['batas_peringatan_kontrak_hari' => 60]);

    bagianAkanBerakhir(User::factory()->pengelola()->create())
        ->assertSee('Segera Berakhir')
        ->assertSee('Dalam Enam Puluh Hari')
        ->assertSee('55 Hari Lagi');
});

it('ISU-02: batas belum diatur menampilkan callout dan tidak memakai angka bawaan', function () {
    PengaturanOperasional::aktif()->update(['batas_peringatan_kontrak_hari' => null]);
    buatKontrakUji('01', 'Salsa Nabila', ['tanggal_berakhir' => '2026-11-20']);

    bagianAkanBerakhir(User::factory()->pengelola()->create())
        ->assertSee('Segera Berakhir')
        ->assertSee('Batas peringatan belum diatur.')
        ->assertSee('Atur di Pengaturan')
        ->assertSeeHtml(route('pengaturan.index'))
        ->assertDontSee('Hari Lagi')
        ->assertDontSee('30 hari');

    bagianAkanBerakhir(User::factory()->pemilik()->create())
        ->assertSee('Batas peringatan belum diatur.')
        ->assertDontSee('Pengelola belum mengatur batasnya di Pengaturan.')
        ->assertDontSee('Atur di Pengaturan');
});
