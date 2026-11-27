{{--
    Pemilih periode (docs/design/DESIGN.md, bagian Components): [‹] [ November 2026 ] [›]. Tombol tengah membuka daftar
    bulan. Lebar label tetap, jadi kontrol tidak bergeser saat bulan diganti; di ponsel selebar baris.

      terpilih   : ['nilai' => 'YYYY-MM', 'label' => 'November 2026']
      daftar     : daftar pilihan bulan berbentuk sama, terbaru di atas
      sebelumnya : nilai bulan sebelumnya; null berarti panah ‹ nonaktif
      berikutnya : nilai bulan berikutnya; null berarti panah › nonaktif
      aksi       : metode Livewire yang menerima nilai
--}}
@props([
    'terpilih',
    'daftar',
    'sebelumnya' => null,
    'berikutnya' => null,
    'aksi' => 'pilihPeriode',
])

<div role="group" aria-label="Periode" {{ $attributes->class('flex items-center gap-2 max-sm:w-full') }}>
    {{-- wire:target tanpa argumen: nilai di wire:click berganti tiap bulan, penanda memuat harus tetap cocok. --}}
    <x-tombol
        varian="sekunder"
        ukuran="persegi"
        wire:click="{{ $aksi }}('{{ $sebelumnya }}')"
        wire:loading.attr="data-sibuk"
        wire:target="{{ $aksi }}"
        :disabled="$sebelumnya === null"
        aria-label="Bulan sebelumnya"
        data-test="periode-sebelumnya"
    ><flux:icon.chevron-left variant="mini" class="size-5" /></x-tombol>

    <flux:dropdown position="bottom" align="center" class="min-w-0 max-sm:flex-1">
        <x-tombol
            varian="sekunder"
            ukuran="sedang"
            class="pemilih-periode-label"
            aria-label="Pilih bulan, {{ $terpilih['label'] }}"
            data-test="periode-label"
        >{{ $terpilih['label'] }}</x-tombol>

        <flux:menu class="max-h-72 overflow-y-auto" data-test="daftar-periode">
            @foreach ($daftar as $bulan)
                @php($dipilih = $bulan['nilai'] === $terpilih['nilai'])
                <flux:menu.item
                    wire:key="periode-{{ $bulan['nilai'] }}-{{ $dipilih ? 'dipilih' : 'lain' }}"
                    wire:click="{{ $aksi }}('{{ $bulan['nilai'] }}')"
                    :icon="$dipilih ? 'check' : null"
                    :aria-current="$dipilih ? 'true' : null"
                    class="num"
                    data-test="opsi-periode"
                    data-periode="{{ $bulan['nilai'] }}"
                >{{ $bulan['label'] }}</flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>

    <x-tombol
        varian="sekunder"
        ukuran="persegi"
        wire:click="{{ $aksi }}('{{ $berikutnya }}')"
        wire:loading.attr="data-sibuk"
        wire:target="{{ $aksi }}"
        :disabled="$berikutnya === null"
        aria-label="Bulan berikutnya"
        data-test="periode-berikutnya"
    ><flux:icon.chevron-right variant="mini" class="size-5" /></x-tombol>
</div>
