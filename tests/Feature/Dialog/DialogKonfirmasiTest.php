<?php

use App\Enums\StatusAkun;
use App\Models\Kamar;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Struktur yang sama di semua dialog konfirmasi: judul, tombol Tutup, ringkasan, akibat, garis kaki, Batal, aksi.
 */
function periksaBentukDialog($komponen, string $name): void
{
    $html = $komponen->html();

    expect($html)->toContain('data-modal="'.$name.'"')
        ->and($html)->toContain('id="judul-'.$name.'"')
        ->and($html)->toContain('aria-label="Tutup"')
        ->and($html)->toContain('data-test="ringkasan-dialog"')
        ->and($html)->toContain('data-test="akibat-dialog"')
        ->and($html)->toContain('border-t border-divider')
        ->and($html)->toMatch('/Batal\s*<\/button>/')
        ->and($html)->toContain('autofocus')
        ->and($html)->toContain('max-sm:flex-col-reverse')
        ->and($html)->toContain('bg-status-late!');
}

it('dialog Akhiri kontrak memakai komponen seragam dengan catatan atau field sesuai kontrak', function () {
    $berjalan = buatKontrakUji('07', 'Nadia Ramadhani', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-06-30']);

    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())->test('pages::kontrak.index')
        ->call('konfirmasiAkhiri', $berjalan->id);

    periksaBentukDialog($komponen, 'akhiri-kontrak');
    $komponen->assertSee('Tanggal keluar')->assertSee('Akhiri kontrak');
});

it('dialog Hapus kamar di daftar dan di form memakai komponen seragam', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '08', 'tarif_bulanan' => 1250000, 'keterangan' => 'Lantai 2']);
    $pengelola = User::factory()->pengelola()->create();

    $daftar = Livewire::actingAs($pengelola)->test('pages::kamar.index')->call('konfirmasiHapus', $kamar->id);

    periksaBentukDialog($daftar, 'hapus-kamar');
    $daftar->assertSee('Hapus Kamar 08?')
        ->assertSeeInOrder(['Tarif', 'Rp1.250.000 per bulan', 'Keterangan', 'Lantai 2'])
        ->assertSee('Kamar ini akan dihapus permanen.')
        ->assertSee('Hapus kamar');

    $form = Livewire::actingAs($pengelola)->test('pages::kamar.form', ['kamar' => $kamar]);

    periksaBentukDialog($form, 'hapus-kamar');
    $form->assertSee('Hapus Kamar 08?')->assertSee('Rp1.250.000 per bulan');
});

it('dialog Tandai sudah keluar memakai komponen seragam', function () {
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => 'Maya Sari', 'email' => 'maya@contoh.test']),
        'no_telepon' => '081234567801',
    ]);

    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())->test('pages::penghuni.form', ['penghuni' => $penghuni]);

    periksaBentukDialog($komponen, 'tandai-keluar');
    $komponen->assertSee('Tandai Maya Sari sudah keluar?')
        ->assertSeeInOrder(['Email akun', 'maya@contoh.test', 'Telepon', '0812 3456 7801'])
        ->assertSeeInOrder(['Status menjadi nonaktif.', 'Riwayat kontrak, tagihan, dan pembayaran tetap tersimpan.', 'Akun login diatur terpisah di Akun pengguna.']);
});

it('F-02: Nonaktifkan akun memakai dialog seragam, bukan confirm bawaan browser', function () {
    $akun = User::factory()->penghuni()->create(['nama' => 'Rina Maharani', 'email' => 'rina@contoh.test']);
    $pengelola = User::factory()->pengelola()->create();

    $komponen = Livewire::actingAs($pengelola)->test('pages::akun.index');

    expect($komponen->html())->not->toContain('wire:confirm');

    $komponen->call('konfirmasiNonaktifkan', $akun->id)->assertSet('idNonaktif', $akun->id);

    periksaBentukDialog($komponen, 'nonaktifkan-akun');
    $komponen->assertSee('Nonaktifkan akun Rina Maharani?')
        ->assertSeeInOrder(['Email', 'rina@contoh.test', 'Peran', 'Penghuni'])
        ->assertSee('Pengguna ini tidak bisa masuk sampai diaktifkan kembali.')
        ->assertSee('Sesi yang sedang berjalan langsung berakhir.')
        ->assertSee('Nonaktifkan')
        ->call('nonaktifkan', $akun->id)
        ->assertSet('idNonaktif', null);

    expect($akun->fresh()->status_akun)->toBe(StatusAkun::Nonaktif);
});

it('F-02: konfirmasiNonaktifkan tetap ditegakkan Policy', function () {
    $pengelola = User::factory()->pengelola()->create();
    $pemilik = User::factory()->pemilik()->create();

    Livewire::actingAs($pengelola)->test('pages::akun.index')->call('konfirmasiNonaktifkan', $pengelola->id)->assertForbidden();
    Livewire::actingAs($pengelola)->test('pages::akun.index')->call('konfirmasiNonaktifkan', $pemilik->id)->assertForbidden();
    Livewire::actingAs($pengelola)->test('pages::akun.index')->call('konfirmasiNonaktifkan', 999999)->assertNotFound();
});

it('kunci kamar terpilih memakai garis 2px lewat bayangan dalam, tanpa ring yang menggeser ukuran', function () {
    Kamar::factory()->create(['nomor_kamar' => '07']);

    $html = Livewire::actingAs(User::factory()->pengelola()->create())->test('pages::kontrak.form')->html();

    expect($html)->toContain('peer-checked:shadow-[inset_0_0_0_1px_var(--color-primary)]')
        ->and($html)->toContain('peer-checked:border-primary')
        ->and($html)->not->toContain('peer-checked:ring-1');
});
