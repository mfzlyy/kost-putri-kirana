<?php

use App\Models\Kamar;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\User;

beforeEach(fn () => PengaturanOperasional::factory()->create());

/*
| Matriks akses halaman per peran (PRD Bagian 6, F-03, TB-04).
*/

dataset('akses_halaman', function () {
    $semuaPeran = ['pemilik', 'pengelola', 'penghuni', 'vendor'];

    $halaman = [
        '/dashboard' => ['pemilik'],
        '/akun' => ['pengelola'],
        '/pengaturan' => ['pengelola', 'pemilik'],
        '/kamar' => ['pengelola', 'pemilik'],
        '/kamar/tambah' => ['pengelola'],
        '/kamar/tambah' => ['pengelola'],
        '/penghuni' => ['pengelola', 'pemilik'],
        '/penghuni/tambah' => ['pengelola'],
        '/kontrak' => ['pengelola', 'pemilik'],
        '/tagihan' => ['pengelola', 'pemilik'],
        '/verifikasi-pembayaran' => ['pengelola'],
        '/riwayat-transaksi' => ['pengelola', 'pemilik', 'penghuni'],
        '/tiket-keluhan' => ['pengelola', 'pemilik'],
        '/sinkronisasi-erp' => ['pengelola'],
        '/tagihan-saya' => ['penghuni'],
        '/profil-saya' => ['penghuni'],
        '/keluhan-saya' => ['penghuni'],
        '/permintaan-kerja' => ['vendor'],
        '/settings/security' => $semuaPeran,
    ];

    foreach ($halaman as $url => $peranBerhak) {
        foreach ($semuaPeran as $peran) {
            $boleh = in_array($peran, $peranBerhak, true);

            yield ($boleh ? 'boleh' : 'ditolak').": {$peran} -> {$url}" => [$url, $peran, $boleh];
        }
    }
});

it('F-03: peran yang berhak mendapat 200, peran lain mendapat 403 tanpa data halaman', function (string $url, string $peran, bool $boleh) {
    $user = User::factory()->{$peran}()->create();

    $respons = $this->actingAs($user)->get($url);

    if ($boleh) {
        $respons->assertOk();

        return;
    }

    $respons->assertForbidden()
        ->assertSee('Akses ditolak')
        ->assertSee('Anda tidak memiliki akses ke halaman ini')
        ->assertSee('Kembali ke beranda')
        ->assertDontSee('sedang disiapkan')
        ->assertDontSee('Tambah Akun');
})->with('akses_halaman');

it('F-03: halaman 403 tidak menampilkan pesan exception atau stack trace', function () {
    $this->actingAs(User::factory()->vendor()->create())
        ->get('/akun')
        ->assertForbidden()
        ->assertDontSee('PastikanPeran')
        ->assertDontSee('Stack trace')
        ->assertDontSee('vendor/laravel');
});

it('F-03: halaman yang belum dibuat menampilkan teks sedang disiapkan tanpa data', function () {
    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Halaman ini sedang disiapkan.');
});

it('F-03: dashboard hanya untuk pemilik (ASUMSI ISU-05)', function () {
    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/dashboard')
        ->assertForbidden();
});

it('F-01: pengguna yang dinonaktifkan di tengah sesi langsung dikeluarkan pada request berikutnya', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'SandiUji12345']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'SandiUji12345']);
    $this->get('/tagihan-saya')->assertOk();

    User::query()->find($user->id)->nonaktifkan();

    // Pada produksi setiap request memuat ulang user dari session; di tes guard menyimpan cache.
    app('auth')->forgetGuards();

    $this->get('/tagihan-saya')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Akun Anda nonaktif. Hubungi pengelola kos.']);

    $this->assertGuest();
});

it('F-01: akun yang dinonaktifkan tidak lagi bisa membuka halaman Ganti Password', function () {
    $user = User::factory()->vendor()->nonaktif()->create();

    $this->actingAs($user)->get('/settings/security')->assertRedirect(route('login'));

    $this->assertGuest();
});

it('F-05: halaman ubah kamar hanya untuk pengelola', function (string $peran, bool $boleh) {
    $kamar = Kamar::factory()->create();
    $user = User::factory()->{$peran}()->create();

    $respons = $this->actingAs($user)->get(route('kamar.ubah', $kamar));

    $boleh ? $respons->assertOk() : $respons->assertForbidden()->assertSee('Akses ditolak');
})->with([
    'pengelola' => ['pengelola', true],
    'pemilik' => ['pemilik', false],
    'penghuni' => ['penghuni', false],
    'vendor' => ['vendor', false],
]);

it('F-06: halaman ubah penghuni hanya untuk pengelola', function (string $peran, bool $boleh) {
    $penghuni = Penghuni::factory()->create();
    $user = User::factory()->{$peran}()->create();

    $respons = $this->actingAs($user)->get(route('penghuni.ubah', $penghuni));

    $boleh ? $respons->assertOk() : $respons->assertForbidden()->assertSee('Akses ditolak');
})->with([
    'pengelola' => ['pengelola', true],
    'pemilik' => ['pemilik', false],
    'penghuni' => ['penghuni', false],
    'vendor' => ['vendor', false],
]);
