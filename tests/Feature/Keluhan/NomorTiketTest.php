<?php

use App\Enums\LokasiKeluhan;
use App\Exceptions\KeluhanGagalDikirim;
use App\Models\Fasilitas;
use App\Models\Penghuni;
use App\Models\TiketKeluhan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('nomor pertama adalah KLH-0001 pada basis data kosong', function () {
    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-0001');
});

it('nomor berikutnya mengikuti nomor terbesar yang ada', function () {
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0003']);
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0007']);

    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-0008');
});

it('nomor berpola lain diabaikan', function () {
    TiketKeluhan::factory()->create(['nomor_tiket' => 'TKT-9999']);
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-12A']);
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-']);
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0002']);

    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-0003');
});

it('nomor di atas 9999 tetap utuh dan muat di kolom', function () {
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-9999']);

    $nomor = TiketKeluhan::nomorBerikutnya();

    expect($nomor)->toBe('KLH-10000');

    $tiket = TiketKeluhan::factory()->create(['nomor_tiket' => $nomor]);

    expect($tiket->refresh()->nomor_tiket)->toBe('KLH-10000')
        ->and(TiketKeluhan::nomorBerikutnya())->toBe('KLH-10001');
});

it('ajukanKeluhan berurutan menghasilkan nomor unik berurutan tanpa celah', function () {
    $user = User::factory()->penghuni()->create();
    $penghuni = Penghuni::factory()->create(['user_id' => $user->id]);
    $area = Fasilitas::factory()->areaBersama()->create();

    $nomor = collect(range(1, 6))->map(fn (int $n) => TiketKeluhan::ajukanKeluhan(
        $penghuni, LokasiKeluhan::AreaBersama, $area, "Keluhan ke-{$n}", $user,
    )->nomor_tiket);

    expect($nomor->all())->toBe(['KLH-0001', 'KLH-0002', 'KLH-0003', 'KLH-0004', 'KLH-0005', 'KLH-0006'])
        ->and($nomor->unique())->toHaveCount(6);
});

it('ajukanKeluhan melanjutkan dari nomor tiket yang sudah ada', function () {
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0012']);
    $user = User::factory()->penghuni()->create();
    $penghuni = Penghuni::factory()->create(['user_id' => $user->id]);
    $area = Fasilitas::factory()->areaBersama()->create();

    $tiket = TiketKeluhan::ajukanKeluhan($penghuni, LokasiKeluhan::AreaBersama, $area, 'Rusak.', $user);

    expect($tiket->nomor_tiket)->toBe('KLH-0013');
});

it('nomor yang bentrok diulang sekali, lalu gagal dengan pesan Indonesia', function () {
    $user = User::factory()->penghuni()->create();
    $penghuni = Penghuni::factory()->create(['user_id' => $user->id]);
    $area = Fasilitas::factory()->areaBersama()->create();

    // Setelah transaksi pertama menghitung nomor, simulasikan tiket lain yang menyerobot nomor itu.
    $serobot = 0;
    TiketKeluhan::creating(function (TiketKeluhan $tiket) use (&$serobot) {
        if ($serobot++ < 2) {
            DB::table('tiket_keluhan')->insert([
                'nomor_tiket' => $tiket->nomor_tiket,
                'penghuni_id' => $tiket->penghuni_id,
                'fasilitas_id' => $tiket->fasilitas_id,
                'lokasi' => 'area_bersama',
                'deskripsi' => 'Serobot '.$serobot,
            ]);
        }
    });

    try {
        expect(fn () => TiketKeluhan::ajukanKeluhan($penghuni, LokasiKeluhan::AreaBersama, $area, 'Rusak.', $user))
            ->toThrow(KeluhanGagalDikirim::class, 'Keluhan gagal dikirim. Coba lagi.');
    } finally {
        TiketKeluhan::flushEventListeners();
    }

    expect($serobot)->toBe(2)
        ->and(TiketKeluhan::query()->count())->toBe(0);
});
