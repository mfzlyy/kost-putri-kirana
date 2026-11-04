<?php

use App\Models\Kamar;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kamar')] class extends Component {
    #[Url(as: 'status')]
    public string $filterStatus = '';

    public ?int $idHapus = null;

    public string $nomorHapus = '';

    public ?string $galatHapus = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Kamar::class);

        if ($pesan = session('toast')) {
            Flux::toast(variant: 'success', text: $pesan);
        }
    }

    /**
     * Nilai filter selain terisi dan kosong dianggap Semua.
     */
    #[Computed]
    public function filterAktif(): string
    {
        return in_array($this->filterStatus, [Kamar::HUNIAN_TERISI, Kamar::HUNIAN_KOSONG], true)
            ? $this->filterStatus
            : '';
    }

    #[Computed]
    public function bisaKelola(): bool
    {
        return auth()->user()->can('create', Kamar::class);
    }

    /**
     * Penghuni aktif dimuat sekaligus (kontrak, penghuni, user), jadi jumlah query tetap berapa pun jumlah kamar.
     */
    #[Computed]
    public function daftarKamar()
    {
        return Kamar::query()
            ->with('kontrakAktif.penghuni.user')
            ->withCount(['kontrak', 'fasilitas'])
            ->when($this->filterAktif === Kamar::HUNIAN_TERISI, fn ($query) => $query->terisi())
            ->when($this->filterAktif === Kamar::HUNIAN_KOSONG, fn ($query) => $query->kosong())
            ->orderBy('nomor_kamar')
            ->get();
    }

    /**
     * @return array{semua: int, terisi: int, kosong: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $semua = Kamar::query()->count();
        $terisi = Kamar::query()->terisi()->count();

        return ['semua' => $semua, 'terisi' => $terisi, 'kosong' => $semua - $terisi];
    }

    /**
     * Pemilik atau peran lain ditolak (403). Pengelola yang kamarnya belum boleh dihapus mendapat pesan Policy.
     */
    private function izinkanHapus(Kamar $kamar): bool
    {
        $respons = Gate::inspect('delete', $kamar);

        if ($respons->denied()) {
            $this->authorize('update', $kamar);

            $this->galatHapus = $respons->message();

            return false;
        }

        return true;
    }

    public function konfirmasiHapus(int $id): void
    {
        $kamar = Kamar::query()->withCount(['kontrak', 'fasilitas'])->findOrFail($id);

        $this->galatHapus = null;

        if (! $this->izinkanHapus($kamar)) {
            return;
        }

        $this->idHapus = $kamar->id;
        $this->nomorHapus = $kamar->nomor_kamar;

        Flux::modal('hapus-kamar')->show();
    }

    public function hapus(int $id): void
    {
        $kamar = Kamar::query()->withCount(['kontrak', 'fasilitas'])->findOrFail($id);

        $this->galatHapus = null;

        if (! $this->izinkanHapus($kamar)) {
            Flux::modal('hapus-kamar')->close();

            return;
        }

        DB::transaction(fn () => $kamar->delete());

        $this->reset('idHapus', 'nomorHapus');
        unset($this->daftarKamar, $this->hitungan);

        Flux::modal('hapus-kamar')->close();
        Flux::toast(variant: 'success', text: "Kamar {$kamar->nomor_kamar} dihapus.");
    }
}; ?>

