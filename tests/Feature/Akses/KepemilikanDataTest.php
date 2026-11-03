<?php

use App\Enums\StatusAkun;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Gate;

/**
 * Satu penghuni lengkap dengan kontrak, tagihan, pembayaran, dan tiket.
 *
 * @return array{user: User, penghuni: Penghuni, kontrak: Kontrak, tagihan: Tagihan, pembayaran: Pembayaran, tiket: TiketKeluhan}
 */
function dataPenghuni(?Vendor $vendor = null): array
{
    $penghuni = Penghuni::factory()->create();
    $kontrak = Kontrak::factory()->create(['penghuni_id' => $penghuni]);
    $tagihan = Tagihan::factory()->create(['kontrak_id' => $kontrak]);
    $pembayaran = Pembayaran::factory()->create(['tagihan_id' => $tagihan]);
    $tiket = TiketKeluhan::factory()->create(['penghuni_id' => $penghuni, 'vendor_id' => $vendor?->id]);

    return [
        'user' => $penghuni->user, 'penghuni' => $penghuni, 'kontrak' => $kontrak,
        'tagihan' => $tagihan, 'pembayaran' => $pembayaran, 'tiket' => $tiket,
    ];
}

function bolehMelihat(User $user, array $data, bool $termasukTiket = true): array
{
    $hasil = [];

    foreach (['penghuni', 'kontrak', 'tagihan', 'pembayaran'] + ($termasukTiket ? [4 => 'tiket'] : []) as $kunci) {
        $hasil[$kunci] = Gate::forUser($user)->allows('view', $data[$kunci]);
    }

    return $hasil;
}

it('ATB-10: penghuni A boleh melihat semua data miliknya', function () {
    $a = dataPenghuni();

    expect(bolehMelihat($a['user'], $a))->toBe([
        'penghuni' => true, 'kontrak' => true, 'tagihan' => true, 'pembayaran' => true, 'tiket' => true,
    ]);
});

it('ATB-10: penghuni A ditolak untuk data milik penghuni B', function () {
    $a = dataPenghuni();
    $b = dataPenghuni();

    expect(bolehMelihat($a['user'], $b))->toBe([
        'penghuni' => false, 'kontrak' => false, 'tagihan' => false, 'pembayaran' => false, 'tiket' => false,
    ]);
});

it('ATB-08: vendor ditolak untuk penghuni, kontrak, tagihan, dan pembayaran', function () {
    $vendor = Vendor::factory()->create();
    $data = dataPenghuni($vendor);

    expect(bolehMelihat($vendor->user, $data, termasukTiket: false))->toBe([
        'penghuni' => false, 'kontrak' => false, 'tagihan' => false, 'pembayaran' => false,
    ]);
});

it('ATB-08: vendor hanya boleh melihat tiket yang ditugaskan kepadanya', function () {
    $vendor = Vendor::factory()->create();
    $lain = Vendor::factory()->create();
    $milikVendor = dataPenghuni($vendor);
    $milikLain = dataPenghuni($lain);
    $belumDitugaskan = dataPenghuni();

    expect(Gate::forUser($vendor->user)->allows('view', $milikVendor['tiket']))->toBeTrue()
        ->and(Gate::forUser($vendor->user)->allows('view', $milikLain['tiket']))->toBeFalse()
        ->and(Gate::forUser($vendor->user)->allows('view', $belumDitugaskan['tiket']))->toBeFalse();
});

it('ATB-10: pemilik dan pengelola boleh melihat semuanya', function (string $peran) {
    $user = User::factory()->{$peran}()->create();
    $a = dataPenghuni(Vendor::factory()->create());
    $b = dataPenghuni();

    foreach ([$a, $b] as $data) {
        expect(bolehMelihat($user, $data))->toBe([
            'penghuni' => true, 'kontrak' => true, 'tagihan' => true, 'pembayaran' => true, 'tiket' => true,
        ]);
    }

    foreach ([Penghuni::class, Kontrak::class, Tagihan::class, Pembayaran::class, TiketKeluhan::class] as $model) {
        expect(Gate::forUser($user)->allows('viewAny', $model))->toBeTrue();
    }
})->with(['pemilik', 'pengelola']);

