<?php

use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

const NIK_UJI_DAFTAR = '3216012345678901';

function daftarPenghuniSebagai(User $user, array $query = [])
{
    return Livewire::withQueryParams($query)->actingAs($user)->test('pages::penghuni.index');
}

function namaDalamDaftar(User $user, array $query = []): array
{
    return daftarPenghuniSebagai($user, $query)->instance()->daftarPenghuni
        ->getCollection()->map(fn ($penghuni) => $penghuni->user->nama)->all();
}

function penghuniDiKamar(string $nomor, string $nama, string $telepon = '081234567801', string $nik = NIK_UJI_DAFTAR): Penghuni
{
    $kamar = Kamar::factory()->create(['nomor_kamar' => $nomor]);
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => $nama, 'email' => "p{$nomor}@contoh.test"]),
        'no_telepon' => $telepon,
        'no_identitas_enc' => $nik,
    ]);
    Kontrak::factory()->aktif()->create(['kamar_id' => $kamar->id, 'penghuni_id' => $penghuni->id]);

    return $penghuni;
}

it('F-06: pengelola melihat nama, kamar, telepon berformat, NIK tersamar, email, dan status; NIK utuh tidak ada di HTML', function () {
    penghuniDiKamar('01', 'Anisa Putri');

    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/penghuni')
        ->assertOk()
        ->assertSeeInOrder(['Anisa Putri', '01', '0812 3456 7801', '32**********8901', 'p01@contoh.test', 'Aktif', 'Ubah'])
        ->assertDontSee('Nomor identitas disamarkan di daftar.')
        ->assertDontSee(NIK_UJI_DAFTAR)
        ->assertDontSee('3216 0123 4567 8901')
        ->assertDontSee('Cari penghuni');
});

it('F-06: filter aktif (bawaan), keluar, dan semua; nilai lain dianggap aktif', function () {
    penghuniDiKamar('01', 'Anisa Putri');
    Penghuni::factory()->nonaktif()->create(['user_id' => User::factory()->penghuni()->create(['nama' => 'Dewi Lama'])]);
    $pengelola = User::factory()->pengelola()->create();

    expect(namaDalamDaftar($pengelola))->toBe(['Anisa Putri'])
        ->and(namaDalamDaftar($pengelola, ['status' => 'aktif']))->toBe(['Anisa Putri'])
        ->and(namaDalamDaftar($pengelola, ['status' => 'keluar']))->toBe(['Dewi Lama'])
        ->and(namaDalamDaftar($pengelola, ['status' => 'semua']))->toBe(['Anisa Putri', 'Dewi Lama'])
        ->and(namaDalamDaftar($pengelola, ['status' => 'sembarang']))->toBe(['Anisa Putri']);

    daftarPenghuniSebagai($pengelola)
        ->call('$set', 'filterStatus', 'keluar')
        ->assertSet('filterStatus', 'keluar')
        ->assertSee('Dewi Lama')
        ->assertSee('Nonaktif');
});

it('F-06: daftar terurut menurut nama lengkap', function () {
    penghuniDiKamar('01', 'Zahra');
    penghuniDiKamar('02', 'Citra');
    penghuniDiKamar('03', 'Bunga');

    expect(namaDalamDaftar(User::factory()->pengelola()->create()))->toBe(['Bunga', 'Citra', 'Zahra']);
});

it('F-06: keadaan kosong membedakan belum ada data dan filter tanpa hasil', function () {
    $pengelola = User::factory()->pengelola()->create();

    daftarPenghuniSebagai($pengelola)
        ->assertSee('Belum ada data penghuni.')
        ->assertSee('Tambah');

    penghuniDiKamar('01', 'Anisa Putri');

    daftarPenghuniSebagai($pengelola, ['status' => 'keluar'])
        ->assertSee('Tidak ada penghuni yang sesuai filter.')
        ->assertDontSee('Belum ada data penghuni.');
});

it('F-06: jumlah query daftar tidak bertambah saat jumlah penghuni bertambah', function () {
    $pengelola = User::factory()->pengelola()->create();

    $hitungQuery = function () use ($pengelola): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($pengelola)->get('/penghuni?status=semua')->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    penghuniDiKamar('01', 'Anisa Putri');
    $sedikit = $hitungQuery();

    foreach (['02', '03', '04', '05', '06'] as $nomor) {
        penghuniDiKamar($nomor, "Penghuni {$nomor}");
    }
    Penghuni::factory()->count(3)->nonaktif()->create();

    expect($hitungQuery())->toBe($sedikit);
});

it('F-06: daftar dipaginasi 15 baris per halaman', function () {
    Penghuni::factory()->count(17)->create();

    $daftar = daftarPenghuniSebagai(User::factory()->pengelola()->create())->instance()->daftarPenghuni;

    expect($daftar->count())->toBe(15)->and($daftar->total())->toBe(17);
});

it('F-06: pemilik melihat daftar tanpa tombol tambah dan tanpa tautan ubah', function () {
    penghuniDiKamar('01', 'Anisa Putri');

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/penghuni')
        ->assertOk()
        ->assertSee('Anisa Putri')
        ->assertSee('32**********8901')
        ->assertDontSee('data-test="tambah-penghuni"', false)
        ->assertDontSee('Ubah')
        ->assertDontSee(NIK_UJI_DAFTAR);
});

it('F-06: penghuni dan vendor mendapat 403 di daftar penghuni', function (string $peran) {
    penghuniDiKamar('01', 'Anisa Putri');

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/penghuni')
        ->assertForbidden()
        ->assertDontSee('Anisa Putri')
        ->assertDontSee('32**********8901');
})->with(['penghuni', 'vendor']);
