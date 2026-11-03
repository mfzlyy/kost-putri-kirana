<?php

use Illuminate\Support\Facades\Route;

// Ganti Password (F-01) tersedia untuk semua peran.
Route::middleware(['auth', 'akun.aktif'])->group(function () {
    Route::redirect('settings', 'settings/security');

    Route::livewire('settings/security', 'pages::settings.security')->name('security.edit');
});
