<?php

use App\Models\Kamar;
use App\Support\Format;
use Flux\Flux;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kamar')] class extends Component {
    #[Locked]
    public ?int $kamarId = null;

    public string $nomor_kamar = '';

    public string $tarif_bulanan = '';

    public string $keterangan = '';

    public function mount(?Kamar $kamar = null): void
    {
        if ($kamar === null || ! $kamar->exists) {
            $this->authorize('create', Kamar::class);

            return;
        }

        $this->authorize('update', $kamar);

        $this->kamarId = $kamar->id;
        $this->nomor_kamar = $kamar->nomor_kamar;
        $this->tarif_bulanan = number_format((int) $kamar->tarif_bulanan, 0, ',', '.');
        $this->keterangan = (string) $kamar->keterangan;
    }

    #[Computed]
    public function kamar(): ?Kamar
    {
        if ($this->kamarId === null) {
            return null;
        }

        return Kamar::query()
            ->with('kontrakAktif.penghuni.user')
            ->withCount(['kontrak', 'fasilitas'])
            ->findOrFail($this->kamarId);
    }

    /**
     * Hasil Policy delete untuk kamar yang sedang diubah; pesannya ditampilkan bila ditolak.
     */
    #[Computed]
    public function izinHapus(): ?Response
    {
        return $this->kamar === null ? null : Gate::inspect('delete', $this->kamar);
    }

    public function simpan(): void
    {
        if ($this->kamarId === null) {
            $this->authorize('create', Kamar::class);
        } else {
            $this->authorize('update', Kamar::query()->findOrFail($this->kamarId));
        }

        $this->nomor_kamar = trim($this->nomor_kamar);
        $this->keterangan = trim($this->keterangan);

        $this->validate([
            'nomor_kamar' => ['required', 'string', 'max:10', Rule::unique('kamar', 'nomor_kamar')->ignore($this->kamarId)],
            'tarif_bulanan' => [
                'required',
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    $tarif = Format::parseRupiah((string) $nilai);

                    if ($tarif === null || $tarif < 1 || $tarif > 9999999999) {
                        $gagal('Isi tarif dalam rupiah, lebih dari 0.');
                    }
                },
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'nomor_kamar.required' => 'Isi nomor kamar.',
            'nomor_kamar.max' => 'Nomor kamar paling banyak 10 karakter.',
            'nomor_kamar.unique' => 'Nomor kamar :input sudah dipakai.',
            'tarif_bulanan.required' => 'Isi tarif dalam rupiah, lebih dari 0.',
            'keterangan.max' => 'Keterangan paling banyak 255 karakter.',
        ]);

        // Hanya tabel kamar yang berubah; tarif pada kontrak tidak disentuh (F-05 butir 3).
        $nilai = [
            'nomor_kamar' => $this->nomor_kamar,
            'tarif_bulanan' => Format::parseRupiah($this->tarif_bulanan),
            'keterangan' => $this->keterangan !== '' ? $this->keterangan : null,
        ];

        DB::transaction(function () use ($nilai) {
            if ($this->kamarId === null) {
                Kamar::query()->create($nilai);
            } else {
                Kamar::query()->findOrFail($this->kamarId)->update($nilai);
            }
        });

        Flux::toast(variant: 'success', text: "Kamar {$this->nomor_kamar} disimpan.");

        $this->redirectRoute('kamar.index', navigate: true);
    }

    public function hapus(): void
    {
        abort_if($this->kamarId === null, 404);

        $kamar = Kamar::query()->withCount(['kontrak', 'fasilitas'])->findOrFail($this->kamarId);

        $respons = Gate::inspect('delete', $kamar);

        if ($respons->denied()) {
            $this->authorize('update', $kamar);

            Flux::modal('hapus-kamar')->close();

            return;
        }

        DB::transaction(fn () => $kamar->delete());

        Flux::toast(variant: 'success', text: "Kamar {$kamar->nomor_kamar} dihapus.");

        $this->redirectRoute('kamar.index', navigate: true);
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-2.5">
        <a href="{{ route('kamar.index') }}" wire:navigate class="w-fit font-semibold no-underline">Kamar</a>
        <x-judul-halaman :judul="$this->kamar ? 'Ubah Kamar '.$this->kamar->nomor_kamar : 'Tambah kamar'" />
    </div>

    <div class="flex flex-wrap items-start gap-6">
        <form wire:submit="simpan" class="flex flex-[2_1_460px] flex-col gap-[18px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nomor kamar</flux:label>
                    <flux:input wire:model="nomor_kamar" maxlength="10" autocomplete="off" required data-test="input-nomor-kamar" />
                    <flux:description>Harus unik.</flux:description>
                    <flux:error name="nomor_kamar" />
                </flux:field>

                <flux:field>
                    <flux:label>Tarif per bulan</flux:label>
                    <flux:input
                        wire:model="tarif_bulanan"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                        class:input="num ps-1"
                        x-data
                        x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                        data-test="input-tarif"
                    >
                        <x-slot name="iconLeading">
                            <span aria-hidden="true" class="ps-3 text-sm font-semibold text-ink-muted">Rp</span>
                        </x-slot>
                    </flux:input>
                    <flux:error name="tarif_bulanan" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Keterangan</flux:label>
                <flux:input wire:model="keterangan" maxlength="255" autocomplete="off" data-test="input-keterangan" />
                <flux:description>Opsional.</flux:description>
                <flux:error name="keterangan" />
            </flux:field>

            @if ($this->kamar?->kontrakAktif)
                @php($kontrak = $this->kamar->kontrakAktif)
                <p class="m-0 rounded-xl bg-primary-container px-3.5 py-3 text-sm text-on-primary-container" data-test="catatan-kontrak">
                    Tarif baru berlaku untuk kontrak berikutnya. Kontrak {{ $kontrak->penghuni?->user?->nama }} tetap {{ Format::rupiah($kontrak->tarif_bulanan) }} sampai {{ $kontrak->tanggal_berakhir->locale('id')->translatedFormat('j F Y') }}.
                </p>
            @endif

            <div class="flex flex-col gap-2 border-t border-divider pt-[18px] sm:flex-row">
                <flux:button type="submit" variant="primary" class="h-11! w-full sm:w-auto" wire:loading.attr="disabled" wire:target="simpan" data-test="simpan-kamar">
                    {{ $this->kamar ? 'Simpan perubahan' : 'Simpan kamar' }}
                </flux:button>
                <flux:button variant="ghost" :href="route('kamar.index')" wire:navigate class="h-11!">Batal</flux:button>
            </div>
        </form>

        @if ($this->kamar)
            <aside class="flex flex-[1_1_260px] flex-col gap-2.5 rounded-2xl border border-dashed border-border-strong p-5">
                <h2 class="m-0 text-base font-[650]">Hapus kamar</h2>

                @if ($this->izinHapus->denied())
                    <p class="m-0 text-sm text-ink-muted" data-test="alasan-tidak-bisa-hapus">{{ $this->izinHapus->message() }}</p>
                    <button type="button" disabled class="h-10 w-fit cursor-not-allowed rounded-lg border border-border bg-background px-3.5 text-sm font-semibold text-[#8A918F]">Hapus kamar</button>
                @else
                    <p class="m-0 text-sm text-ink-muted">Kamar ini belum punya kontrak maupun fasilitas, jadi bisa dihapus.</p>
                    <flux:modal.trigger name="hapus-kamar">
                        <button type="button" class="h-10 w-fit cursor-pointer rounded-lg border border-[#E3B4AD] bg-surface px-3.5 text-sm font-semibold text-danger" data-test="buka-hapus-kamar">Hapus kamar</button>
                    </flux:modal.trigger>
                @endif
            </aside>

            <flux:modal name="hapus-kamar" class="w-full max-w-md">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Hapus Kamar {{ $this->kamar->nomor_kamar }}?</flux:heading>
                        <flux:subheading>Kamar ini akan dihapus permanen.</flux:subheading>
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">Batal</flux:button>
                        </flux:modal.close>

                        <flux:button variant="danger" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus" data-test="konfirmasi-hapus-kamar">
                            Hapus kamar
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </div>
</section>
