<?php

/*
|--------------------------------------------------------------------------
| Akun Awal untuk Seeder
|--------------------------------------------------------------------------
|
| Nilai dibaca dari .env (SEED_*) lewat config agar tetap terbaca ketika
| konfigurasi di-cache. Tidak ada nilai bawaan untuk email dan password.
|
*/

return [
    'pemilik' => [
        'nama' => env('SEED_PEMILIK_NAMA', 'Pemilik Kost'),
        'email' => env('SEED_PEMILIK_EMAIL'),
        'password' => env('SEED_PEMILIK_PASSWORD'),
    ],

    'pengelola' => [
        'nama' => env('SEED_PENGELOLA_NAMA', 'Pengelola Kost'),
        'email' => env('SEED_PENGELOLA_EMAIL'),
        'password' => env('SEED_PENGELOLA_PASSWORD'),
    ],

    // Password akun contoh penghuni dan vendor; hanya dipakai DataContohSeeder di environment local.
    'demo_password' => env('SEED_DEMO_PASSWORD'),
];
