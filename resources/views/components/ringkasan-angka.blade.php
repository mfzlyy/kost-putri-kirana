{{--
    Ringkasan angka (docs/design/DESIGN.md, bagian Layout). Satu kisi untuk semua halaman.

    items: daftar ['label' => string, 'nilai' => string, 'keterangan' => ?string, 'nada' => 'biasa'|'bahaya', 'kunci' => ?string]
    Tata letak mengikuti lebar kartu (container query di app.css), bukan lebar layar:
      - kartu >= 1000px: kolom sebanyak item; 600-999px: tiga kolom; < 600px: daftar label kiri, nilai kanan.
      - kolom minmax(max-content, 1fr); bila tetap tidak muat, skrip di bawah menaikkan data-turun (1 = tiga kolom,
        2 = daftar) sampai tidak ada angka yang keluar dari kotak.
      - nada bahaya: warna status-late, garis kiri 1px dan padding kiri 24px di dalam lebar kolomnya; selalu terakhir.
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
    {{ $attributes->class(['ringkasan rounded-2xl border border-border bg-surface px-4 py-1 md:px-6 md:py-5'])->merge(['data-test' => 'ringkasan']) }}
>
    <div
        class="ringkasan-isi"
        style="--kolom: {{ $jumlah }}; --kolom-md: {{ min($jumlah, 3) }};"
        x-data="{ turun: 0 }"
        x-ref="isi"
        x-bind:data-turun="turun"
        x-init="
            const g = $refs.isi;
            let lebar = -1;
            let sibuk = false;
            const meluber = () => g.scrollWidth > g.clientWidth + 1;
            const ukur = () => {
                if (sibuk) return;
                sibuk = true;
                turun = 0;
                $nextTick(() => {
                    if (! meluber()) { sibuk = false; return; }
                    turun = 1;
                    $nextTick(() => { if (meluber()) turun = 2; sibuk = false; });
                });
            };
            if (window.ResizeObserver) {
                new ResizeObserver((e) => {
                    const baru = Math.round(e[0].contentRect.width);
                    if (baru !== lebar) { lebar = baru; ukur(); }
                }).observe($el.parentElement);
            }
            if (window.MutationObserver) {
                new MutationObserver(ukur).observe(g, { childList: true, characterData: true, subtree: true });
            }
            ukur();
        "
    >
        @foreach ($urut as $item)
            @php
                $bahaya = ($item['nada'] ?? 'biasa') === 'bahaya';
                $kunci = $item['kunci'] ?? null;
                $tandaNada = $bahaya ? ['data-nada' => 'bahaya'] : [];
            @endphp
            <div {{ $atribut($tandaNada)->class(['ringkasan-sel', 'text-status-late' => $bahaya]) }}>
                <div {{ $atribut($kunci ? ['data-test' => "ringkasan-{$kunci}-label"] : [])->class(['teks-kolom', 'text-status-late!' => $bahaya]) }}>{{ $item['label'] }}</div>
                <div {{ $atribut($kunci ? ['data-test' => "ringkasan-{$kunci}"] : [])->class(['ringkasan-nilai teks-angka']) }}>{{ $item['nilai'] }}</div>
                @if (filled($item['keterangan'] ?? null))
                    <div {{ $atribut($kunci ? ['data-test' => "ringkasan-{$kunci}-keterangan"] : [])->class(['teks-bantuan ringkasan-sel-keterangan', 'text-status-late!' => $bahaya]) }}>{{ $item['keterangan'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</section>
