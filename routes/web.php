<?php

use App\Http\Controllers\BuktiBayarController;
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

    // Berkas bukti bayar dari disk privat. Tanpa batas peran di route: akses ditentukan PembayaranPolicy::view.
    Route::get('bukti-bayar/{pembayaran}', [BuktiBayarController::class, 'tampil'])->name('bukti-bayar.tampil');

    // Pemilik
    Route::middleware('peran:'.$peranDashboard)->group(function () use ($disiapkan) {
        $disiapkan('dashboard', 'dashboard', 'Dasbor', 'F-21');
    });

    // Pengelola
    Route::middleware('peran:pengelola')->group(function () use ($disiapkan) {
        Route::livewire('akun', 'pages::akun.index')->name('akun.index');
        Route::livewire('kamar/tambah', 'pages::kamar.form')->name('kamar.tambah');
        Route::livewire('kamar/{kamar}/ubah', 'pages::kamar.form')->name('kamar.ubah');
        Route::livewire('penghuni/tambah', 'pages::penghuni.form')->name('penghuni.tambah');
        Route::livewire('penghuni/{penghuni}/ubah', 'pages::penghuni.form')->name('penghuni.ubah');
        Route::livewire('kontrak/buat', 'pages::kontrak.form')->name('kontrak.buat');
        Route::livewire('kontrak/{kontrak}/perpanjang', 'pages::kontrak.form')->name('kontrak.perpanjang');
        Route::livewire('verifikasi-pembayaran', 'pages::verifikasi-pembayaran.index')->name('verifikasi-pembayaran.index');
        $disiapkan('sinkronisasi-erp', 'sinkronisasi-erp.index', 'Sinkronisasi', 'F-25');
    });

    // Pengelola dan pemilik
    Route::middleware('peran:pengelola,pemilik')->group(function () {
        Route::livewire('pengaturan', 'pages::pengaturan.index')->name('pengaturan.index');
        Route::livewire('kamar', 'pages::kamar.index')->name('kamar.index');
        Route::livewire('penghuni', 'pages::penghuni.index')->name('penghuni.index');
        Route::livewire('kontrak', 'pages::kontrak.index')->name('kontrak.index');
        Route::livewire('tagihan', 'pages::tagihan.index')->name('tagihan.index');
        Route::livewire('tiket-keluhan', 'pages::tiket-keluhan.index')->name('tiket-keluhan.index');
    });

    // Pengelola, pemilik, dan penghuni (milik sendiri)
    Route::middleware('peran:pengelola,pemilik,penghuni')->group(function () {
        Route::livewire('riwayat-transaksi', 'pages::riwayat-transaksi.index')->name('riwayat-transaksi.index');
    });

    // Penghuni
    Route::middleware('peran:penghuni')->group(function () {
        Route::livewire('tagihan-saya', 'pages::tagihan-saya.index')->name('tagihan-saya.index');
        Route::livewire('tagihan-saya/{tagihan}/unggah', 'pages::tagihan-saya.unggah')->name('tagihan-saya.unggah');
        Route::livewire('profil-saya', 'pages::profil-saya.index')->name('profil-saya.index');
        Route::livewire('keluhan-saya', 'pages::keluhan-saya.index')->name('keluhan-saya.index');
        Route::livewire('keluhan-saya/ajukan', 'pages::keluhan-saya.ajukan')->name('keluhan-saya.ajukan');
    });

    // Vendor
    Route::middleware('peran:vendor')->group(function () use ($disiapkan) {
        $disiapkan('permintaan-kerja', 'permintaan-kerja.index', 'Pekerjaan', 'F-19, F-20');
    });
});

require __DIR__.'/settings.php';
