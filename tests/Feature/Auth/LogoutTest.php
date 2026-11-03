<?php

use App\Models\User;

it('F-01: logout menghapus sesi dan mengarahkan ke /login', function () {
    $user = User::factory()->pengelola()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('F-01: setelah logout halaman yang butuh login mengarah ke /login', function () {
    $user = User::factory()->pengelola()->create();

    $this->actingAs($user)->post(route('logout'));

    $this->get(route('verifikasi-pembayaran.index'))->assertRedirect(route('login'));
    $this->get(route('akun.index'))->assertRedirect(route('login'));
});
