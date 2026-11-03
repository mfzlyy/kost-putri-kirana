<x-layouts::auth title="Masuk">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Masuk ke Akun" description="Masukkan email dan password Anda" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input
                    name="email"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="nama@contoh.com"
                />
                <flux:error name="email" />
            </flux:field>

            <flux:input
                name="password"
                label="Password"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                Masuk
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
