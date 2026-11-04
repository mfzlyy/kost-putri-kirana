<?php

use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use Livewire\Livewire;

function halamanPengaturanSebagai(User $user)
{
    return Livewire::actingAs($user)->test('pages::pengaturan.index');
}

it('F-04: pengelola melihat form pengaturan', function () {
    PengaturanOperasional::factory()->lengkap(10, 30)->create();

    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/pengaturan')
        ->assertOk()
        ->assertSee('Simpan pengaturan')
        ->assertSee('Tanggal jatuh tempo tagihan');
});

it('F-04: pemilik melihat nilai tanpa form', function () {
    PengaturanOperasional::factory()->lengkap(10, 30)->create();

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/pengaturan')
        ->assertOk()
        ->assertSee('Tanggal 10')
        ->assertSee('30 hari')
        ->assertSee('Diubah oleh pengelola.')
        ->assertDontSee('Simpan pengaturan');
});

it('F-04: pemilik melihat Belum diatur bila nilai kosong', function () {
    PengaturanOperasional::factory()->create();

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/pengaturan')
        ->assertOk()
        ->assertSee('Belum diatur');
});

it('F-04: pengelola menyimpan pengaturan dan diperbarui_oleh terisi', function () {
    PengaturanOperasional::factory()->create();
    $pengelola = User::factory()->pengelola()->create(['nama' => 'Dewi Pengelola']);

    halamanPengaturanSebagai($pengelola)
        ->set('tanggal_jatuh_tempo', 10)
        ->set('batas_peringatan_kontrak_hari', 30)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertSee('Terakhir diubah oleh Dewi Pengelola');

    $baris = PengaturanOperasional::query()->find(1);

    expect($baris->tanggal_jatuh_tempo)->toBe(10)
        ->and($baris->batas_peringatan_kontrak_hari)->toBe(30)
        ->and($baris->diperbarui_oleh)->toBe($pengelola->id);
});

it('F-04: validasi menolak tanggal dan batas di luar rentang dengan pesan yang benar', function (mixed $tanggal, mixed $batas, string $kolom, string $pesan) {
    PengaturanOperasional::factory()->create();

    halamanPengaturanSebagai(User::factory()->pengelola()->create())
        ->set('tanggal_jatuh_tempo', $tanggal)
        ->set('batas_peringatan_kontrak_hari', $batas)
        ->call('simpan')
        ->assertHasErrors([$kolom])
        ->assertSee($pesan);

    expect(PengaturanOperasional::query()->find(1)->tanggal_jatuh_tempo)->toBeNull();
})->with([
    'tanggal 0' => [0, 30, 'tanggal_jatuh_tempo', 'Pilih tanggal jatuh tempo antara 1 dan 28.'],
    'tanggal 29' => [29, 30, 'tanggal_jatuh_tempo', 'Pilih tanggal jatuh tempo antara 1 dan 28.'],
    'tanggal kosong' => [null, 30, 'tanggal_jatuh_tempo', 'Pilih tanggal jatuh tempo antara 1 dan 28.'],
    'batas 0' => [10, 0, 'batas_peringatan_kontrak_hari', 'Isi batas antara 1 dan 365 hari.'],
    'batas 366' => [10, 366, 'batas_peringatan_kontrak_hari', 'Isi batas antara 1 dan 365 hari.'],
    'batas kosong' => [10, null, 'batas_peringatan_kontrak_hari', 'Isi batas antara 1 dan 365 hari.'],
]);

it('F-04: pemilik yang memanggil aksi simpan langsung ditolak dan data tidak berubah', function () {
    PengaturanOperasional::factory()->lengkap(10, 30)->create();

    halamanPengaturanSebagai(User::factory()->pemilik()->create())
        ->set('tanggal_jatuh_tempo', 15)
        ->set('batas_peringatan_kontrak_hari', 60)
        ->call('simpan')
        ->assertForbidden();

    $baris = PengaturanOperasional::query()->find(1);

    expect($baris->tanggal_jatuh_tempo)->toBe(10)
        ->and($baris->batas_peringatan_kontrak_hari)->toBe(30)
        ->and($baris->diperbarui_oleh)->toBeNull();
});

it('F-04: peringatan muncul bila tanggal jatuh tempo belum diatur dan hilang setelah diisi', function () {
    PengaturanOperasional::factory()->create();

    halamanPengaturanSebagai(User::factory()->pengelola()->create())
        ->assertSee('Tanggal jatuh tempo belum diatur. Tagihan belum bisa diterbitkan sampai tanggal ini diisi.')
        ->set('tanggal_jatuh_tempo', 10)
        ->set('batas_peringatan_kontrak_hari', 30)
        ->call('simpan')
        ->assertDontSee('Tanggal jatuh tempo belum diatur.');
});

it('F-04: menyimpan pengaturan tidak mengubah tagihan yang sudah terbit', function () {
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
    $tagihan = Tagihan::factory()->create();
    $semula = $tagihan->only(['jatuh_tempo', 'nominal', 'periode', 'status_tagihan']);

    halamanPengaturanSebagai(User::factory()->pengelola()->create())
        ->set('tanggal_jatuh_tempo', 20)
        ->set('batas_peringatan_kontrak_hari', 45)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Tagihan::query()->find($tagihan->id)->only(['jatuh_tempo', 'nominal', 'periode', 'status_tagihan']))
        ->toEqual($semula);
});

it('F-04: penghuni dan vendor mendapat 403 di /pengaturan', function (string $peran) {
    PengaturanOperasional::factory()->create();

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/pengaturan')
        ->assertForbidden();
})->with(['penghuni', 'vendor']);
