{{-- Item menu sidebar. Dibuat sendiri karena gaya flux:sidebar.item dirancang untuk sidebar terang. --}}
@props([
    'href',
    'aktif' => false,
    'jumlah' => null,
    // Kunci antrean: angka ikut berubah lewat event window "antrean-{kunci}" (detail.jumlah) tanpa memuat ulang menu.
    'antrean' => null,
    // Keterangan untuk pembaca layar: "{n} {satuan}".
    'satuan' => 'bukti menunggu',
])

@php
    // Angka antrean: pil 22px, 13px, angka tabular. Item aktif berlatar Primary, item biasa berlatar putih transparan.
    $kelasAngka = 'teks-pil inline-flex h-[22px] flex-none items-center rounded-full px-2 text-white';
    $kelasAngkaAktif = 'bg-primary font-semibold';
    $kelasAngkaBiasa = 'bg-primary-container/[.14]';
@endphp

<a
    href="{{ $href }}"
    wire:navigate
    @if ($aktif) aria-current="page" @endif
    {{ $attributes->class([
        'menu-sidebar flex min-h-11 pendek:min-h-10 items-center justify-between gap-2 rounded-lg px-3 no-underline',
        'teks-menu-aktif bg-primary-container text-on-primary-container' => $aktif,
        'teks-menu text-on-sidebar' => ! $aktif,
    ]) }}
>
    {{-- Label satu baris; bila tetap tidak muat dipotong dengan elipsis dan title berisi teks lengkap. --}}
    <span class="min-w-0 truncate whitespace-nowrap" title="{{ trim(strip_tags((string) $slot)) }}">{{ $slot }}</span>
    @if ($antrean)
        <span
            x-data="{ n: {{ (int) $jumlah }} }"
            x-on:antrean-{{ $antrean }}.window="n = $event.detail.jumlah"
            x-show="n > 0"
            @if (! $jumlah) style="display: none" @endif
            @class([$kelasAngka, $aktif ? $kelasAngkaAktif : $kelasAngkaBiasa])
            data-test="angka-antrean"
        ><span x-text="n" aria-hidden="true">{{ $jumlah }}</span><span class="sr-only" x-text="n + ' {{ $satuan }}'">{{ $jumlah }} {{ $satuan }}</span></span>
    @elseif ($jumlah)
        <span @class([$kelasAngka, $aktif ? $kelasAngkaAktif : $kelasAngkaBiasa]) data-test="angka-menu">{{ $jumlah }}</span>
    @endif
</a>
