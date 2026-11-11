<?php

use App\Models\Penghuni;
use App\Models\User;
use Livewire\Livewire;

const NIK_UJI_IDENTITAS = '3216012345678901';
const NIK_UJI_IDENTITAS_KELOMPOK = '3216 0123 4567 8901';

function penghuniBerNik(): Penghuni
{
    return Penghuni::factory()->create(['no_identitas_enc' => NIK_UJI_IDENTITAS]);
}

function pastikanSnapshotTanpaNik($komponen): void
{
    $snapshot = json_encode($komponen->snapshot);

    expect($snapshot)->not->toContain(NIK_UJI_IDENTITAS)
        ->and($snapshot)->not->toContain('3216 0123')
        ->and($snapshot)->not->toContain('4567 8901');
}

it('NFR-02: snapshot Livewire form ubah tidak memuat NIK utuh sebelum dan sesudah tampilkanIdentitas', function () {
    $penghuni = penghuniBerNik();

    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::penghuni.form', ['penghuni' => $penghuni]);

    pastikanSnapshotTanpaNik($komponen);
    $komponen->assertDontSee(NIK_UJI_IDENTITAS_KELOMPOK);

    $komponen->call('tampilkanIdentitas')
        ->assertSet('identitasTerbuka', true)
        ->assertSee(NIK_UJI_IDENTITAS_KELOMPOK)
        ->assertSee('Sembunyikan');

    pastikanSnapshotTanpaNik($komponen);

    $komponen->call('sembunyikanIdentitas')
        ->assertSet('identitasTerbuka', false)
        ->assertDontSee(NIK_UJI_IDENTITAS_KELOMPOK)
        ->assertSee('32**********8901')
        ->assertSee('Tampilkan');
});

it('NFR-02: NIK utuh tidak ada di HTML awal form ubah dan keadaan terbuka hilang saat dimuat ulang', function () {
    $penghuni = penghuniBerNik();
    $pengelola = User::factory()->pengelola()->create();

    Livewire::actingAs($pengelola)->test('pages::penghuni.form', ['penghuni' => $penghuni])->call('tampilkanIdentitas');

    $this->actingAs($pengelola)
        ->get(route('penghuni.ubah', $penghuni))
        ->assertOk()
        ->assertDontSee(NIK_UJI_IDENTITAS)
        ->assertDontSee(NIK_UJI_IDENTITAS_KELOMPOK)
        ->assertSee('32**********8901');
});

it('NFR-02: field NIK selalu mulai kosong dan identitasTerbuka tidak bisa diubah dari browser', function () {
    $penghuni = penghuniBerNik();

    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::penghuni.form', ['penghuni' => $penghuni])
        ->assertSet('nik', '')
        ->assertSet('identitasTerbuka', false);

    expect(fn () => $komponen->set('identitasTerbuka', true))->toThrow('Cannot update locked property');
});

it('NFR-02: PenghuniPolicy::lihatIdentitas hanya untuk pemilik, pengelola, dan penghuni yang bersangkutan', function () {
    $penghuni = penghuniBerNik();
    $lain = Penghuni::factory()->create();

    expect(User::factory()->pemilik()->create()->can('lihatIdentitas', $penghuni))->toBeTrue()
        ->and(User::factory()->pengelola()->create()->can('lihatIdentitas', $penghuni))->toBeTrue()
        ->and($penghuni->user->can('lihatIdentitas', $penghuni))->toBeTrue()
        ->and($lain->user->can('lihatIdentitas', $penghuni))->toBeFalse()
        ->and(User::factory()->vendor()->create()->can('lihatIdentitas', $penghuni))->toBeFalse();
});

it('NFR-02: tampilkanIdentitas dari peran yang tidak berhak ditolak', function () {
    $penghuni = penghuniBerNik();
    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::penghuni.form', ['penghuni' => $penghuni]);

    $this->actingAs(User::factory()->vendor()->create());

    $komponen->call('tampilkanIdentitas')->assertForbidden();
});
