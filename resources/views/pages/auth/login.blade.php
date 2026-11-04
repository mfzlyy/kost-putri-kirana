<x-layouts::auth title="Masuk">
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-[22px]">
        @csrf

        <div>
            <h1 class="m-0 text-[22px] leading-[1.1] font-bold font-stretch-expanded lg:text-3xl">Masuk</h1>
            <p class="mt-2 mb-0 text-ink-muted">Pakai email dan password dari pengelola.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="flex flex-col gap-1 rounded-lg bg-danger-container px-3.5 py-3 font-medium text-on-danger-container">
                @foreach (array_unique($errors->all()) as $pesan)
                    <p class="m-0">{{ $pesan }}</p>
                @endforeach
            </div>
        @endif

        <flux:input
            name="email"
            label="Email"
            :value="old('email')"
            type="email"
            required
            autofocus
            autocomplete="email"
            placeholder="nama@contoh.com"
        />

        <flux:input
            name="password"
            label="Password"
            type="password"
            required
            autocomplete="current-password"
            viewable
        />

        <flux:button variant="primary" type="submit" class="h-12! w-full" data-test="login-button">
            Masuk
        </flux:button>

        <p class="m-0 text-sm text-ink-muted">Lupa password? Hubungi pengelola kos.</p>
    </form>
</x-layouts::auth>
