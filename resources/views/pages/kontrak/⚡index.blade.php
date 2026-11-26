<?php

use App\Enums\StatusKontrak;
use App\Exceptions\KontrakSudahBerakhir;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kontrak')] class extends Component {
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'aktif';

    /** Kontrak yang sedang akan diakhiri (modal). Hanya diisi lewat konfirmasiAkhiri. */
    #[Locked]
    public ?int $idAkhiri = null;

    public string $tanggal_keluar = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Kontrak::class);

        if ($pesan = session('toast')) {
            Flux::toast(variant: 'success', text: $pesan);
        }
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Nilai filter selain berakhir dan semua dianggap aktif.
     */
    #[Computed]
    public function filterAktif(): string
    {
        return in_array($this->filterStatus, ['berakhir', 'semua'], true) ? $this->filterStatus : 'aktif';
    }

    #[Computed]
    public function bisaKelola(): bool
    {
        return auth()->user()->can('create', Kontrak::class);
    }

    /**
     * @return array{aktif: int, berakhir: int, semua: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $jumlah = Kontrak::query()
            ->toBase()
            ->select('status_kontrak')
            ->selectRaw('count(*) as jumlah')
            ->groupBy('status_kontrak')
            ->pluck('jumlah', 'status_kontrak');

        $aktif = (int) ($jumlah[StatusKontrak::Aktif->value] ?? 0);
        $berakhir = (int) ($jumlah[StatusKontrak::Berakhir->value] ?? 0);

        return ['aktif' => $aktif, 'berakhir' => $berakhir, 'semua' => $aktif + $berakhir];
    }

    /**
     * Batas hari peringatan dibaca dari pengaturan operasional; null bila belum diatur (ISU-02).
     */
    #[Computed]
    public function batasPeringatan(): ?int
    {
        return PengaturanOperasional::aktif()->batas_peringatan_kontrak_hari;
    }

    /**
     * Kontrak aktif yang berakhir dalam batas peringatan (F-08), termasuk yang tanggalnya sudah lewat
     * tetapi belum diakhiri otomatis.
     */
    #[Computed]
    public function akanBerakhir()
    {
        if ($this->batasPeringatan === null) {
            return collect();
        }

        return Kontrak::query()
            ->perluPeringatan($this->batasPeringatan)
            ->with(['penghuni:id,user_id', 'penghuni.user:id,nama', 'kamar:id,nomor_kamar'])
            ->orderBy('kontrak.tanggal_berakhir')
            ->orderBy('kontrak.id')
            ->get();
    }

    /**
     * Aktif menurut nomor kamar, berakhir menurut tanggal berakhir menurun; Semua = aktif dulu lalu berakhir.
     * Penghuni, akun, dan kamar dimuat sekaligus, jadi jumlah query tetap berapa pun jumlah baris.
     */
    #[Computed]
    public function daftarKontrak()
    {
        return Kontrak::query()
            ->with(['penghuni:id,user_id', 'penghuni.user:id,nama', 'kamar:id,nomor_kamar'])
            ->when($this->filterAktif === 'aktif', fn ($query) => $query->aktif())
            ->when($this->filterAktif === 'berakhir', fn ($query) => $query->where('kontrak.status_kontrak', StatusKontrak::Berakhir->value))
            ->orderByRaw("case when kontrak.status_kontrak = 'aktif' then 0 else 1 end")
            ->orderByRaw("case when kontrak.status_kontrak = 'aktif' then (select k.nomor_kamar from kamar k where k.id = kontrak.kamar_id) end")
            ->orderByDesc('kontrak.tanggal_berakhir')
            ->orderBy('kontrak.id')
            ->paginate(15);
    }

    /**
     * Kontrak untuk modal Akhiri, dimuat ulang dari ID tersimpan.
     */
    #[Computed]
    public function kontrakAkhiri(): ?Kontrak
    {
        if ($this->idAkhiri === null) {
            return null;
        }

        return Kontrak::query()
            ->with(['penghuni:id,user_id', 'penghuni.user:id,nama', 'kamar:id,nomor_kamar'])
            ->find($this->idAkhiri);
    }

    /**
     * Pemanggil sudah lolos authorize('create') (hanya pengelola). Kontrak yang sudah berakhir mendapat toast dari Policy.
     */
    private function izinkanAkhiri(Kontrak $kontrak): bool
    {
        $respons = Gate::inspect('akhiri', $kontrak);

        if ($respons->denied()) {
            Flux::modal('akhiri-kontrak')->close();
            Flux::toast(variant: 'danger', text: $respons->message());
            $this->refreshDaftar();

            return false;
        }

        return true;
    }

    private function refreshDaftar(): void
    {
        unset($this->daftarKontrak, $this->hitungan, $this->akanBerakhir, $this->kontrakAkhiri);
    }

    public function konfirmasiAkhiri(int $id): void
    {
        $this->authorize('create', Kontrak::class);

        $kontrak = Kontrak::query()->findOrFail($id);

        $this->resetErrorBag();

        if (! $this->izinkanAkhiri($kontrak)) {
            return;
        }

        $this->idAkhiri = $kontrak->id;
        unset($this->kontrakAkhiri);

        // Bawaan: hari ini, atau tanggal berakhir bila sudah lewat.
        $this->tanggal_keluar = min(Kontrak::hariIni()->toDateString(), $kontrak->tanggal_berakhir->toDateString());

        Flux::modal('akhiri-kontrak')->show();
    }

    public function akhiri(): void
    {
        $this->authorize('create', Kontrak::class);

        abort_if($this->idAkhiri === null, 404);

        $kontrak = Kontrak::query()->with('kamar:id,nomor_kamar')->findOrFail($this->idAkhiri);

        if (! $this->izinkanAkhiri($kontrak)) {
            return;
        }

        $tanggalKeluar = null;

        if ($kontrak->sudahBerjalan()) {
            $this->tanggal_keluar = trim($this->tanggal_keluar);

            $mulai = $kontrak->tanggal_mulai->toDateString();
            $berakhir = $kontrak->tanggal_berakhir->toDateString();
            $hariIni = Kontrak::hariIni()->toDateString();

            $this->validate([
                'tanggal_keluar' => [
                    'bail',
                    'required',
                    'date_format:Y-m-d',
                    function (string $atribut, mixed $nilai, Closure $gagal) use ($mulai, $berakhir, $hariIni) {
                        if ($nilai <= $mulai) {
                            $gagal('Tanggal keluar harus setelah tanggal mulai kontrak.');
                        } elseif ($nilai > $hariIni) {
                            $gagal('Tanggal keluar tidak boleh setelah hari ini.');
                        } elseif ($nilai > $berakhir) {
                            $gagal('Tanggal keluar tidak boleh setelah tanggal berakhir kontrak.');
                        }
                    },
                ],
            ], [
                'tanggal_keluar.required' => 'Isi tanggal keluar.',
                'tanggal_keluar.date_format' => 'Isi tanggal keluar yang valid.',
            ]);

            $tanggalKeluar = CarbonImmutable::parse($this->tanggal_keluar);
        }

        try {
            $kontrak->akhiri($tanggalKeluar);
        } catch (KontrakSudahBerakhir $e) {
            Flux::modal('akhiri-kontrak')->close();
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->refreshDaftar();

            return;
        }

        $this->reset('idAkhiri', 'tanggal_keluar');
        $this->refreshDaftar();

        Flux::modal('akhiri-kontrak')->close();
        Flux::toast(variant: 'success', text: "Kontrak Kamar {$kontrak->kamar->nomor_kamar} diakhiri.");
    }
}; ?>

