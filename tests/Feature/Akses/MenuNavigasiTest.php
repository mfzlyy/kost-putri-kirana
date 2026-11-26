<?php

use App\Enums\PeranPengguna;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\User;
use App\Support\MenuNavigasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

dataset('semua_peran', ['pemilik', 'pengelola', 'penghuni', 'vendor']);

beforeEach(fn () => PengaturanOperasional::factory()->create());

it('F-03: menu setiap peran sesuai daftar PRD', function () {
    $label = fn (PeranPengguna $peran) => array_column(MenuNavigasi::untuk($peran), 'label');

    expect($label(PeranPengguna::Pemilik))->toBe([
        'Dasbor', 'Kamar', 'Penghuni', 'Kontrak', 'Tagihan', 'Riwayat', 'Keluhan', 'Pengaturan',
    ])
        ->and($label(PeranPengguna::Pengelola))->toBe([
            'Verifikasi', 'Tagihan', 'Kamar', 'Penghuni', 'Kontrak', 'Riwayat',
            'Keluhan', 'Sinkronisasi', 'Akun', 'Pengaturan',
        ])
        ->and($label(PeranPengguna::Penghuni))->toBe(['Tagihan', 'Riwayat', 'Keluhan', 'Profil'])
        ->and($label(PeranPengguna::Vendor))->toBe(['Pekerjaan']);
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
        ->get('/tagihan-saya')->assertOk()->assertDontSee('>Akun<', false);

    $this->actingAs(User::factory()->vendor()->create())
        ->get('/permintaan-kerja')->assertOk()->assertDontSee('Tagihan');

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/dashboard')->assertOk()->assertDontSee('>Akun<', false)->assertDontSee('>Verifikasi<', false);
});

it('F-25: menu Sinkronisasi pengelola berangka jumlah transaksi gagal, dengan satu query', function () {
    Queue::fake();
    SinkronisasiErp::factory()->gagal()->count(2)->create();
    SinkronisasiErp::factory()->terkirim()->create();
    SinkronisasiErp::factory()->create();

    $item = collect(MenuNavigasi::denganAngka(MenuNavigasi::untuk(PeranPengguna::Pengelola), PeranPengguna::Pengelola))
        ->firstWhere('route', 'sinkronisasi-erp.index');

    expect($item['jumlah'])->toBe(2)
        ->and($item['antrean'])->toBe('sinkronisasi')
        ->and($item['satuan'])->toBe('sinkronisasi gagal');

    $pengelola = User::factory()->pengelola()->create();
    DB::enableQueryLog();
    $this->actingAs($pengelola)->get('/kamar')->assertOk()
        ->assertSee('x-on:antrean-sinkronisasi.window="n = $event.detail.jumlah"', false)
        ->assertSee('2 sinkronisasi gagal');
    $query = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'sinkronisasi_erp'));
    DB::disableQueryLog();

    expect($query)->toHaveCount(1);
});

it('F-25: angka menu Sinkronisasi tidak tampil saat tidak ada yang gagal', function () {
    Queue::fake();
    SinkronisasiErp::factory()->terkirim()->create();

    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/kamar')->assertOk()->getContent();

    preg_match('#<span\s+x-data="\{ n: (\d+) \}"\s+x-on:antrean-sinkronisasi.*?style="display: none"#s', $html, $cocok);

    expect($cocok)->not->toBeEmpty()->and($cocok[1])->toBe('0');
});

it('F-25: angka dan menu Sinkronisasi tidak ada untuk peran lain', function (string $peran, string $url) {
    Queue::fake();
    SinkronisasiErp::factory()->gagal()->create();

    $this->actingAs(User::factory()->{$peran}()->create())->get($url)->assertOk()
        ->assertDontSee('antrean-sinkronisasi', false)
        ->assertDontSee('sinkronisasi gagal')
        ->assertDontSee('href="'.route('sinkronisasi-erp.index').'"', false);
})->with([
    ['pemilik', '/kamar'],
    ['penghuni', '/tagihan-saya'],
    ['vendor', '/permintaan-kerja'],
]);

it('F-03: menu pengguna menampilkan nama, peran, Ganti password, dan Keluar', function () {
    $user = User::factory()->pengelola()->create(['nama' => 'Dewi Pengelola Uji']);

    $this->actingAs($user)->get('/verifikasi-pembayaran')
        ->assertSee('Dewi Pengelola Uji')
        ->assertSee('Pengelola')
        ->assertSee('Ganti Password')
        ->assertSee('Keluar')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation');
});
