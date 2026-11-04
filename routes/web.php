<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Aturan akses Dashboard (F-21)
|--------------------------------------------------------------------------
|
| [ASUMSI] ISU-05 (akses pengelola ke dashboard dan laporan ERP) belum diputuskan,
| jadi Dashboard hanya untuk pemilik. Ubah daftar ini bila pemilik memutuskan lain.
|
*/
$peranDashboard = 'pemilik';

// Halaman fitur yang belum dibuat: hanya judul dan teks "sedang disiapkan", tanpa data.
$disiapkan = function (string $uri, string $nama, string $judul, string $kode): void {
    Route::view($uri, 'pages::halaman-disiapkan', ['judul' => $judul, 'kode' => $kode])->name($nama);
};

Route::redirect('/', '/beranda')->name('home');

Route::middleware(['auth', 'akun.aktif'])->group(function () use ($peranDashboard, $disiapkan) {
    Route::get('beranda', fn () => redirect()->route(auth()->user()->peran->halamanUtama()))
        ->name('beranda');

    // Pemilik
    Route::middleware('peran:'.$peranDashboard)->group(function () use ($disiapkan) {
        $disiapkan('dashboard', 'dashboard', 'Dashboard', 'F-21');
    });

    // Pengelola
    Route::middleware('peran:pengelola')->group(function () use ($disiapkan) {
        Route::livewire('akun', 'pages::akun.index')->name('akun.index');
        Route::livewire('kamar/tambah', 'pages::kamar.form')->name('kamar.tambah');
        Route::livewire('kamar/{kamar}/ubah', 'pages::kamar.form')->name('kamar.ubah');
        $disiapkan('verifikasi-pembayaran', 'verifikasi-pembayaran.index', 'Verifikasi pembayaran', 'F-13');
        $disiapkan('sinkronisasi-erp', 'sinkronisasi-erp.index', 'Sinkronisasi ERP', 'F-25');
    });

    // Pengelola dan pemilik
    Route::middleware('peran:pengelola,pemilik')->group(function () use ($disiapkan) {
        Route::livewire('pengaturan', 'pages::pengaturan.index')->name('pengaturan.index');
        Route::livewire('kamar', 'pages::kamar.index')->name('kamar.index');
        $disiapkan('penghuni', 'penghuni.index', 'Penghuni', 'F-06');
        $disiapkan('kontrak', 'kontrak.index', 'Kontrak', 'F-07, F-08');
        $disiapkan('tagihan', 'tagihan.index', 'Tagihan', 'F-10, F-15');
        $disiapkan('tiket-keluhan', 'tiket-keluhan.index', 'Tiket keluhan', 'F-17, F-18');
    });

    // Pengelola, pemilik, dan penghuni (milik sendiri)
    Route::middleware('peran:pengelola,pemilik,penghuni')->group(function () use ($disiapkan) {
        $disiapkan('riwayat-transaksi', 'riwayat-transaksi.index', 'Riwayat transaksi', 'F-14');
    });

    // Penghuni
    Route::middleware('peran:penghuni')->group(function () use ($disiapkan) {
        $disiapkan('tagihan-saya', 'tagihan-saya.index', 'Tagihan saya', 'F-11, F-12');
        $disiapkan('profil-saya', 'profil-saya.index', 'Profil saya', 'F-06');
        $disiapkan('keluhan-saya', 'keluhan-saya.index', 'Keluhan saya', 'F-16, F-18');
    });

    // Vendor
    Route::middleware('peran:vendor')->group(function () use ($disiapkan) {
        $disiapkan('permintaan-kerja', 'permintaan-kerja.index', 'Permintaan kerja', 'F-19, F-20');
    });
});

require __DIR__.'/settings.php';
