{{-- Item menu sidebar. Dibuat sendiri karena gaya flux:sidebar.item dirancang untuk sidebar terang. --}}
@props([
    'href',
    'aktif' => false,
    'jumlah' => null,
    // Kunci antrean: angka ikut berubah lewat event window "antrean-{kunci}" (detail.jumlah) tanpa memuat ulang menu.
    'antrean' => null,
])

<a
    href="{{ $href }}"
    wire:navigate
    @if ($aktif) aria-current="page" @endif
    {{ $attributes->class([
        'flex min-h-11 pendek:min-h-10 items-center justify-between gap-2 rounded-lg px-3 no-underline',
        'bg-primary-container font-[650] text-on-primary-container' => $aktif,
        'font-medium text-on-sidebar hover:bg-white/[.08] hover:text-white' => ! $aktif,
    ]) }}
>
    <span>{{ $slot }}</span>
    @if ($antrean)
        <span
            x-data="{ n: {{ (int) $jumlah }} }"
            x-on:antrean-{{ $antrean }}.window="n = $event.detail.jumlah"
            x-show="n > 0"
            x-text="n"
            @if (! $jumlah) style="display: none" @endif
            class="num rounded-full bg-primary-container/15 px-2 py-px text-[13px] text-white"
            data-test="angka-antrean"
        >{{ $jumlah }}</span>
    @elseif ($jumlah)
        <span class="num rounded-full bg-primary-container/15 px-2 py-px text-[13px] text-white">{{ $jumlah }}</span>
    @endif
</a>
