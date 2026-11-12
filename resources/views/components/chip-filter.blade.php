{{-- Chip filter pil 36px. Bungkus barisan chip dengan <div role="group" aria-label="..."> memakai class "chip-baris". --}}
@props([
    'aktif' => false,
    'jumlah' => null,
])

<button
    type="button"
    aria-pressed="{{ $aktif ? 'true' : 'false' }}"
    {{ $attributes->class([
        'teks-chip inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full border px-3',
        'border-chip-active bg-chip-active text-white' => $aktif,
        'border-border-strong bg-surface text-ink hover:bg-surface-muted' => ! $aktif,
    ]) }}
>
    {{ $slot }}
    @if ($jumlah !== null)
        <span class="num">{{ $jumlah }}</span>
    @endif
</button>
