{{-- Layout halaman masuk. Papan kunci 01-08 hanya hiasan statis, tidak membaca data kamar. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-svh bg-background text-ink antialiased">
        <div class="flex min-h-svh flex-col lg:flex-row">
            {{-- Panel desktop --}}
            <section class="hidden flex-1 basis-[460px] flex-col justify-between gap-12 bg-sidebar p-14 text-primary-container lg:flex">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 no-underline">
                    <x-app-logo-icon varian="gelap" aria-hidden="true" class="h-[38px] w-8" />
                    <span class="sr-only">Kost Putri Kirana</span>
                    <span class="flex flex-col leading-[1.1]" aria-hidden="true">
                        <span class="text-xs text-on-sidebar-muted">Kost Putri</span>
                        <span class="text-xl font-[750] font-stretch-expanded text-white">Kirana</span>
                    </span>
                </a>

                <div class="flex max-w-[460px] flex-col gap-7">
                    <div aria-hidden="true" class="grid max-w-[360px] grid-cols-4 gap-2.5">
                        @foreach (range(1, 8) as $nomor)
                            <div @class([
                                'relative flex h-[84px] items-end rounded-[10px] border border-primary-container/30 px-2.5 pt-[22px] pb-2 text-[22px] font-[750] font-stretch-expanded text-primary-container',
                                'opacity-45' => $nomor > 6,
                            ])>
                                <span class="absolute start-1/2 top-2 -ms-1 size-2 rounded-full border border-primary-container/50"></span>
                                <span class="num">{{ str_pad((string) $nomor, 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <p class="m-0 text-[44px] leading-[1.02] font-[750] font-stretch-expanded tracking-[-0.01em] text-white">Delapan kamar, satu catatan.</p>
                    <p class="m-0 max-w-[400px] text-[17px] text-on-sidebar">Tagihan, bukti bayar, dan laporan kerusakan kos di Cikarang Utara tercatat di sini.</p>
                </div>

                <p class="m-0 text-[13px] text-on-sidebar-muted">Akun dibuat oleh pengelola kos.</p>
            </section>

            {{-- Pita ponsel --}}
            <div class="flex flex-col gap-7 bg-sidebar px-5 pt-7 pb-14 lg:hidden">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 no-underline">
                    <x-app-logo-icon varian="gelap" aria-hidden="true" class="h-[34px] w-7" />
                    <span class="sr-only">Kost Putri Kirana</span>
                    <span class="flex flex-col leading-[1.1]" aria-hidden="true">
                        <span class="text-[11px] text-on-sidebar-muted">Kost Putri</span>
                        <span class="text-lg font-[750] font-stretch-expanded text-white">Kirana</span>
                    </span>
                </a>
                <p class="m-0 text-[26px] leading-[1.1] font-[750] font-stretch-expanded text-white">Cek tagihan dan lapor kerusakan dari ponsel.</p>
            </div>

            <section class="flex flex-1 basis-[420px] items-start justify-center max-lg:px-4 lg:items-center lg:px-5 lg:py-12">
                <div class="w-full max-lg:-mt-7 max-lg:rounded-2xl max-lg:border max-lg:bg-surface max-lg:p-5 lg:max-w-[380px]">
                    {{ $slot }}
                </div>
            </section>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
