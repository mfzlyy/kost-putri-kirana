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

        // Toast dibawa lewat session flash karena halaman daftar dimuat ulang oleh redirect.
        session()->flash('toast', "Kamar {$this->nomor_kamar} disimpan.");

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

        session()->flash('toast', "Kamar {$kamar->nomor_kamar} dihapus.");

        $this->redirectRoute('kamar.index', navigate: true);
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="{{ route('kamar.index') }}" wire:navigate class="tautan-aksi w-fit">Kamar</a>
        <x-judul-halaman :judul="$this->kamar ? 'Ubah Kamar '.$this->kamar->nomor_kamar : 'Tambah Kamar'" />
    </div>

    <div @class(['flex flex-wrap items-start gap-6', 'max-w-[760px]' => ! $this->kamar])>
        <form wire:submit="simpan" class="flex flex-[2_1_460px] flex-col gap-[18px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nomor Kamar</flux:label>
                    <flux:input wire:model="nomor_kamar" maxlength="10" autocomplete="off" required data-test="input-nomor-kamar" />
                    <flux:description>Harus unik.</flux:description>
                    <flux:error name="nomor_kamar" />
                </flux:field>

                <flux:field>
                    <flux:label>Tarif</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix class="text-ink-muted teks-label">Rp</flux:input.group.prefix>
                        <flux:input
                            wire:model="tarif_bulanan"
                            inputmode="numeric"
                            autocomplete="off"
                            required
                            class:input="num"
                            x-data
                            x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                            data-test="input-tarif"
                        />
                    </flux:input.group>
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
                <p class="m-0 rounded-xl bg-primary-container px-3.5 py-3 text-on-primary-container teks-badan" data-test="catatan-kontrak">
                    Kontrak {{ $kontrak->penghuni?->user?->nama }} tetap {{ Format::rupiah($kontrak->tarif_bulanan) }} sampai {{ $kontrak->tanggal_berakhir->locale('id')->translatedFormat('j F Y') }}.
                </p>
            @endif

            <div class="flex flex-col gap-2 border-t border-divider pt-[18px] sm:flex-row">
                <x-tombol type="submit" class="max-sm:w-full" wire:loading.attr="disabled" wire:target="simpan" data-test="simpan-kamar">
                    Simpan
                </x-tombol>
                <x-tombol varian="sekunder" :href="route('kamar.index')" wire:navigate>Batal</x-tombol>
            </div>
        </form>

        @if ($this->kamar)
            <aside class="flex flex-[1_1_260px] flex-col gap-2.5 rounded-2xl border border-dashed border-border-strong p-5">
                <h2 class="m-0 teks-bagian">Hapus</h2>

                @if ($this->izinHapus->denied())
                    <p class="m-0 teks-bantuan" data-test="alasan-tidak-bisa-hapus">{{ $this->izinHapus->message() }}</p>
                    <x-tombol varian="bahaya" class="w-fit max-sm:w-full" disabled :title="str_contains($this->izinHapus->message(), 'fasilitas') ? 'Masih punya fasilitas' : 'Masih punya kontrak'">Hapus</x-tombol>
                @else
                    <flux:modal.trigger name="hapus-kamar">
                        <x-tombol varian="bahaya" class="w-fit max-sm:w-full" data-test="buka-hapus-kamar">Hapus</x-tombol>
                    </flux:modal.trigger>
                @endif
            </aside>

            <x-dialog-konfirmasi
                name="hapus-kamar"
                :judul="'Hapus Kamar '.$this->kamar->nomor_kamar.'?'"
                label-aksi="Hapus"
                wire:click="hapus"
                data-test="konfirmasi-hapus-kamar"
            >
                <x-slot:ringkasan>
                    <dt>Tarif</dt>
                    <dd>{{ Format::rupiah($this->kamar->tarif_bulanan) }}</dd>
                    <dt>Keterangan</dt>
                    <dd>{{ filled($this->kamar->keterangan) ? $this->kamar->keterangan : '-' }}</dd>
                </x-slot:ringkasan>

                <x-slot:akibat>Kamar ini akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.</x-slot:akibat>
            </x-dialog-konfirmasi>
        @endif
    </div>
</section>
