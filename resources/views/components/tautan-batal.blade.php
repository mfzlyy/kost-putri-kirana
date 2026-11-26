{{-- Tautan aksi "Batal": teks primer, berat 600, garis bawah saat hover. --}}
@props([
    'href',
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('tautan-aksi inline-flex h-11 items-center justify-center px-4') }}
>{{ $slot->isEmpty() ? 'Batal' : $slot }}</a>
