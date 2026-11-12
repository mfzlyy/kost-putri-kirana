{{--
    Dialog konfirmasi aksi (docs/design/DESIGN.md, bagian Dialog). Dibangun di atas flux:modal.

    Atribut yang diteruskan (wire:click, data-test) dipasang pada tombol aksi.
    Slot bernama, semuanya opsional:
      - ringkasan : pasangan <dt>/<dd> tentang objek yang dikenai aksi
      - akibat    : butir <li> berisi akibat aksi
      - catatan   : satu kalimat Catatan info
      - isi       : field isian (label, bantuan, galat)

    variant "bahaya" membuat tombol aksi merah (status-late); "biasa" memakai gaya Primary.
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
            <h2 id="{{ $idJudul }}" class="m-0 min-w-0 text-2xl leading-[1.1] font-[750] font-stretch-expanded break-words text-ink">{{ $judul }}</h2>
            <flux:modal.close>
                <button type="button" aria-label="Tutup" class="-me-2 -mt-2 flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-ink-muted hover:bg-surface-muted hover:text-ink">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </flux:modal.close>
        </div>

        {{-- Ringkasan objek --}}
        @if ($ringkasan && ! $ringkasan->isEmpty())
            <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 rounded-xl bg-background px-4 py-3.5 [&_dd]:m-0 [&_dd]:text-[15px] [&_dd]:font-semibold [&_dd]:break-words [&_dd]:text-ink [&_dt]:text-sm [&_dt]:text-ink-muted" data-test="ringkasan-dialog">
                {{ $ringkasan }}
            </dl>
        @endif

        {{-- Akibat --}}
        @if ($akibat && ! $akibat->isEmpty())
            <ul class="m-0 flex list-disc flex-col gap-1.5 ps-5 text-[15px] text-ink marker:text-ink-muted" data-test="akibat-dialog">
                {{ $akibat }}
            </ul>
        @endif

        {{-- Catatan info --}}
        @if ($catatan && ! $catatan->isEmpty())
            <p class="m-0 rounded-xl bg-primary-container px-3.5 py-3 text-sm text-on-primary-container" data-test="catatan-dialog">{{ $catatan }}</p>
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
                <flux:button type="button" variant="outline" autofocus class="h-12! w-full border-border-strong! bg-surface! text-ink! sm:h-11! sm:w-auto">Batal</flux:button>
            </flux:modal.close>

            <flux:button
                type="button"
                :variant="$variant === 'bahaya' ? 'danger' : 'primary'"
                {{ $attributes->class([
                    'h-12! w-full sm:h-11! sm:w-auto',
                    'bg-status-late! text-white! hover:bg-on-danger-container!' => $variant === 'bahaya',
                ])->merge($targetAksi !== '' ? ['wire:target' => $targetAksi] : []) }}
                wire:loading.attr="disabled"
            >{{ $labelAksi }}</flux:button>
        </div>
    </div>
</flux:modal>
