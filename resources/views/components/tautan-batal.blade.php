{{-- Tombol sekunder berupa tautan "Batal": teks primer, berat 600, tanpa garis bawah. --}}
@props([
    'href',
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('inline-flex h-11 items-center justify-center rounded-lg px-4 hover:bg-primary-container/60') }}
>{{ $slot->isEmpty() ? 'Batal' : $slot }}</a>
