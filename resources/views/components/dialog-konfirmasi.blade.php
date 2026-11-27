{{--
    Dialog konfirmasi aksi (docs/design/DESIGN.md, bagian Dialog). Dibangun di atas flux:modal.

    Atribut yang diteruskan (wire:click, data-test) dipasang pada tombol aksi.
    Slot bernama, semuanya opsional:
      - ringkasan : pasangan <dt>/<dd> tentang objek yang dikenai aksi
      - akibat    : satu atau dua kalimat biasa: apa yang terjadi dan apakah bisa dibatalkan (tanpa istilah teknis)
      - catatan   : satu kalimat Catatan info
      - isi       : field isian (label, bantuan, galat)

    variant "bahaya" membuat tombol aksi merah penuh (x-tombol bahaya-penuh); "biasa" memakai x-tombol utama.
    Desktop: dialog di tengah, lebar maksimal 480 px. Ponsel: bottom sheet dengan tombol selebar layar,
    tombol aksi di atas dan Batal di bawah. Fokus awal di tombol Batal.
--}}
@props([
    'name',
    'judul',
    'labelAksi',
    'variant' => 'bahaya',
    'ringkasan' => null,
    'akibat' => null,
    'catatan' => null,
    'isi' => null,
])

@php
    $idJudul = 'judul-'.$name;
    // wire:click="hapus(3)" -> target "hapus", supaya hanya aksi ini yang menandai tombol sedang memuat.
    $targetAksi = \Illuminate\Support\Str::before((string) $attributes->get('wire:click'), '(');
@endphp

<flux:modal
    :name="$name"
    variant="bare"
    :closable="false"
    class="m-auto max-h-[calc(100dvh-48px)] w-full max-w-[480px] overflow-y-auto rounded-2xl bg-surface! p-0 text-ink shadow-[0_24px_60px_rgba(12,53,51,.28)] max-sm:m-0 max-sm:mt-auto max-sm:max-h-[calc(100dvh-24px)] max-sm:max-w-none max-sm:rounded-b-none"
>
    <div role="group" aria-labelledby="{{ $idJudul }}" class="flex flex-col gap-[18px] p-7 max-sm:p-4 max-sm:pb-[max(1rem,env(safe-area-inset-bottom))]">
        {{-- Kepala --}}
        <div class="flex items-start justify-between gap-4">
            <h2 id="{{ $idJudul }}" class="teks-judul-dialog m-0 min-w-0 break-words text-ink">{{ $judul }}</h2>
            <flux:modal.close>
                <button type="button" aria-label="Tutup" class="tombol-ikon -me-2 -mt-2 flex size-10 shrink-0 items-center justify-center text-ink-muted">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </flux:modal.close>
        </div>

        {{-- Ringkasan objek --}}
        @if ($ringkasan && ! $ringkasan->isEmpty())
            <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 rounded-xl bg-background px-4 py-3.5 [&_dd]:m-0 [&_dd]:teks-badan-kuat [&_dd]:break-words [&_dd]:text-ink [&_dt]:teks-bantuan" data-test="ringkasan-dialog">
                {{ $ringkasan }}
            </dl>
        @endif

        {{-- Akibat --}}
        @if ($akibat && ! $akibat->isEmpty())
            <p class="m-0 text-ink teks-badan" data-test="akibat-dialog">{{ $akibat }}</p>
        @endif

        {{-- Catatan info --}}
        @if ($catatan && ! $catatan->isEmpty())
            <p class="m-0 rounded-xl bg-primary-container px-3.5 py-3 text-on-primary-container teks-badan" data-test="catatan-dialog">{{ $catatan }}</p>
        @endif

        {{-- Field isian --}}
        @if ($isi && ! $isi->isEmpty())
            <div class="flex flex-col gap-4">
                {{ $isi }}
            </div>
        @endif

        {{-- Kaki --}}
        <div class="flex justify-end gap-2 border-t border-divider pt-[18px] max-sm:flex-col-reverse">
            <flux:modal.close>
                <x-tombol varian="sekunder" autofocus class="max-sm:w-full">Batal</x-tombol>
            </flux:modal.close>

            <x-tombol
                :varian="$variant === 'bahaya' ? 'bahaya-penuh' : 'utama'"
                {{ $attributes->class('max-sm:w-full')->merge($targetAksi !== '' ? ['wire:target' => $targetAksi] : []) }}
                wire:loading.attr="disabled"
            >{{ $labelAksi }}</x-tombol>
        </div>
    </div>
</flux:modal>
