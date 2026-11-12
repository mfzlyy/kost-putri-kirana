<?php

use App\Models\User;
use Livewire\Livewire;

function dialogAkunSebagai(User $user)
{
    return Livewire::actingAs($user)->test('pages::akun.index');
}

it('F-02: pilihan peran di dialog berupa radio, bukan dropdown, dengan Penghuni sebagai bawaan', function () {
    $komponen = dialogAkunSebagai(User::factory()->pengelola()->create())
        ->assertSet('peran', 'penghuni')
        ->assertSeeHtml('type="radio"')
        ->assertSeeHtml('name="peran"')
        ->assertDontSee('Pilih peran')
        ->assertDontSee('Nama Vendor');

    expect(substr_count($komponen->html(), 'name="peran"'))->toBe(2);
});

it('F-02: memilih Vendor menampilkan Nama vendor, Jenis layanan, dan Telepon', function () {
    dialogAkunSebagai(User::factory()->pengelola()->create())
        ->set('peran', 'vendor')
        ->assertSee('Data Vendor')
        ->assertSee('Nama Vendor')
        ->assertSee('Jenis Layanan')
        ->assertSee('Telepon')
        ->assertSee('Awalnya diisi dari nama akun.');
});

it('F-02: Nama vendor terisi dari Nama sampai pengguna mengubahnya sendiri', function () {
    dialogAkunSebagai(User::factory()->pengelola()->create())
        ->set('nama', 'Budi Teknik')
        ->set('peran', 'vendor')
        ->assertSet('nama_vendor', 'Budi Teknik')
        ->set('nama', 'Budi Listrik')
        ->assertSet('nama_vendor', 'Budi Listrik')
        ->set('nama_vendor', 'CV Budi Jaya')
        ->set('nama', 'Budi Lain')
        ->assertSet('nama_vendor', 'CV Budi Jaya');
});

it('F-02: setelah galat email ganda, nilai nama dan email yang diketik tetap ada', function () {
    User::factory()->penghuni()->create(['email' => 'sudah.ada@contoh.test']);

    dialogAkunSebagai(User::factory()->pengelola()->create())
        ->set('nama', 'Sari Wulandari')
        ->set('email', 'sudah.ada@contoh.test')
        ->set('password', 'SandiAwal123')
        ->set('password_confirmation', 'SandiAwal123')
        ->call('simpan')
        ->assertHasErrors(['email'])
        ->assertSet('nama', 'Sari Wulandari')
        ->assertSet('email', 'sudah.ada@contoh.test')
        ->assertSet('peran', 'penghuni');
});

it('F-02: dialog punya judul, tombol tutup, dan tombol Buat akun', function () {
    dialogAkunSebagai(User::factory()->pengelola()->create())
        ->assertSee('Tambah Akun')
        ->assertSeeHtml('aria-label="Tutup"')
        ->assertSee('Buat')
        ->assertSee('Minimal 8 karakter, huruf dan angka.');
});