<section class="flex w-full flex-col gap-4 lg:gap-6">
    <x-judul-halaman
        judul="Kamar"
        :deskripsi="$this->hitungan['semua'].' kamar, '.$this->hitungan['terisi'].' terisi. Status mengikuti kontrak aktif dan tidak bisa diubah manual.'"
    >
        @if ($this->bisaKelola)
            <flux:button variant="primary" :href="route('kamar.tambah')" wire:navigate class="h-12! w-full sm:h-11! sm:w-auto" data-test="tambah-kamar">
                Tambah kamar
            </flux:button>
        @endif
    </x-judul-halaman>

    @if ($galatHapus)
        <flux:callout variant="danger" icon="x-circle" data-test="galat-hapus">
            <flux:callout.text>{{ $galatHapus }}</flux:callout.text>
        </flux:callout>
    @endif

    <div role="group" aria-label="Saring status" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
        <x-chip-filter :aktif="$this->filterAktif === ''" :jumlah="$this->hitungan['semua']" wire:click="$set('filterStatus', '')">Semua</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'terisi'" :jumlah="$this->hitungan['terisi']" wire:click="$set('filterStatus', 'terisi')">Terisi</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'kosong'" :jumlah="$this->hitungan['kosong']" wire:click="$set('filterStatus', 'kosong')">Kosong</x-chip-filter>
    </div>

    <div wire:loading.class="opacity-60" class="transisi-opasitas flex flex-col gap-4 lg:gap-6">
    @if ($this->daftarKamar->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-border bg-surface px-4 py-10 text-center" data-test="pesan-kosong">
            <p class="m-0 text-ink-muted">{{ $this->hitungan['semua'] === 0 ? 'Belum ada kamar.' : 'Tidak ada kamar yang sesuai filter.' }}</p>
            @if ($this->hitungan['semua'] === 0 && $this->bisaKelola)
                <flux:button variant="primary" :href="route('kamar.tambah')" wire:navigate class="h-11!">Tambah kamar</flux:button>
            @endif
        </div>
    @else
        {{-- Desktop: tabel dalam kartu --}}
        <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
            <table class="w-full min-w-[720px] border-collapse">
                <thead>
                    <tr>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Kamar</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Penghuni aktif</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Keterangan</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-end text-[13px] font-[550] whitespace-nowrap text-ink-muted">Tarif per bulan</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Status</th>
                        @if ($this->bisaKelola)
                            <th scope="col" class="border-b border-border px-5 py-3.5 text-end text-[13px] font-[550] whitespace-nowrap text-ink-muted"><span class="sr-only">Aksi</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarKamar as $kamar)
                        @php($namaPenghuni = $kamar->kontrakAktif?->penghuni?->user?->nama)
                        <tr wire:key="kamar-tabel-{{ $kamar->id }}" class="h-[60px] border-t border-row-divider first:border-t-0">
                            <td class="num px-5 text-[22px] font-[750] font-stretch-expanded {{ $namaPenghuni ? '' : 'text-ink-muted' }}">{{ $kamar->nomor_kamar }}</td>
                            <td class="px-5 {{ $namaPenghuni ? '' : 'text-ink-muted' }}">{{ $namaPenghuni ?? 'Belum ada' }}</td>
                            <td class="px-5 text-ink-muted">{{ $kamar->keterangan }}</td>
                            <td class="num px-5 text-end font-semibold whitespace-nowrap">{{ \App\Support\Format::rupiah($kamar->tarif_bulanan) }}</td>
                            <td class="px-5">
                                @if ($namaPenghuni !== null)
                                    <x-status jenis="ok">Terisi</x-status>
                                @else
                                    <x-status jenis="kosong">Kosong</x-status>
                                @endif
                            </td>
                            @if ($this->bisaKelola)
                                <td class="px-5 text-end whitespace-nowrap">
                                    <a href="{{ route('kamar.ubah', $kamar) }}" wire:navigate class="inline-flex min-h-10 items-center px-2 text-sm font-semibold no-underline">Ubah</a>
                                    @can('delete', $kamar)
                                        <button type="button" class="min-h-10 cursor-pointer px-2 text-sm font-semibold text-danger" wire:click="konfirmasiHapus({{ $kamar->id }})">Hapus</button>
                                    @endcan
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Ponsel: daftar kartu --}}
        <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 md:hidden">
            @foreach ($this->daftarKamar as $kamar)
                @php($namaPenghuni = $kamar->kontrakAktif?->penghuni?->user?->nama)
                <li wire:key="kamar-kartu-{{ $kamar->id }}" class="flex flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-baseline gap-2.5">
                            <span class="num text-xl leading-none font-[750] font-stretch-expanded {{ $namaPenghuni ? '' : 'text-ink-muted' }}">{{ $kamar->nomor_kamar }}</span>
                            <span class="truncate font-semibold {{ $namaPenghuni ? '' : 'text-ink-muted' }}">{{ $namaPenghuni ?? 'Belum ada penghuni' }}</span>
                        </span>
                        @if ($namaPenghuni !== null)
                            <x-status jenis="ok">Terisi</x-status>
                        @else
                            <x-status jenis="kosong">Kosong</x-status>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-ink-muted">{{ $kamar->keterangan }}</span>
                        <span class="num shrink-0 font-semibold">{{ \App\Support\Format::rupiah($kamar->tarif_bulanan) }}</span>
                    </div>
                    @if ($this->bisaKelola)
                        <div class="mt-0.5 flex gap-2">
                            <a href="{{ route('kamar.ubah', $kamar) }}" wire:navigate class="inline-flex min-h-10 items-center px-1 text-sm font-semibold no-underline">Ubah</a>
                            @can('delete', $kamar)
                                <button type="button" class="min-h-10 cursor-pointer px-1 text-sm font-semibold text-danger" wire:click="konfirmasiHapus({{ $kamar->id }})">Hapus</button>
                            @endcan
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    </div>

    <p class="m-0 text-sm text-ink-muted">Kamar yang masih punya kontrak tidak bisa dihapus. Mengubah tarif tidak mengubah tarif kontrak yang sedang berjalan.</p>

    @if ($this->bisaKelola)
        <flux:modal name="hapus-kamar" class="w-full max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Hapus Kamar {{ $nomorHapus }}?</flux:heading>
                    <flux:subheading>Kamar ini akan dihapus permanen.</flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="text-primary! font-semibold">Batal</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" wire:click="hapus({{ (int) $idHapus }})" wire:loading.attr="disabled" wire:target="hapus" data-test="konfirmasi-hapus-kamar">
                        Hapus kamar
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</section>
