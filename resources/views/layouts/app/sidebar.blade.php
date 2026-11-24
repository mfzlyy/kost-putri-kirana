@php
    $pengguna = auth()->user();
    $menu = \App\Support\MenuNavigasi::denganAngka(\App\Support\MenuNavigasi::untuk($pengguna->peran), $pengguna->peran);
    // Penghuni di ponsel memakai tab bawah, bukan bilah gelap atas dan menu geser (F-11). Halaman tertentu
    // (unggah bukti) menyembunyikan tab lewat parameter layout $tanpaTabPenghuni.
    $adalahPenghuni = $pengguna->peran === \App\Enums\PeranPengguna::Penghuni;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-background text-ink antialiased">
        {{-- Desktop: sidebar tetap 232px. Ponsel: menu geser dari kiri selebar 288px. --}}
        <flux:sidebar
            sticky
            collapsible="mobile"
            aria-label="Menu"
            class="h-dvh! max-h-dvh! w-[232px] gap-0! overflow-y-hidden! border-0 bg-sidebar px-3.5 py-[22px] text-on-sidebar max-lg:w-72"
        >
            <div class="flex shrink-0 items-start justify-between gap-2 pb-[26px] ps-2.5">
                <x-app-logo :sidebar="true" />

                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('flux-sidebar-toggle')"
                    aria-label="Tutup menu"
                    class="-me-2 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-primary-container hover:bg-white/10 lg:hidden"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <nav aria-label="Menu utama" class="flex min-h-0 flex-1 flex-col gap-0.5 pendek:gap-0 overflow-y-auto [scrollbar-color:rgba(220,237,234,.25)_transparent] [scrollbar-width:thin]">
                @foreach ($menu as $item)
                    <x-menu-samping.item
                        :href="route($item['route'])"
                        :aktif="request()->routeIs($item['route'], \Illuminate\Support\Str::beforeLast($item['route'], '.').'.*')"
                        :jumlah="$item['jumlah'] ?? null"
                        :antrean="$item['antrean'] ?? null"
                        :satuan="$item['satuan'] ?? 'bukti menunggu'"
                    >
                        {{ $item['label'] }}
                    </x-menu-samping.item>
                @endforeach
            </nav>

            <x-menu-samping.pengguna />
        </flux:sidebar>

        {{-- Top bar ponsel --}}
        <header data-flux-header @class(['z-10 flex h-14 items-center gap-1.5 bg-sidebar ps-1 pe-3 [grid-area:header] lg:hidden', 'max-md:hidden' => $adalahPenghuni])>
            <button
                type="button"
                x-data
                x-on:click="$dispatch('flux-sidebar-toggle')"
                aria-label="Buka menu"
                class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-primary-container hover:bg-white/10"
            >
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>

            <x-app-logo :sidebar="true" ukuran="kecil" />

            <span class="ms-auto text-on-sidebar-muted! teks-bantuan">{{ $pengguna->peran->label() }}</span>
        </header>

        {{ $slot }}

        @if ($adalahPenghuni && ! ($tanpaTabPenghuni ?? false))
            <x-menu-tab-penghuni />
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
