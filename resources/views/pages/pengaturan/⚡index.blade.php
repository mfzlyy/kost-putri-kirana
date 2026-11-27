<?php

use App\Models\PengaturanOperasional;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] class extends Component {
    public ?int $tanggal_jatuh_tempo = null;

    public ?int $batas_peringatan_kontrak_hari = null;

    public function mount(): void
    {
        $this->authorize('view', $this->pengaturan);

        $this->tanggal_jatuh_tempo = $this->pengaturan->tanggal_jatuh_tempo;
        $this->batas_peringatan_kontrak_hari = $this->pengaturan->batas_peringatan_kontrak_hari;
    }

    /**
     * Baris tunggal id 1 dibuat oleh seeder; halaman ini tidak membuatnya.
     */
    #[Computed]
    public function pengaturan(): PengaturanOperasional
    {
        $pengaturan = PengaturanOperasional::query()->find(PengaturanOperasional::ID_TETAP);

        if ($pengaturan === null) {
            $pesan = 'Baris pengaturan_operasional (id 1) tidak ada. Jalankan PengaturanOperasionalSeeder.';
            logger()->error($pesan);

            throw new RuntimeException($pesan);
        }

        return $pengaturan;
    }

    public function simpan(): void
    {
        $this->authorize('update', $this->pengaturan);

        $data = $this->validate([
            'tanggal_jatuh_tempo' => ['required', 'integer', 'between:1,28'],
            'batas_peringatan_kontrak_hari' => ['required', 'integer', 'between:1,365'],
        ], [
            'tanggal_jatuh_tempo.*' => 'Pilih tanggal jatuh tempo antara 1 dan 28.',
            'batas_peringatan_kontrak_hari.*' => 'Isi batas antara 1 dan 365 hari.',
        ]);

        DB::transaction(function () use ($data) {
            PengaturanOperasional::query()
                ->whereKey(PengaturanOperasional::ID_TETAP)
                ->update([
                    'tanggal_jatuh_tempo' => $data['tanggal_jatuh_tempo'],
                    'batas_peringatan_kontrak_hari' => $data['batas_peringatan_kontrak_hari'],
                    'diperbarui_oleh' => auth()->id(),
                ]);
        });

        unset($this->pengaturan);

        Flux::toast(variant: 'success', text: 'Pengaturan disimpan.');
    }
}; ?>

<section class="flex w-full max-w-[760px] flex-col gap-6">
    <x-judul-halaman judul="Pengaturan" />

    @can('update', $this->pengaturan)
        @if ($this->pengaturan->tanggal_jatuh_tempo === null)
            <flux:callout variant="warning" icon="exclamation-triangle" data-test="callout-belum-diatur">
                <flux:callout.text>Tanggal jatuh tempo belum diatur, tagihan belum bisa diterbitkan.</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="simpan" class="flex flex-col gap-[26px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
            <fieldset class="m-0 border-0 p-0">
                <legend class="mb-1 p-0 teks-label">Tanggal Jatuh Tempo</legend>
                <p id="bantuan-hari" class="mt-0 mb-2.5 teks-bantuan">Tanggal 1–28.</p>

                <div role="radiogroup" aria-describedby="bantuan-hari" class="grid grid-cols-7 gap-1.5 lg:grid-cols-[repeat(14,40px)]">
                    @foreach (range(1, 28) as $hari)
                        <div class="relative">
                            <input
                                type="radio"
                                id="hari-{{ $hari }}"
                                name="tanggal_jatuh_tempo"
                                value="{{ $hari }}"
                                wire:model="tanggal_jatuh_tempo"
                                class="peer sr-only"
                            />
                            <label
                                for="hari-{{ $hari }}"
                                class="pilihan num flex h-10 w-full cursor-pointer items-center justify-center rounded-lg border border-border bg-surface peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary teks-chip"
                            >{{ $hari }}</label>
                        </div>
                    @endforeach
                </div>

                @error('tanggal_jatuh_tempo')
                    <p class="mt-2 mb-0 teks-galat" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            <flux:field>
                <flux:label>Batas Peringatan</flux:label>
                {{-- Satuan "hari" menjadi akhiran di dalam isian, dan dibacakan sebagai keterangan isian. --}}
                <flux:input.group class="w-40">
                    <flux:input
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="365"
                        wire:model="batas_peringatan_kontrak_hari"
                        aria-describedby="satuan-batas"
                        class:input="num"
                        data-test="input-batas"
                    />
                    <flux:input.group.suffix id="satuan-batas" class="text-ink-muted teks-label">hari</flux:input.group.suffix>
                </flux:input.group>
                <flux:error name="batas_peringatan_kontrak_hari" />
            </flux:field>

            <div class="flex flex-col border-t border-divider pt-[18px] sm:flex-row">
                <x-tombol type="submit" class="max-sm:w-full" wire:loading.attr="disabled" wire:target="simpan" data-test="simpan-pengaturan">
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan</span>
                </x-tombol>
            </div>
        </form>
    @else
        <section class="grid grid-cols-1 gap-6 rounded-2xl border border-border bg-surface p-6 sm:grid-cols-2">
            <div>
                <p class="mt-0 mb-1.5 teks-bantuan">Jatuh Tempo</p>
                <p class="num m-0 teks-angka">
                    {{ $this->pengaturan->tanggal_jatuh_tempo !== null ? 'Tanggal '.$this->pengaturan->tanggal_jatuh_tempo : 'Belum diatur' }}
                </p>
                @if ($this->pengaturan->tanggal_jatuh_tempo !== null)
                    <p class="mt-1 mb-0 teks-bantuan">setiap bulan</p>
                @endif
            </div>
            <div>
                <p class="mt-0 mb-1.5 teks-bantuan">Batas Peringatan</p>
                <p class="num m-0 teks-angka">
                    {{ $this->pengaturan->batas_peringatan_kontrak_hari !== null ? $this->pengaturan->batas_peringatan_kontrak_hari.' hari' : 'Belum diatur' }}
                </p>
            </div>
        </section>
    @endcan
</section>
