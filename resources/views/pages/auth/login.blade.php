@php
    // Galat autentikasi (salah password, akun nonaktif, throttle) memakai kunci email dari Fortify.
    // Hanya galat itu yang tampil di kotak atas; galat isian tetap di bawah field masing-masing.
    $galatIsianEmail = [
        trans('validation.required', ['attribute' => 'email']),
        trans('validation.email', ['attribute' => 'email']),
    ];
    $galatAutentikasi = collect($errors->get('email'))->reject(fn ($pesan) => in_array($pesan, $galatIsianEmail, true))->unique()->values();
    $galatIsianEmailAda = collect($errors->get('email'))->contains(fn ($pesan) => in_array($pesan, $galatIsianEmail, true));

    // Galat autentikasi sudah tampil di kotak atas; hapus dari bag agar field Email tidak bergaris merah dan tidak ada teks galat ganda.
    if (! $galatIsianEmailAda) {
        $errors->getBag('default')->forget('email');
    }
@endphp

<x-layouts::auth title="Masuk">
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-[22px]">
        @csrf

        <div>
            <h1 class="m-0 text-[22px] leading-[1.1] font-bold font-stretch-expanded lg:text-3xl">Masuk</h1>
        </div>

        @if ($galatAutentikasi->isNotEmpty())
            <div role="alert" class="flex flex-col gap-1 rounded-lg bg-danger-container px-3.5 py-3 font-medium text-on-danger-container">
                @foreach ($galatAutentikasi as $pesan)
                    <p class="m-0">{{ $pesan }}</p>
                @endforeach
            </div>
        @endif

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
                :invalid="$galatIsianEmailAda"
            />
            @if ($galatIsianEmailAda)
                <flux:error name="email" />
            @endif
        </flux:field>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input
                name="password"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />
            <flux:error name="password" />
        </flux:field>

        <flux:button variant="primary" type="submit" class="h-12! w-full" data-test="login-button">
            Masuk
        </flux:button>

        <p class="m-0 text-sm text-ink-muted">Lupa password? Hubungi pengelola kos.</p>
    </form>
</x-layouts::auth>
