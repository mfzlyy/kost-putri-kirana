{{-- Status = ikon 14px + teks berat 600, tanpa latar dan tanpa bingkai (docs/design/DESIGN.md, bagian Status). --}}
@props([
    'jenis',
])

@php
    $daftar = [
        'ok' => 'text-status-ok',
        'tunggu' => 'text-status-wait',
        'belum' => 'text-status-none',
        'kosong' => 'text-status-none',
        'telat' => 'text-status-late',
        'gagal' => 'text-status-late',
    ];

    if (! array_key_exists($jenis, $daftar)) {
        throw new \InvalidArgumentException("Jenis status tidak dikenal: {$jenis}");
    }
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap text-sm font-semibold', $daftar[$jenis]]) }}>
    <svg viewBox="0 0 16 16" class="size-3.5 shrink-0" aria-hidden="true">
        @switch($jenis)
            @case('ok')
                <circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M4.8 8.2l2.1 2.1 4.3-4.6" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                @break
            @case('tunggu')
                <circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 4.8V8l2.2 1.4" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                @break
            @case('belum')
                <circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/>
                @break
            @case('kosong')
                <circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-dasharray="2.4 2"/>
                @break
            @case('telat')
                <path d="M8 1.5 15 14H1z" fill="currentColor"/><path d="M8 6v3.6" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/><circle cx="8" cy="11.8" r=".95" fill="#fff"/>
                @break
            @case('gagal')
                <circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M5.6 5.6l4.8 4.8M10.4 5.6l-4.8 4.8" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
                @break
        @endswitch
    </svg>
    <span>{{ $slot }}</span>
</span>