it('F-03: user nonaktif ditolak untuk semua Policy', function (string $peran) {
    $data = dataPenghuni();
    $user = match ($peran) {
        'pemilik', 'pengelola' => User::factory()->{$peran}()->nonaktif()->create(),
        'penghuni' => $data['user'],
        'vendor' => Vendor::factory()->create(['user_id' => User::factory()->vendor()->nonaktif()])->user,
    };

    if ($peran === 'penghuni') {
        $user->forceFill(['status_akun' => StatusAkun::Nonaktif])->save();
    }

    // Tiket ditugaskan ke vendor ini: bila akunnya aktif, vendor boleh melihatnya.
    $data['tiket']->forceFill(['vendor_id' => $user->vendor?->id])->save();

    foreach (['penghuni', 'kontrak', 'tagihan', 'pembayaran', 'tiket'] as $kunci) {
        expect(Gate::forUser($user)->allows('view', $data[$kunci]))->toBeFalse("{$peran} nonaktif: {$kunci}");
    }

    foreach ([Penghuni::class, Kontrak::class, Tagihan::class, Pembayaran::class, TiketKeluhan::class, User::class] as $model) {
        expect(Gate::forUser($user)->allows('viewAny', $model))->toBeFalse("{$peran} nonaktif: viewAny {$model}");
    }
})->with(['pemilik', 'pengelola', 'penghuni', 'vendor']);

it('ATB-10: viewAny pada daftar mengikuti peran', function () {
    $penghuni = User::factory()->penghuni()->create();
    $vendor = User::factory()->vendor()->create();

    expect(Gate::forUser($penghuni)->allows('viewAny', Penghuni::class))->toBeFalse()
        ->and(Gate::forUser($penghuni)->allows('viewAny', Kontrak::class))->toBeFalse()
        ->and(Gate::forUser($penghuni)->allows('viewAny', Tagihan::class))->toBeTrue()
        ->and(Gate::forUser($penghuni)->allows('viewAny', TiketKeluhan::class))->toBeTrue()
        ->and(Gate::forUser($vendor)->allows('viewAny', Tagihan::class))->toBeFalse()
        ->and(Gate::forUser($vendor)->allows('viewAny', Pembayaran::class))->toBeFalse()
        ->and(Gate::forUser($vendor)->allows('viewAny', TiketKeluhan::class))->toBeTrue();
});

it('F-03: penolakan Policy memakai pesan akses ditolak', function () {
    $a = dataPenghuni();
    $b = dataPenghuni();

    $respons = Gate::forUser($a['user'])->inspect('view', $b['tagihan']);

    expect($respons->allowed())->toBeFalse()
        ->and($respons->message())->toBe('Anda tidak memiliki akses ke halaman ini.');
});

it('F-02: UserPolicy hanya mengizinkan pengelola mengelola akun penghuni dan vendor', function () {
    $pengelola = User::factory()->pengelola()->create();
    $penghuni = User::factory()->penghuni()->create();
    $vendor = User::factory()->vendor()->create();
    $pemilik = User::factory()->pemilik()->create();

    expect(Gate::forUser($pengelola)->allows('nonaktifkan', $penghuni))->toBeTrue()
        ->and(Gate::forUser($pengelola)->allows('aktifkan', $vendor))->toBeTrue()
        ->and(Gate::forUser($pengelola)->allows('nonaktifkan', $pemilik))->toBeFalse()
        ->and(Gate::forUser($pengelola)->allows('nonaktifkan', $pengelola))->toBeFalse()
        ->and(Gate::forUser($pengelola)->allows('nonaktifkan', User::factory()->pengelola()->create()))->toBeFalse()
        ->and(Gate::forUser($pemilik)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($pemilik)->allows('create', User::class))->toBeFalse()
        ->and(Gate::forUser($penghuni)->allows('nonaktifkan', $vendor))->toBeFalse();
});
