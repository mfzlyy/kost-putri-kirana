{{-- Logo + wordmark dua baris. Teks tetap (tidak dari config) agar tidak bergantung pada .env. --}}
@props([
    'sidebar' => false,
    'href' => url('/beranda'),
    'ukuran' => 'normal',
])

@php
    $kecil = $ukuran === 'kecil';
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class(['flex items-center gap-2.5 rounded-lg no-underline', 'sorot-gelap' => $sidebar]) }}
>
    <x-app-logo-icon
        :varian="$sidebar ? 'gelap' : 'terang'"
        aria-hidden="true"
        :class="$kecil ? 'h-6 w-5 shrink-0' : 'h-[31px] w-[26px] shrink-0'"
    />
    <span class="sr-only">Kost Putri Kirana, ke beranda</span>
    <span class="flex flex-col" aria-hidden="true">
        @unless ($kecil)
            <span @class(['teks-merek-atas', 'text-on-sidebar-muted' => $sidebar, 'text-ink-muted' => ! $sidebar])>Kost Putri</span>
        @endunless
        <span @class([
            'teks-merek' => ! $kecil,
            'teks-merek-kecil' => $kecil,
            'text-white' => $sidebar,
            'text-ink' => ! $sidebar,
        ])>Kirana</span>
    </span>
</a>
