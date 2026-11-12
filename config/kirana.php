<?php

/*
|--------------------------------------------------------------------------
| Aturan Bisnis Kost Putri Kirana
|--------------------------------------------------------------------------
|
| Nilai yang menunggu keputusan pemilik dibaca dari .env supaya bisa diganti
| tanpa mengubah kode.
|
*/

return [
    'tagihan' => [
        // Kontrak yang tidak penuh satu bulan (ISU-P01): "penuh" menagih tarif penuh,
        // "prorata" menagih sesuai jumlah hari kontrak berjalan dalam bulan itu.
        'parsial' => env('TAGIHAN_PARSIAL', 'penuh'),
    ],
];
