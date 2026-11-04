<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ganti password')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Ganti password pengguna yang sedang login (F-01). Disimpan ke kolom password_hash.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password:web'],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->forceFill([
            'password_hash' => $validated['password'],
        ])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: 'Password berhasil diganti.');
    }
}; ?>

<section class="flex w-full max-w-[520px] flex-col gap-4 lg:gap-6">
    <x-judul-halaman judul="Ganti password" deskripsi="Minimal 8 karakter, berisi huruf dan angka." />

    <form wire:submit="updatePassword" class="flex flex-col gap-[18px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
        <flux:input
            wire:model="current_password"
            label="Password lama"
            type="password"
            required
            autocomplete="current-password"
            viewable
        />
        <flux:input
            wire:model="password"
            label="Password baru"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />
        <flux:input
            wire:model="password_confirmation"
            label="Konfirmasi password baru"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <div class="flex flex-col gap-2 sm:flex-row">
            <flux:button variant="primary" type="submit" class="h-11! sm:w-auto" wire:loading.attr="disabled" data-test="update-password-button">
                Simpan password
            </flux:button>
            <x-tautan-batal :href="url('/beranda')" />
        </div>
    </form>
</section>
