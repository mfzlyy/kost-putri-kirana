<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\MenuNavigasi;

beforeEach(fn () => PengaturanOperasional::factory()->create());

it('halaman penghuni memuat menu tab bawah dengan empat tab dan aria-current pada tab aktif', function (string $url, string $tabAktif) {
    $penghuni = buatKontrakUji('05', 'Maya Sari')->penghuni->user;

    $html = $this->actingAs($penghuni)->get($url)->assertOk()->getContent();

    expect($html)->toContain('aria-label="Menu penghuni"');

    preg_match('#<nav\s+aria-label="Menu penghuni".*?</nav>#s', $html, $nav);
    $menu = $nav[0] ?? '';

    foreach (['Tagihan', 'Riwayat', 'Keluhan', 'Profil'] as $label) {
        expect($menu)->toContain('>'.$label.'<');
    }

    expect(substr_count($menu, 'aria-current="page"'))->toBe(1)
        ->and($menu)->toMatch('#aria-current="page"[^>]*>.*?<span>'.$tabAktif.'</span>#s');
})->with([
    'tagihan' => ['/tagihan-saya', 'Tagihan'],
    'riwayat' => ['/riwayat-transaksi', 'Riwayat'],
    'keluhan' => ['/keluhan-saya', 'Keluhan'],
    'profil' => ['/profil-saya', 'Profil'],
]);

it('tab penghuni memakai rute yang sama dengan menu sidebar penghuni', function () {
    $tab = array_column(MenuNavigasi::tabPenghuni(), 'route');
    $menu = array_column(MenuNavigasi::untuk(PeranPengguna::Penghuni), 'route');

    expect($tab)->toBe($menu)
        ->and(array_column(MenuNavigasi::tabPenghuni(), 'label'))->toBe(['Tagihan', 'Riwayat', 'Keluhan', 'Profil']);
});

it('halaman unggah tidak memuat menu tab', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $tagihan = Tagihan::factory()->create(['kontrak_id' => $kontrak->id, 'status_tagihan' => StatusTagihan::BelumDibayar]);

    $this->actingAs($kontrak->penghuni->user)
        ->get(route('tagihan-saya.unggah', $tagihan))
        ->assertOk()
        ->assertDontSee('Menu penghuni')
        ->assertSee('Kembali ke tagihan');
});

it('halaman peran lain tidak memuat menu tab penghuni', function (string $peran, string $url) {
    $this->actingAs(User::factory()->{$peran}()->create())
        ->get($url)
        ->assertOk()
        ->assertDontSee('Menu penghuni');
})->with([
    'pengelola' => ['pengelola', '/tagihan'],
    'pemilik' => ['pemilik', '/tagihan'],
    'vendor' => ['vendor', '/permintaan-kerja'],
]);

it('Profil saya memuat tautan Ganti password dan tombol Keluar untuk ponsel', function () {
    $penghuni = buatKontrakUji('05', 'Maya Sari')->penghuni->user;

    $this->actingAs($penghuni)
        ->get('/profil-saya')
        ->assertOk()
        ->assertSee('data-test="kotak-akun"', false)
        ->assertSee('Ganti password')
        ->assertSee(route('security.edit'), false)
        ->assertSee('data-test="keluar-ponsel"', false)
        ->assertSee('action="'.route('logout').'"', false);
});

it('bilah gelap atas disembunyikan di ponsel hanya untuk penghuni', function () {
    $penghuni = buatKontrakUji('05', 'Maya Sari')->penghuni->user;

    $this->actingAs($penghuni)->get('/tagihan-saya')->assertSee('max-md:hidden', false);
    $this->actingAs(User::factory()->pengelola()->create())->get('/tagihan')->assertDontSee('max-md:hidden', false);
});
