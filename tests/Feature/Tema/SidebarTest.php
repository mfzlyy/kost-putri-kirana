<?php

use App\Models\User;

it('sidebar semua peran memakai blok pengguna yang sama dengan tautan dan tombol identik', function (string $peran) {
    $user = User::factory()->{$peran}()->create(['nama' => 'Nama Uji '.$peran]);

    $html = $this->actingAs($user)->get(route($user->peran->halamanUtama()))->assertOk()->getContent();

    preg_match('/<a\s[^>]*class="([^"]*)"[^>]*>\s*Ganti password\s*<\/a>/', $html, $tautan);
    preg_match('/<button\s[^>]*class="([^"]*)"[^>]*>\s*Keluar\s*<\/button>/', $html, $tombol);

    expect($tautan)->not->toBeEmpty()
        ->and($tombol)->not->toBeEmpty()
        ->and($tautan[1])->toBe($tombol[1])
        ->and($tautan[1])->toMatch('/\bunderline\b/')
        ->and($tautan[1])->not->toMatch('/font-semibold|font-bold/')
        ->and($html)->toContain('Nama Uji '.$peran)
        ->and(substr_count($html, 'Ganti password'))->toBe(1)
        ->and(substr_count($html, 'data-test="logout-button"'))->toBe(1);
})->with(['pemilik', 'pengelola', 'penghuni', 'vendor']);

it('sidebar semua peran memakai kerangka yang sama dan hanya isi menu yang berbeda', function (string $peran) {
    $user = User::factory()->{$peran}()->create();

    $html = $this->actingAs($user)->get(route($user->peran->halamanUtama()))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Menu utama"')
        ->and($html)->toContain('w-[232px]')
        ->and($html)->toContain('data-flux-header');
})->with(['pemilik', 'pengelola', 'penghuni', 'vendor']);
