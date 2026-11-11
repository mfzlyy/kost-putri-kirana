<?php

use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\User;
use Livewire\Livewire;

const NIK_UJI_PROFIL = '3216012345678901';

function penghuniBerKontrak(string $nama, string $nomorKamar, string $nik = NIK_UJI_PROFIL): Penghuni
{
    $kamar = Kamar::factory()->create(['nomor_kamar' => $nomorKamar, 'tarif_bulanan' => 1400000]);
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => $nama, 'email' => "kamar{$nomorKamar}@contoh.test"]),
        'no_identitas_enc' => $nik,
        'no_telepon' => '087855660021',
    ]);
    Kontrak::factory()->aktif()->create([
        'kamar_id' => $kamar->id,
        'penghuni_id' => $penghuni->id,
        'tanggal_mulai' => '2026-02-01',
        'tanggal_berakhir' => '2027-01-31',
        'tarif_bulanan' => 1400000,
    ]);

    return $penghuni;
}

it('F-06: penghuni melihat data diri, kamar, masa sewa, dan tarif', function () {
    $penghuni = penghuniBerKontrak('Maya Sari', '05');

    $this->actingAs($penghuni->user)
        ->get('/profil-saya')
        ->assertOk()
        ->assertSee('Maya Sari')
        ->assertSee('Penghuni Kamar 05')
        ->assertSee('kamar05@contoh.test')
        ->assertSee('0878 5566 0021')
        ->assertSee('32**********8901')
        ->assertSee('1 Feb 2026 sampai 31 Jan 2027')
        ->assertSee('Rp1.400.000')
        ->assertSee('Ada data yang salah? Hubungi pengelola kos untuk mengubahnya.')
        ->assertDontSee(NIK_UJI_PROFIL)
        ->assertDontSee('Simpan');
});

it('F-06: penghuni tanpa kontrak aktif melihat pesan belum ada kontrak', function () {
    $penghuni = Penghuni::factory()->create(['user_id' => User::factory()->penghuni()->create(['nama' => 'Dewi Lama'])]);

    $this->actingAs($penghuni->user)
        ->get('/profil-saya')
        ->assertOk()
        ->assertSee('Dewi Lama')
        ->assertSee('Belum ada kontrak aktif.')
        ->assertDontSee('Penghuni Kamar');
});

it('ATB-10: penghuni A tidak pernah melihat data penghuni B', function () {
    $a = penghuniBerKontrak('Penghuni A', '01', '3216000000000001');
    $b = penghuniBerKontrak('Penghuni B', '02', '3216000000000002');

    $komponen = Livewire::actingAs($a->user)->test('pages::profil-saya.index')
        ->assertSee('Penghuni A')
        ->assertSee('kamar01@contoh.test')
        ->assertDontSee('Penghuni B')
        ->assertDontSee('kamar02@contoh.test')
        ->call('tampilkanIdentitas')
        ->assertSee('3216 0000 0000 0001')
        ->assertDontSee('3216 0000 0000 0002');

    expect($komponen->instance()->penghuni->id)->toBe($a->id)->and($b->id)->not->toBe($a->id);
});

it('NFR-02: tampilkan NIK hanya untuk dirinya, snapshot tanpa NIK utuh, dan bisa disembunyikan lagi', function () {
    $penghuni = penghuniBerKontrak('Maya Sari', '05');

    $komponen = Livewire::actingAs($penghuni->user)->test('pages::profil-saya.index');

    expect(json_encode($komponen->snapshot))->not->toContain(NIK_UJI_PROFIL);

    $komponen->call('tampilkanIdentitas')
        ->assertSee('3216 0123 4567 8901')
        ->assertSee('Sembunyikan');

    expect(json_encode($komponen->snapshot))->not->toContain(NIK_UJI_PROFIL)
        ->and(json_encode($komponen->snapshot))->not->toContain('3216 0123');

    $komponen->call('sembunyikanIdentitas')
        ->assertDontSee('3216 0123 4567 8901')
        ->assertSee('32**********8901');
});

it('F-06: penghuni tanpa baris data penghuni melihat pesan belum dilengkapi tanpa galat', function () {
    $this->actingAs(User::factory()->penghuni()->create())
        ->get('/profil-saya')
        ->assertOk()
        ->assertSee('Profil saya')
        ->assertSee('Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.');
});

it('F-06: tampilkan NIK tanpa data penghuni mengembalikan 404, bukan galat server', function () {
    Livewire::actingAs(User::factory()->penghuni()->create())
        ->test('pages::profil-saya.index')
        ->call('tampilkanIdentitas')
        ->assertNotFound();
});

it('F-06: pemilik, pengelola, dan vendor mendapat 403 di Profil saya', function (string $peran) {
    penghuniBerKontrak('Maya Sari', '05');

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/profil-saya')
        ->assertForbidden()
        ->assertDontSee('Maya Sari');
})->with(['pemilik', 'pengelola', 'vendor']);
