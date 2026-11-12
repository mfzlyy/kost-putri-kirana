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
        $pengaturan = PengaturanOperasional::query()
            ->with('diperbaruiOleh:id,nama')
            ->find(PengaturanOperasional::ID_TETAP);

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

<section class="flex w-full max-w-[760px] flex-col gap-4 lg:gap-6">
    <x-judul-halaman judul="Pengaturan" />

    @can('update', $this->pengaturan)
        @if ($this->pengaturan->tanggal_jatuh_tempo === null)
            <flux:callout variant="warning" icon="exclamation-triangle" data-test="callout-belum-diatur">
                <flux:callout.text>Tanggal jatuh tempo belum diatur, tagihan belum bisa diterbitkan.</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="simpan" class="flex flex-col gap-[26px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
            <fieldset class="m-0 border-0 p-0">
                <legend class="mb-1 p-0 text-[15px] font-semibold">Tanggal Jatuh Tempo</legend>
                <p id="bantuan-hari" class="mt-0 mb-2.5 text-sm text-ink-muted">Tanggal 1 sampai 28, setiap bulan.</p>

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
                                class="num flex h-10 w-full cursor-pointer items-center justify-center rounded-lg border border-border bg-surface text-sm font-semibold peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary"
                            >{{ $hari }}</label>
                        </div>
                    @endforeach
                </div>

                @error('tanggal_jatuh_tempo')
                    <p class="mt-2 mb-0 text-[13px] text-danger" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            <div>
                <label for="batas-peringatan" class="mb-1 block text-[15px] font-semibold">Batas Peringatan</label>
                <p id="bantuan-batas" class="mt-0 mb-2 text-sm text-ink-muted">Jumlah hari sebelum kontrak berakhir.</p>
                <div class="flex items-center gap-2.5">
                    <input
                        id="batas-peringatan"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="365"
                        wire:model="batas_peringatan_kontrak_hari"
                        aria-describedby="bantuan-batas"
                        @if ($errors->has('batas_peringatan_kontrak_hari')) aria-invalid="true" @endif
                        class="num h-[46px] w-28 rounded-lg border bg-surface px-3 text-base {{ $errors->has('batas_peringatan_kontrak_hari') ? 'border-[#B3261E]' : 'border-border-strong' }}"
                    />
                    <span>hari</span>
                </div>
                @error('batas_peringatan_kontrak_hari')
                    <p class="mt-2 mb-0 text-[13px] text-danger" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-3 border-t border-divider pt-[18px] sm:flex-row sm:items-center sm:gap-4">
                <flux:button type="submit" variant="primary" class="h-12! w-full sm:h-11! sm:w-auto" wire:loading.attr="disabled" wire:target="simpan" data-test="simpan-pengaturan">
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan</span>
                </flux:button>

                @if ($this->pengaturan->diperbaruiOleh)
                    <span class="text-sm text-ink-muted max-sm:text-center">Terakhir diubah oleh {{ $this->pengaturan->diperbaruiOleh->nama }}</span>
                @endif
            </div>
        </form>
    @else
        <section class="grid grid-cols-1 gap-6 rounded-2xl border border-border bg-surface p-6 sm:grid-cols-2">
            <div>
                <p class="mt-0 mb-1.5 text-sm text-ink-muted">Jatuh Tempo</p>
                <p class="num m-0 text-2xl leading-[1.1] font-[750] font-stretch-expanded sm:text-[28px]">
                    {{ $this->pengaturan->tanggal_jatuh_tempo !== null ? 'Tanggal '.$this->pengaturan->tanggal_jatuh_tempo : 'Belum diatur' }}
                </p>
                @if ($this->pengaturan->tanggal_jatuh_tempo !== null)
                    <p class="mt-1 mb-0 text-sm text-ink-muted">setiap bulan</p>
                @endif
            </div>
            <div>
                <p class="mt-0 mb-1.5 text-sm text-ink-muted">Batas Peringatan</p>
                <p class="num m-0 text-2xl leading-[1.1] font-[750] font-stretch-expanded sm:text-[28px]">
                    {{ $this->pengaturan->batas_peringatan_kontrak_hari !== null ? $this->pengaturan->batas_peringatan_kontrak_hari.' hari' : 'Belum diatur' }}
                </p>
            </div>
        </section>
    @endcan
</section>