@php
    $bisaKelola = $this->bisaKelola;
    $batas = $this->batasPeringatan;
    $kontrakModal = $this->kontrakAkhiri;
    $tanggalId = fn ($tanggal) => $tanggal->locale('id')->translatedFormat('j M Y');
    $tanggalPanjang = fn ($tanggal) => $tanggal->locale('id')->translatedFormat('j F Y');
    $kelasLinkAksi = 'inline-flex min-h-10 items-center px-1 teks-tombol-kecil';
@endphp

<section class="flex w-full flex-col gap-6">
    <x-judul-halaman judul="Kontrak">
        @if ($bisaKelola)
            <flux:button variant="primary" :href="route('kontrak.buat')" wire:navigate class="tombol-utama" data-test="buat-kontrak">
                Buat
            </flux:button>
        @endif
    </x-judul-halaman>

    {{-- Kontrak yang akan berakhir (F-08) --}}
    <section aria-labelledby="judul-akan-berakhir" class="overflow-hidden rounded-2xl border border-border bg-surface" data-test="akan-berakhir">
        @if ($batas === null)
            <div class="flex flex-col gap-3 px-4 py-4 sm:px-5">
                <h2 id="judul-akan-berakhir" class="m-0 teks-bagian">Segera Berakhir</h2>
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="batas-belum-diatur">
                    <flux:callout.text>
                        Batas peringatan belum diatur.
                        @if ($bisaKelola)
                            <a href="{{ route('pengaturan.index') }}" wire:navigate class="font-semibold">Atur di Pengaturan</a>
                        @else
                            Pengelola belum mengatur batasnya di Pengaturan.
                        @endif
                    </flux:callout.text>
                </flux:callout>
            </div>
        @else
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-4 sm:px-5 sm:py-[18px]">
                <h2 id="judul-akan-berakhir" class="m-0 teks-bagian">Segera Berakhir</h2>
            </div>

            @if ($this->akanBerakhir->isEmpty())
                <p class="m-0 border-t border-row-divider px-4 py-5 text-ink-muted sm:px-5" data-test="akan-berakhir-kosong">Tidak ada kontrak yang akan berakhir.</p>
            @else
                <ul class="m-0 list-none p-0">
                    @foreach ($this->akanBerakhir as $kontrak)
                        @php($sisa = $kontrak->sisaHari())
                        <li wire:key="akan-berakhir-{{ $kontrak->id }}" class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-baseline gap-x-2.5 gap-y-1 border-t border-row-divider px-4 py-3.5 sm:px-5 md:grid-cols-[72px_minmax(0,1fr)_auto_auto] md:items-center md:gap-x-4 md:py-4" data-test="baris-akan-berakhir">
                            <span class="num teks-nomor-kamar">{{ $kontrak->kamar?->nomor_kamar }}</span>
                            <span class="min-w-0">
                                <strong class="font-semibold">{{ $kontrak->penghuni?->user?->nama }}</strong>
                                <span class="block teks-bantuan">Berakhir {{ $tanggalPanjang($kontrak->tanggal_berakhir) }}</span>
                            </span>
                            @if ($sisa > 0)
                                <x-status jenis="tunggu">{{ $sisa }} Hari Lagi</x-status>
                            @elseif ($sisa === 0)
                                <x-status jenis="tunggu">Berakhir Hari Ini</x-status>
                            @else
                                <x-status jenis="telat">Lewat {{ abs($sisa) }} Hari</x-status>
                            @endif
                            @if ($bisaKelola)
                                <span class="col-span-3 flex items-center gap-3 md:col-span-1 md:gap-2">
                                    <a href="{{ route('kontrak.perpanjang', $kontrak) }}" wire:navigate class="tautan-aksi sekunder-md {{ $kelasLinkAksi }} md:tombol-kecil md:border md:border-border-strong md:bg-surface" data-test="perpanjang-kontrak">Perpanjang</a>
                                    <button type="button" class="tautan-bahaya {{ $kelasLinkAksi }}" wire:click="konfirmasiAkhiri({{ $kontrak->id }})" data-test="akhiri-kontrak">Akhiri</button>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <div role="group" aria-label="Saring status" class="baris-filter -mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
        <x-chip-filter :aktif="$this->filterAktif === 'semua'" :jumlah="$this->hitungan['semua']" wire:click="$set('filterStatus', 'semua')">Semua</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'aktif'" :jumlah="$this->hitungan['aktif']" wire:click="$set('filterStatus', 'aktif')">Aktif</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'berakhir'" :jumlah="$this->hitungan['berakhir']" wire:click="$set('filterStatus', 'berakhir')">Berakhir</x-chip-filter>
    </div>

    <div wire:loading.class="opacity-60" class="transisi-opasitas flex flex-col gap-4">
    @if ($this->daftarKontrak->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-border bg-surface px-4 py-10 text-center" data-test="pesan-kosong">
            <p class="m-0 text-ink-muted">{{ $this->hitungan['semua'] === 0 ? 'Belum ada kontrak.' : 'Tidak ada kontrak yang sesuai filter.' }}</p>
            @if ($this->hitungan['semua'] === 0 && $bisaKelola)
                <flux:button variant="primary" :href="route('kontrak.buat')" wire:navigate class="tombol-utama">Buat</flux:button>
            @endif
        </div>
    @else
        {{-- Desktop: tabel dalam kartu --}}
        <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
            <table class="w-full min-w-[820px] border-collapse">
                <thead>
                    <tr>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Kamar</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Penghuni</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Mulai</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Berakhir</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-end whitespace-nowrap teks-kolom">Tarif</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Status</th>
                        @if ($bisaKelola)
                            <th scope="col" class="border-b border-border px-5 py-3.5 text-end whitespace-nowrap teks-kolom"><span class="sr-only">Aksi</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarKontrak as $kontrak)
                        @php($aktif = $kontrak->status_kontrak === StatusKontrak::Aktif)
                        <tr wire:key="kontrak-tabel-{{ $kontrak->id }}" class="h-[60px] border-t border-row-divider first:border-t-0">
                            <td class="num px-5 teks-nomor-kamar">{{ $kontrak->kamar?->nomor_kamar }}</td>
                            <td class="px-5 font-semibold whitespace-nowrap">{{ $kontrak->penghuni?->user?->nama }}</td>
                            <td class="px-5 whitespace-nowrap">{{ $tanggalId($kontrak->tanggal_mulai) }}</td>
                            <td class="px-5 whitespace-nowrap">{{ $tanggalId($kontrak->tanggal_berakhir) }}</td>
                            <td class="num px-5 text-end font-semibold whitespace-nowrap">{{ Format::rupiah($kontrak->tarif_bulanan) }}</td>
                            <td class="px-5">
                                @if ($aktif)
                                    <x-status jenis="ok">Aktif</x-status>
                                @else
                                    <x-status jenis="belum">Berakhir</x-status>
                                @endif
                            </td>
                            @if ($bisaKelola)
                                <td class="px-5 text-end whitespace-nowrap">
                                    @if ($aktif)
                                        <a href="{{ route('kontrak.perpanjang', $kontrak) }}" wire:navigate class="tautan-aksi {{ $kelasLinkAksi }} px-2!">Perpanjang</a>
                                        <button type="button" class="tautan-bahaya {{ $kelasLinkAksi }} px-2!" wire:click="konfirmasiAkhiri({{ $kontrak->id }})">Akhiri</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Ponsel: daftar kartu --}}
        <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 md:hidden">
            @foreach ($this->daftarKontrak as $kontrak)
                @php($aktif = $kontrak->status_kontrak === StatusKontrak::Aktif)
                <li wire:key="kontrak-kartu-{{ $kontrak->id }}" class="flex flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="flex min-w-0 items-baseline gap-2.5">
                            <span class="num teks-nomor-kamar">{{ $kontrak->kamar?->nomor_kamar }}</span>
                            <span class="truncate font-semibold">{{ $kontrak->penghuni?->user?->nama }}</span>
                        </span>
                        <span class="num shrink-0 teks-tombol-kecil">{{ Format::rupiah($kontrak->tarif_bulanan) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 teks-badan">
                        <span class="text-ink-muted">{{ $tanggalId($kontrak->tanggal_mulai) }} sampai {{ $tanggalId($kontrak->tanggal_berakhir) }}</span>
                        @unless ($aktif)
                            <x-status jenis="belum">Berakhir</x-status>
                        @endunless
                    </div>
                    @if ($bisaKelola && $aktif)
                        <div class="flex items-center gap-3">
                            <a href="{{ route('kontrak.perpanjang', $kontrak) }}" wire:navigate class="tautan-aksi {{ $kelasLinkAksi }}">Perpanjang</a>
                            <button type="button" class="tautan-bahaya {{ $kelasLinkAksi }}" wire:click="konfirmasiAkhiri({{ $kontrak->id }})">Akhiri</button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        {{ $this->daftarKontrak->links() }}
    @endif
    </div>

    @if ($bisaKelola)
        @php($nomorModal = $kontrakModal?->kamar?->nomor_kamar)
        <x-dialog-konfirmasi
            name="akhiri-kontrak"
            :judul="'Akhiri Kontrak Kamar '.$nomorModal.'?'"
            label-aksi="Akhiri"
            wire:click="akhiri"
            data-test="konfirmasi-akhiri-kontrak"
        >
            <x-slot:ringkasan>
                <dt>Penghuni</dt>
                <dd>{{ $kontrakModal?->penghuni?->user?->nama }}</dd>
                <dt>Masa Sewa</dt>
                <dd>{{ $kontrakModal ? $tanggalId($kontrakModal->tanggal_mulai).' sampai '.$tanggalId($kontrakModal->tanggal_berakhir) : '' }}</dd>
                <dt>Tarif</dt>
                <dd>{{ $kontrakModal ? Format::rupiah($kontrakModal->tarif_bulanan) : '' }}</dd>
            </x-slot:ringkasan>

            <x-slot:akibat>Kamar {{ $nomorModal }} akan menjadi kosong, sedangkan tagihan dan status penghuni tetap. Kontrak yang diakhiri tidak bisa diaktifkan lagi.</x-slot:akibat>

            @if ($kontrakModal && ! $kontrakModal->sudahBerjalan())
                <x-slot:catatan>Kontrak belum dimulai, tanggalnya tidak diubah.</x-slot:catatan>
            @endif

            @if ($kontrakModal?->sudahBerjalan())
                <x-slot:isi>
                    <flux:field>
                        <flux:label>Tanggal Keluar</flux:label>
                        <flux:input type="date" wire:model="tanggal_keluar" data-test="input-tanggal-keluar" />
                        <flux:description>Menggantikan tanggal berakhir.</flux:description>
                        <flux:error name="tanggal_keluar" />
                    </flux:field>
                </x-slot:isi>
            @endif
        </x-dialog-konfirmasi>
    @endif
</section>
