{{--
    Segmen periode (docs/design/DESIGN.md, bagian Components): tiga tautan, yang aktif ditandai aria-current.
    Di ponsel (< 640px) barisnya sendiri selebar konten, tiga bagian sama lebar, tinggi 40px, nama bulan pendek.

      pilihan : daftar ['nilai' => 'YYYY-MM', 'href', 'aktif', 'label' => 'Oktober', 'pendek' => 'Okt',
                'lengkap' => 'Oktober 2026'] (lengkap dibacakan pembaca layar lewat aria-label)
      aksi    : metode Livewire yang menerima nilai
--}}
@props([
    'pilihan',
    'aksi' => 'pilihPeriode',
    'label' => 'Periode',
])

<nav aria-label="{{ $label }}" {{ $attributes->class('grid h-10 w-full grid-cols-3 overflow-hidden rounded-lg border border-border-strong bg-surface max-sm:basis-full! sm:inline-flex sm:w-auto sm:gap-1 sm:rounded-[10px] sm:border-0 sm:bg-surface-muted sm:p-1') }}>
    @foreach ($pilihan as $item)
        <a
            href="{{ $item['href'] }}"
            wire:click.prevent="{{ $aksi }}('{{ $item['nilai'] }}')"
            aria-label="{{ $item['lengkap'] }}"
            @if ($item['aktif']) aria-current="true" @endif
            @class([
                'segmen teks-chip flex min-w-0 items-center justify-center whitespace-nowrap border-s border-border-strong no-underline first:border-s-0 sm:h-8 sm:rounded-[7px] sm:border-0 sm:px-3.5',
                'bg-chip-active text-white' => $item['aktif'],
                'text-ink' => ! $item['aktif'],
            ])
        ><span class="sm:hidden">{{ $item['pendek'] }}</span><span class="max-sm:hidden">{{ $item['label'] }}</span></a>
    @endforeach
</nav>
