<?php

use App\Enums\PeranPengguna;
use App\Models\PengaturanOperasional;
use App\Models\User;
use App\Support\MenuNavigasi;
use Illuminate\Support\Str;

dataset('semua_peran', ['pemilik', 'pengelola', 'penghuni', 'vendor']);

beforeEach(fn () => PengaturanOperasional::factory()->create());

it('F-03: menu setiap peran sesuai daftar PRD', function () {
    $label = fn (PeranPengguna $peran) => array_column(MenuNavigasi::untuk($peran), 'label');

    expect($label(PeranPengguna::Pemilik))->toBe([
        'Dashboard', 'Kamar', 'Penghuni', 'Kontrak', 'Tagihan', 'Riwayat transaksi', 'Tiket keluhan', 'Pengaturan',
    ])
        ->and($label(PeranPengguna::Pengelola))->toBe([
            'Verifikasi pembayaran', 'Tagihan', 'Kamar', 'Penghuni', 'Kontrak', 'Riwayat transaksi',
            'Tiket keluhan', 'Sinkronisasi ERP', 'Akun pengguna', 'Pengaturan',
        ])
        ->and($label(PeranPengguna::Penghuni))->toBe(['Tagihan saya', 'Riwayat transaksi', 'Keluhan saya', 'Profil saya'])
        ->and($label(PeranPengguna::Vendor))->toBe(['Permintaan kerja']);
});

it('F-03: setiap item menu mengarah ke route yang bisa dibuka perannya', function (string $peran) {
    $user = User::factory()->{$peran}()->create();

    foreach (MenuNavigasi::untuk($user->peran) as $item) {
        $this->actingAs($user)->get(route($item['route']))->assertOk();
    }
})->with('semua_peran');

it('F-03: setiap peran hanya melihat menu miliknya di halaman utamanya', function (string $peran) {
    $user = User::factory()->{$peran}()->create();
    $milik = MenuNavigasi::untuk($user->peran);
    $labelMilik = array_column($milik, 'label');

    $halaman = $this->actingAs($user)->get(route($user->peran->halamanUtama()))->assertOk();

    foreach ($labelMilik as $label) {
        $halaman->assertSee($label);
    }

    $menuPeranLain = collect(PeranPengguna::cases())
        ->reject(fn (PeranPengguna $lain) => $lain === $user->peran)
        ->flatMap(fn (PeranPengguna $lain) => MenuNavigasi::untuk($lain));

    $rutePeranIni = array_column($milik, 'route');

    foreach ($menuPeranLain as $item) {
        if (in_array($item['route'], $rutePeranIni, true)) {
            continue;
        }

        // Tautan menu peran lain tidak boleh ada.
        $halaman->assertDontSee('href="'.route($item['route']).'"', false);

        // Label dilewati bila sama dengan, atau bagian dari, teks milik sendiri (mis. "Tagihan" dan "Tagihan saya").
        $adaDiTeksSendiri = $item['label'] === $user->peran->label()
            || collect($labelMilik)->contains(fn (string $saya) => Str::contains($saya, $item['label']));

        if (! $adaDiTeksSendiri) {
            $halaman->assertDontSee($item['label']);
        }
    }
})->with('semua_peran');

it('F-03: contoh menu terlarang tidak muncul (penghuni tanpa Akun pengguna, vendor tanpa Tagihan)', function () {
    $this->actingAs(User::factory()->penghuni()->create())
        ->get('/tagihan-saya')->assertOk()->assertDontSee('Akun pengguna');

    $this->actingAs(User::factory()->vendor()->create())
        ->get('/permintaan-kerja')->assertOk()->assertDontSee('Tagihan');

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/dashboard')->assertOk()->assertDontSee('Akun pengguna')->assertDontSee('Verifikasi pembayaran');
});

it('F-03: menu pengguna menampilkan nama, peran, Ganti password, dan Keluar', function () {
    $user = User::factory()->pengelola()->create(['nama' => 'Dewi Pengelola Uji']);

    $this->actingAs($user)->get('/verifikasi-pembayaran')
        ->assertSee('Dewi Pengelola Uji')
        ->assertSee('Pengelola')
        ->assertSee('Ganti password')
        ->assertSee('Keluar')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation');
});
