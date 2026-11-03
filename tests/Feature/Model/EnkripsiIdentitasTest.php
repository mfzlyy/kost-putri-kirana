<?php

use App\Models\Penghuni;
use Illuminate\Support\Facades\DB;

const NOMOR_IDENTITAS_UJI = '3275010101010001';

it('menyimpan no_identitas_enc terenkripsi di basis data (NFR-02)', function () {
    $penghuni = Penghuni::factory()->create(['no_identitas_enc' => NOMOR_IDENTITAS_UJI]);

    $mentah = DB::table('penghuni')->where('id', $penghuni->id)->value('no_identitas_enc');

    expect($mentah)->not->toBe(NOMOR_IDENTITAS_UJI)
        ->and($mentah)->not->toContain(NOMOR_IDENTITAS_UJI);
});

it('mengembalikan nomor identitas utuh lewat model', function () {
    $penghuni = Penghuni::factory()->create(['no_identitas_enc' => NOMOR_IDENTITAS_UJI]);

    expect(Penghuni::query()->find($penghuni->id)->no_identitas_enc)->toBe(NOMOR_IDENTITAS_UJI);
});

it('tidak membocorkan no_identitas_enc lewat serialisasi', function () {
    $penghuni = Penghuni::factory()->create(['no_identitas_enc' => NOMOR_IDENTITAS_UJI]);
    $segar = Penghuni::query()->find($penghuni->id);

    expect($segar->toArray())->not->toHaveKey('no_identitas_enc')
        ->and($segar->toJson())->not->toContain(NOMOR_IDENTITAS_UJI)
        ->and($segar->toJson())->not->toContain('no_identitas_enc');
});

it('menampilkan nomor identitas tersamar: dua digit awal, bintang, empat digit akhir', function () {
    $penghuni = Penghuni::factory()->create(['no_identitas_enc' => NOMOR_IDENTITAS_UJI]);

    expect($penghuni->no_identitas_tersamar)->toBe('32**********0001')
        ->and(mb_strlen($penghuni->no_identitas_tersamar))->toBe(16);
});

it('menyamarkan seluruh nomor identitas yang terlalu pendek', function () {
    $penghuni = Penghuni::factory()->make(['no_identitas_enc' => '12345']);

    expect($penghuni->no_identitas_tersamar)->toBe('*****');
});
