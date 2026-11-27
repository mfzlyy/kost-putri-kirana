<?php

use App\Enums\StatusPenghuni;
use App\Models\Penghuni;
use App\Models\User;
use App\Support\Format;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Penghuni')] class extends Component {
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'aktif';

    public function mount(): void
    {
        $this->authorize('viewAny', Penghuni::class);

        if ($pesan = session('toast')) {
            Flux::toast(variant: 'success', text: $pesan);
        }
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Nilai filter selain keluar dan semua dianggap aktif.
     */
    #[Computed]
    public function filterAktif(): string
    {
        return in_array($this->filterStatus, ['keluar', 'semua'], true) ? $this->filterStatus : 'aktif';
    }

    #[Computed]
    public function bisaKelola(): bool
    {
        return auth()->user()->can('create', Penghuni::class);
    }

    /**
     * @return array{aktif: int, keluar: int, semua: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $jumlah = Penghuni::query()
            ->toBase()
            ->select('status_penghuni')
            ->selectRaw('count(*) as jumlah')
            ->groupBy('status_penghuni')
            ->pluck('jumlah', 'status_penghuni');

        $aktif = (int) ($jumlah[StatusPenghuni::Aktif->value] ?? 0);
        $keluar = (int) ($jumlah[StatusPenghuni::Nonaktif->value] ?? 0);

        return ['aktif' => $aktif, 'keluar' => $keluar, 'semua' => $aktif + $keluar];
    }

    /**
     * Nama dan email akun serta kamar kontrak aktif dimuat sekaligus, jadi jumlah query tetap berapa pun jumlah baris.
     */
    #[Computed]
    public function daftarPenghuni()
    {
        return Penghuni::query()
            ->with(['user:id,nama,email', 'kontrakAktif.kamar:id,nomor_kamar'])
            ->when($this->filterAktif === 'aktif', fn ($query) => $query->where('status_penghuni', StatusPenghuni::Aktif->value))
            ->when($this->filterAktif === 'keluar', fn ($query) => $query->where('status_penghuni', StatusPenghuni::Nonaktif->value))
            ->orderBy(User::query()->select('nama')->whereColumn('users.id', 'penghuni.user_id'))
            ->orderBy('penghuni.id')
            ->paginate(15);
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <x-judul-halaman judul="Penghuni">
        @if ($this->bisaKelola)
            <flux:button variant="primary" :href="route('penghuni.tambah')" wire:navigate class="tombol-utama" data-test="tambah-penghuni">
                Tambah
            </flux:button>
        @endif
    </x-judul-halaman>

    <div role="group" aria-label="Saring status" class="baris-filter -mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
        <x-chip-filter :aktif="$this->filterAktif === 'semua'" :jumlah="$this->hitungan['semua']" wire:click="$set('filterStatus', 'semua')">Semua</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'aktif'" :jumlah="$this->hitungan['aktif']" wire:click="$set('filterStatus', 'aktif')">Aktif</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'keluar'" :jumlah="$this->hitungan['keluar']" wire:click="$set('filterStatus', 'keluar')">Keluar</x-chip-filter>
    </div>

    <div wire:loading.class="opacity-60" class="transisi-opasitas flex flex-col gap-4">
    @if ($this->daftarPenghuni->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-border bg-surface px-4 py-10 text-center" data-test="pesan-kosong">
            <p class="m-0 text-ink-muted">{{ $this->hitungan['semua'] === 0 ? 'Belum ada data penghuni.' : 'Tidak ada penghuni yang sesuai filter.' }}</p>
            @if ($this->hitungan['semua'] === 0 && $this->bisaKelola)
                <flux:button variant="primary" :href="route('penghuni.tambah')" wire:navigate class="tombol-utama">Tambah</flux:button>
            @endif
        </div>
    @else
        {{-- Desktop: tabel dalam kartu. Nomor identitas selalu tersamar di daftar, untuk semua peran.
             relative menahan teks sr-only di kepala tabel tetap di dalam area gulir; nama dan email boleh turun baris. --}}
        <div class="relative hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
            <table class="w-full min-w-[860px] border-collapse">
                <thead>
                    <tr>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Nama Lengkap</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Kamar</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Telepon</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Nomor Identitas</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Email</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Status</th>
                        @if ($this->bisaKelola)
                            <th scope="col" class="border-b border-border px-5 py-3.5 text-end whitespace-nowrap teks-kolom"><span class="sr-only">Aksi</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarPenghuni as $penghuni)
                        @php($nomorKamar = $penghuni->kontrakAktif?->kamar?->nomor_kamar)
                        <tr wire:key="penghuni-tabel-{{ $penghuni->id }}" class="h-[60px] border-t border-row-divider first:border-t-0">
                            <td class="px-5 font-semibold">{{ $penghuni->user?->nama }}</td>
                            <td class="num px-5 {{ $nomorKamar ? '' : 'text-ink-muted' }}">{{ $nomorKamar ?? '-' }}</td>
                            <td class="num px-5 whitespace-nowrap">{{ Format::telepon($penghuni->no_telepon) }}</td>
                            <td class="num px-5 tracking-[0.02em] whitespace-nowrap">{{ $penghuni->no_identitas_tersamar }}</td>
                            @php([$namaEmail, $domainEmail] = array_pad(explode('@', (string) $penghuni->user?->email, 2), 2, ''))
                            {{-- Email patah di "@" bila kolom sempit; potongan di tengah kata hanya bila satu bagian tetap tidak muat. --}}
                            <td class="px-5 text-ink-muted"><span class="block min-w-40 [overflow-wrap:anywhere]">{{ $namaEmail }}<wbr>{{ '@'.$domainEmail }}</span></td>
                            <td class="px-5">
                                @if ($penghuni->status_penghuni === StatusPenghuni::Aktif)
                                    <x-status jenis="ok">Aktif</x-status>
                                @else
                                    <x-status jenis="belum">Nonaktif</x-status>
                                @endif
                            </td>
                            @if ($this->bisaKelola)
                                <td class="px-5 text-end whitespace-nowrap">
                                    <a href="{{ route('penghuni.ubah', $penghuni) }}" wire:navigate class="tautan-aksi inline-flex min-h-10 items-center px-2 teks-tombol-kecil">Ubah</a>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Ponsel: daftar kartu --}}
        <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 md:hidden">
            @foreach ($this->daftarPenghuni as $penghuni)
                @php($nomorKamar = $penghuni->kontrakAktif?->kamar?->nomor_kamar)
                <li wire:key="penghuni-kartu-{{ $penghuni->id }}" class="flex flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="truncate font-[650]">{{ $penghuni->user?->nama }}</span>
                            @if ($penghuni->status_penghuni !== StatusPenghuni::Aktif)
                                <x-status jenis="belum">Nonaktif</x-status>
                            @endif
                        </span>
                        <span class="shrink-0 teks-badan {{ $nomorKamar ? '' : 'text-ink-muted' }}">
                            @if ($nomorKamar)
                                Kamar <strong class="num">{{ $nomorKamar }}</strong>
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3 teks-bantuan">
                        <span class="num">{{ Format::telepon($penghuni->no_telepon) }}</span>
                        <span class="num tracking-[0.02em]">{{ $penghuni->no_identitas_tersamar }}</span>
                    </div>
                    @if ($this->bisaKelola)
                        <a href="{{ route('penghuni.ubah', $penghuni) }}" wire:navigate class="tautan-aksi inline-flex min-h-10 w-fit items-center px-1 teks-tombol-kecil">Ubah</a>
                    @endif
                </li>
            @endforeach
        </ul>

        {{ $this->daftarPenghuni->links() }}
    @endif
    </div>

</section>
