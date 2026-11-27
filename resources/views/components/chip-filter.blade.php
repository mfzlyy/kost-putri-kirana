{{-- Chip filter pil 36px. Barisan chip dibungkus <x-chip-filter.baris label="Saring ...">. --}}
@props([
    'aktif' => false,
    'jumlah' => null,
])

<button
    type="button"
    aria-pressed="{{ $aktif ? 'true' : 'false' }}"
    {{ $attributes->class([
        'chip teks-chip inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 max-sm:snap-start',
        'border-chip-active bg-chip-active text-white' => $aktif,
        'border-border-strong bg-surface text-ink' => ! $aktif,
    ]) }}
>
    {{ $slot }}
    @if ($jumlah !== null)
        <span class="num">{{ $jumlah }}</span>
    @endif
</button>
