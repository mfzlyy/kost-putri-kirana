{{--
    Ringkasan angka (docs/design/DESIGN.md, bagian Layout). Satu kisi untuk semua halaman.

    items: daftar ['label' => string, 'nilai' => string, 'keterangan' => ?string, 'nada' => 'biasa'|'bahaya', 'kunci' => ?string]
      - >= lg: kolom sama lebar sebanyak item, label di atas, nilai (teks-angka, tanpa putus baris), keterangan di bawah.
      - md sampai lg: tiga kolom, baris kedua rata kiri.
      - < md: daftar baris, label kiri dan nilai kanan; item bahaya di baris terakhir.
      - nada bahaya: label dan nilai warna status-late, dipisah garis kiri 1px dan padding kiri 24px (>= md).
--}}
@props([
    'items',
    'label' => null,
])

@php
    $jumlah = count($items);
    // Item bahaya selalu paling akhir.
    $urut = collect($items)->sortBy(fn ($item) => ($item['nada'] ?? 'biasa') === 'bahaya' ? 1 : 0)->values();
    $atribut = fn (array $nilai) => new \Illuminate\View\ComponentAttributeBag($nilai);
@endphp

<section
    {{ $atribut(array_filter(['aria-label' => $label])) }}
    {{ $attributes->class([
        'grid grid-cols-1 rounded-2xl border border-border bg-surface px-4 py-1 md:grid-cols-[repeat(var(--kolom-md),minmax(0,1fr))] md:gap-x-6 md:gap-y-4 md:px-6 md:py-5 lg:grid-cols-[repeat(var(--kolom),minmax(0,1fr))]',
    ])->merge(['style' => "--kolom: {$jumlah}; --kolom-md: ".min($jumlah, 3).';', 'data-test' => 'ringkasan']) }}
>
    @foreach ($urut as $item)
        @php
            $bahaya = ($item['nada'] ?? 'biasa') === 'bahaya';
            $kunci = $item['kunci'] ?? null;
            $tandaNada = $bahaya ? ['data-nada' => 'bahaya'] : [];
        @endphp
        <div
            {{ $atribut($tandaNada)->class([
                'grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-0.5 border-t border-row-divider py-3 first:border-t-0 md:block md:border-t-0 md:py-0',
                'text-status-late md:border-s md:border-border md:ps-6' => $bahaya,
            ]) }}
        >
            <div {{ $atribut($kunci ? ['data-test' => "ringkasan-{$kunci}-label"] : [])->class(['teks-kolom', 'text-status-late!' => $bahaya]) }}>{{ $item['label'] }}</div>
            <div {{ $atribut($kunci ? ['data-test' => "ringkasan-{$kunci}"] : [])->class(['teks-angka md:mt-1']) }}>{{ $item['nilai'] }}</div>
            @if (filled($item['keterangan'] ?? null))
                <div {{ $atribut($kunci ? ['data-test' => "ringkasan-{$kunci}-keterangan"] : [])->class(['teks-bantuan max-md:col-span-2 md:mt-0.5', 'text-status-late!' => $bahaya]) }}>{{ $item['keterangan'] }}</div>
            @endif
        </div>
    @endforeach
</section>
