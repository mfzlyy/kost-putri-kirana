<?php

use App\Enums\PeranPengguna;
use App\Models\User;

dataset('peran_dan_halaman_utama', [
    'pemilik' => ['pemilik', 'dashboard'],
    'pengelola' => ['pengelola', 'verifikasi-pembayaran.index'],
    'penghuni' => ['penghuni', 'tagihan-saya.index'],
    'vendor' => ['vendor', 'permintaan-kerja.index'],
]);

dataset('halaman_terproteksi', [
    'beranda' => '/beranda',
    'dashboard' => '/dashboard',
    'akun' => '/akun',
    'pengaturan' => '/pengaturan',
    'kamar' => '/kamar',
    'penghuni' => '/penghuni',
    'kontrak' => '/kontrak',
    'tagihan' => '/tagihan',
    'verifikasi pembayaran' => '/verifikasi-pembayaran',
    'riwayat transaksi' => '/riwayat-transaksi',
    'tiket keluhan' => '/tiket-keluhan',
    'sinkronisasi erp' => '/sinkronisasi-erp',
    'tagihan saya' => '/tagihan-saya',
    'profil saya' => '/profil-saya',
    'keluhan saya' => '/keluhan-saya',
    'permintaan kerja' => '/permintaan-kerja',
    'ganti password' => '/settings/security',
]);

function loginDengan(string $email, string $password)
{
    return test()->post(route('login.store'), ['email' => $email, 'password' => $password]);
}

it('menampilkan halaman login berbahasa Indonesia tanpa ingat saya, lupa password, dan daftar', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Masuk')
        ->assertDontSee('Pakai email dan password dari pengelola.')
        ->assertSee('Lupa password? Hubungi pengelola kos.')
        ->assertDontSee('Ingat saya')
        ->assertDontSee('href="'.url('/forgot-password'))
        ->assertDontSee('Daftar');
});

it('F-01: login berhasil lalu diarahkan ke halaman utama perannya', function (string $peran, string $routeUtama) {
    $user = User::factory()->{$peran}()->create(['password_hash' => 'SandiUji12345']);

    loginDengan($user->email, 'SandiUji12345')
        ->assertSessionHasNoErrors()
        ->assertRedirect(route($routeUtama));

    $this->assertAuthenticatedAs($user);
    expect($user->peran->halamanUtama())->toBe($routeUtama);
})->with('peran_dan_halaman_utama');

it('F-01: password salah dan email tidak terdaftar menghasilkan pesan yang sama', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'SandiUji12345']);

    loginDengan($user->email, 'salah-salah')
        ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
    $this->assertGuest();

    loginDengan('tidak.ada@contoh.test', 'SandiUji12345')
        ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
    $this->assertGuest();
});

it('F-01: email dengan huruf besar dan spasi tetap bisa login', function () {
    $user = User::factory()->pengelola()->create([
        'email' => 'pengelola.uji@contoh.test',
        'password_hash' => 'SandiUji12345',
    ]);

    loginDengan('  Pengelola.Uji@Contoh.Test ', 'SandiUji12345')
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('verifikasi-pembayaran.index'));

    $this->assertAuthenticatedAs($user);
});

it('F-01: akun nonaktif dengan password benar ditolak dengan pesan nonaktif', function () {
    $user = User::factory()->penghuni()->nonaktif()->create(['password_hash' => 'SandiUji12345']);

    loginDengan($user->email, 'SandiUji12345')
        ->assertSessionHasErrors(['email' => 'Akun Anda nonaktif. Hubungi pengelola kos.']);

    $this->assertGuest();
});

it('F-01: akun nonaktif dengan password salah tidak membocorkan status akun', function () {
    $user = User::factory()->penghuni()->nonaktif()->create(['password_hash' => 'SandiUji12345']);

    loginDengan($user->email, 'salah-salah')
        ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

    $this->assertGuest();
});

it('F-01: setelah 5 kali gagal muncul pesan throttle berbahasa Indonesia', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'SandiUji12345']);

    foreach (range(1, 5) as $percobaan) {
        loginDengan($user->email, 'salah-salah')->assertSessionHasErrors('email');
    }

    $respons = loginDengan($user->email, 'SandiUji12345');

    $respons->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))
        ->toContain('Terlalu banyak percobaan masuk. Coba lagi dalam')
        ->toContain('detik');
    $this->assertGuest();
});

it('F-01: guest yang membuka halaman terproteksi diarahkan ke /login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('halaman_terproteksi');

it('F-01: halaman / mengarah ke beranda lalu ke login bagi guest', function () {
    $this->get('/')->assertRedirect('/beranda');
    $this->get('/beranda')->assertRedirect(route('login'));
});

it('F-01: pengguna yang sudah login dan membuka /login diarahkan ke halaman utamanya', function (string $peran, string $routeUtama) {
    $this->actingAs(User::factory()->{$peran}()->create())
        ->get(route('login'))
        ->assertRedirect(route($routeUtama));
})->with('peran_dan_halaman_utama');

it('F-01: /beranda mengarahkan ke halaman utama peran pengguna', function (string $peran, string $routeUtama) {
    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/beranda')
        ->assertRedirect(route($routeUtama));
})->with('peran_dan_halaman_utama');

it('F-01: login tidak mengikuti url.intended milik peran lain', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'SandiUji12345']);

    // Guest membuka halaman pengelola dulu sehingga URL tujuan tersimpan di session.
    $this->get('/akun')->assertRedirect(route('login'));
    expect(session('url.intended'))->not->toBeNull();

    loginDengan($user->email, 'SandiUji12345')
        ->assertRedirect(route('tagihan-saya.index'));
});

it('F-01: setiap peran punya halaman utama yang terdaftar sebagai route', function () {
    foreach (PeranPengguna::cases() as $peran) {
        expect(Route::has($peran->halamanUtama()))->toBeTrue();
    }
});

it('F-01: galat login hanya tampil sekali di kotak atas, tanpa galat di bawah field email', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'SandiUji12345']);

    $html = $this->from(route('login'))->followingRedirects()
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'salah-sekali'])
        ->assertOk()
        ->getContent();

    // Elemen galat Flux yang kosong tetap ada tetapi tersembunyi; tidak boleh ada yang tampil.
    preg_match_all('/class="([^"]*)" data-flux-error/', $html, $elemenGalat);
    $galatTampil = array_filter($elemenGalat[1], fn ($kelas) => ! str_contains($kelas, 'hidden'));

    expect(substr_count($html, 'Email atau password salah.'))->toBe(1)
        ->and($html)->toContain('role="alert"')
        ->and($galatTampil)->toBeEmpty()
        ->and($html)->not->toContain('aria-invalid="true"');
});
