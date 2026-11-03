<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function ubahPassword(User $user, string $lama, string $baru, ?string $konfirmasi = null)
{
    return Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', $lama)
        ->set('password', $baru)
        ->set('password_confirmation', $konfirmasi ?? $baru)
        ->call('updatePassword');
}

it('menampilkan halaman Ganti Password untuk semua peran', function (string $peran) {
    $this->actingAs(User::factory()->{$peran}()->create())
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Ganti Password')
        ->assertSee('Password lama')
        ->assertSee('Konfirmasi password baru');
})->with(['pemilik', 'pengelola', 'penghuni', 'vendor']);

it('F-01: password lama yang salah ditolak', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'PasswordLama123']);

    ubahPassword($user, 'bukan-password-lama', 'PasswordBaru456')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('PasswordLama123', $user->fresh()->password_hash))->toBeTrue();
});

it('F-01: password baru yang tidak memenuhi aturan ditolak', function (string $baru) {
    $user = User::factory()->penghuni()->create(['password_hash' => 'PasswordLama123']);

    ubahPassword($user, 'PasswordLama123', $baru)->assertHasErrors(['password']);

    expect(Hash::check('PasswordLama123', $user->fresh()->password_hash))->toBeTrue();
})->with([
    'terlalu pendek' => 'Ab1',
    'tanpa angka' => 'HurufSajaPanjang',
    'tanpa huruf' => '1234567890',
]);

it('F-01: konfirmasi password yang tidak sama ditolak', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'PasswordLama123']);

    ubahPassword($user, 'PasswordLama123', 'PasswordBaru456', 'PasswordBeda789')
        ->assertHasErrors(['password']);
});

it('F-01: password berhasil diganti lalu login dengan password baru berhasil dan yang lama gagal', function () {
    $user = User::factory()->penghuni()->create(['password_hash' => 'PasswordLama123']);

    ubahPassword($user, 'PasswordLama123', 'PasswordBaru456')->assertHasNoErrors();

    expect(Hash::check('PasswordBaru456', $user->fresh()->password_hash))->toBeTrue();

    auth()->logout();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'PasswordLama123']);
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'PasswordBaru456'])
        ->assertRedirect(route('tagihan-saya.index'));
    $this->assertAuthenticatedAs($user);
});
