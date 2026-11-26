<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPenghuni;
use App\Enums\StatusSinkronisasi;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat')] class extends Component {
    use WithPagination;

    /** Langkah tampil bertahap di mode penghuni. */
    private const LANGKAH = 12;

    #[Url(as: 'penghuni', except: '')]
    public string $filterPenghuni = '';

    /** Periode, YYYY-MM. */
    #[Url(as: 'periode', except: '')]
    public string $filterPeriode = '';

    /** Jumlah baris yang tampil di mode penghuni. */
    public int $batas = self::LANGKAH;

    public function mount(): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        if (! $this->modePenghuni) {
            // Nilai filter yang tidak dikenal dianggap "Semua".
            $this->filterPenghuni = $this->filterPenghuniAktif;
            $this->filterPeriode = $this->filterPeriodeAktif;
        }
    }

    #[Computed]
    public function modePenghuni(): bool
    {
        return auth()->user()->peran === PeranPengguna::Penghuni;
    }

    /** Hanya pengelola yang punya halaman Sinkronisasi ERP untuk ditautkan. */
    #[Computed]
    public function bisaLihatSinkronisasi(): bool
    {
        return auth()->user()->peran === PeranPengguna::Pengelola;
    }

    public function updatedFilterPenghuni(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPeriode(): void
    {
        $this->resetPage();
    }

    public function tampilkanLagi(): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        $this->batas += self::LANGKAH;
    }

    // ---- Mode penghuni ----

    /**
     * Selalu penghuni milik pengguna yang login; tidak pernah menerima ID dari URL atau browser (ATB-10).
     */
    #[Computed]
    public function penghuni(): ?Penghuni
    {
        return Penghuni::query()->where('user_id', auth()->id())->first();
    }

    /**
     * Pembayaran diterima milik penghuni, semua kontrak, periode terbaru di atas.
     *
     * @return Collection<int, Pembayaran>
     */
    #[Computed]
    public function barisPenghuni(): Collection
    {
        return Pembayaran::query()
            ->diterima()
            ->milikPenghuni($this->penghuni)
            ->join('tagihan', 'tagihan.id', '=', 'pembayaran.tagihan_id')
            ->select('pembayaran.*')
            ->with('tagihan:id,periode')
            ->orderByDesc('tagihan.periode')
            ->orderByDesc('pembayaran.id')
            ->limit($this->batas)
            ->get();
    }

    /**
     * @return object{jumlah: int, total: string, sejak: string|null}
     */
    #[Computed]
    public function ringkasanPenghuni(): object
    {
        return Pembayaran::query()
            ->diterima()
            ->milikPenghuni($this->penghuni)
            ->join('tagihan', 'tagihan.id', '=', 'pembayaran.tagihan_id')
            ->toBase()
            ->selectRaw('count(*) as jumlah, coalesce(sum(pembayaran.nominal_bayar), 0) as total, min(tagihan.periode) as sejak')
            ->first();
    }

    // ---- Mode kantor (pengelola dan pemilik) ----

    /**
     * Penghuni yang punya pembayaran diterima, urut nama; penghuni yang sudah keluar ikut.
     *
     * @return Collection<int, Penghuni>
     */
    #[Computed]
    public function opsiPenghuni(): Collection
    {
        return Penghuni::query()
            ->select('penghuni.id', 'penghuni.user_id', 'penghuni.status_penghuni')
            ->with('user:id,nama')
            ->whereIn('penghuni.id', Kontrak::query()
                ->select('kontrak.penghuni_id')
                ->join('tagihan', 'tagihan.kontrak_id', '=', 'kontrak.id')
                ->join('pembayaran', 'pembayaran.tagihan_id', '=', 'tagihan.id')
                ->where('pembayaran.status_verifikasi', 'diterima'))
            ->get()
            ->sortBy(fn (Penghuni $penghuni) => mb_strtolower((string) $penghuni->user?->nama), SORT_NATURAL)
            ->values();
    }

    /**
     * Periode yang punya pembayaran diterima, terbaru di atas: nilai YYYY-MM menjadi "November 2026".
     *
     * @return array<string, string>
     */
    #[Computed]
    public function opsiPeriode(): array
    {
        return Tagihan::query()
            ->whereHas('pembayaran', fn (Builder $pembayaran) => $pembayaran->diterima())
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->mapWithKeys(fn ($periode) => [$periode->format('Y-m') => $periode->locale('id')->translatedFormat('F Y')])
            ->all();
    }

    #[Computed]
    public function filterPenghuniAktif(): string
    {
        return ctype_digit($this->filterPenghuni) && $this->opsiPenghuni->contains('id', (int) $this->filterPenghuni)
            ? $this->filterPenghuni
            : '';
    }

    #[Computed]
    public function filterPeriodeAktif(): string
    {
        return array_key_exists($this->filterPeriode, $this->opsiPeriode) ? $this->filterPeriode : '';
    }

    /**
     * Dasar semua query mode kantor: pembayaran diterima, disaring penghuni dan periode, dengan join
     * tagihan, kontrak, dan kamar kontrak itu (bukan kamar saat ini).
     */
    private function dasarKantor(): Builder
    {
        $query = Pembayaran::query()
            ->diterima()
            ->join('tagihan', 'tagihan.id', '=', 'pembayaran.tagihan_id')
            ->join('kontrak', 'kontrak.id', '=', 'tagihan.kontrak_id')
            ->join('kamar', 'kamar.id', '=', 'kontrak.kamar_id');

        if ($this->filterPenghuniAktif !== '') {
            $query->where('kontrak.penghuni_id', (int) $this->filterPenghuniAktif);
        }

        if ($this->filterPeriodeAktif !== '') {
            $query->where('tagihan.periode', $this->filterPeriodeAktif.'-01');
        }

        return $query;
    }

    /**
     * Jumlah dan total nominal SELURUH hasil filter (bukan hanya halaman ini), dalam satu query agregat.
     *
     * @return object{jumlah: int, total: string}
     */
    #[Computed]
    public function ringkasanKantor(): object
    {
        return $this->dasarKantor()
            ->toBase()
            ->selectRaw('count(*) as jumlah, coalesce(sum(pembayaran.nominal_bayar), 0) as total')
            ->first();
    }

    #[Computed]
    public function halamanKantor(): LengthAwarePaginator
    {
        return $this->dasarKantor()
            ->select('pembayaran.*')
            ->with([
                'tagihan.kontrak.kamar:id,nomor_kamar',
                'tagihan.kontrak.penghuni:id,user_id,status_penghuni',
                'tagihan.kontrak.penghuni.user:id,nama',
                'diverifikasiOleh:id,nama',
                'sinkronisasi',
            ])
            ->orderByDesc('tagihan.periode')
            ->orderBy('kamar.nomor_kamar')
            ->orderByDesc('pembayaran.id')
            ->paginate(20);
    }
}; ?>

@php
    $zona = config('app.timezone');
    $tanggal = fn ($nilai) => $nilai?->setTimezone($zona)->locale('id')->translatedFormat('j M') ?? '-';
    $waktu = fn ($nilai) => $nilai ? $nilai->setTimezone($zona)->locale('id')->translatedFormat('j M').', '.$nilai->setTimezone($zona)->format('H.i') : '-';
@endphp

@if ($this->modePenghuni)
    {{-- ===================== MODE PENGHUNI ===================== --}}
    <section class="flex w-full max-w-[880px] flex-col gap-6">
        @if ($this->penghuni === null)
            <x-judul-halaman judul="Riwayat" />
            <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.</p>
        @else
            @php
                $baris = $this->barisPenghuni;
                $ringkasan = $this->ringkasanPenghuni;
                $sisa = max(0, (int) $ringkasan->jumlah - $baris->count());
            @endphp

            <x-judul-halaman judul="Riwayat" />

            @if ($ringkasan->jumlah > 0)
                <x-ringkasan-angka
                    :items="[
                        ['kunci' => 'sejak', 'label' => 'Sejak', 'nilai' => \Carbon\CarbonImmutable::parse($ringkasan->sejak)->locale('id')->translatedFormat('F Y')],
                        ['kunci' => 'jumlah', 'label' => 'Jumlah', 'nilai' => $ringkasan->jumlah.' kali'],
                        ['kunci' => 'total', 'label' => 'Total', 'nilai' => Format::rupiah($ringkasan->total)],
                    ]"
                />
            @endif

            @if ($baris->isEmpty())
                <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="kosong">Belum ada pembayaran yang diterima.</p>
            @else
                {{-- Desktop: tabel kartu --}}
                <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
                    <table class="w-full min-w-[640px] border-collapse">
                        <thead>
                            <tr>
                                @foreach (['Periode' => 'left', 'Dibayar' => 'left', 'Diterima' => 'left', 'Nominal' => 'right', 'Status' => 'left'] as $judul => $rata)
                                    <th scope="col" @class(['border-b border-border px-5 py-3.5 whitespace-nowrap teks-kolom', 'text-left' => $rata === 'left', 'text-right' => $rata === 'right'])>{{ $judul }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($baris as $b)
                                <tr wire:key="tabel-{{ $b->id }}" class="border-t border-row-divider first:border-t-0">
                                    <td class="px-5 py-3.5 font-semibold whitespace-nowrap">{{ $b->tagihan->periode->locale('id')->translatedFormat('F Y') }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $tanggal($b->tanggal_bayar) }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-ink-muted">{{ $tanggal($b->diverifikasi_pada) }}</td>
                                    <td class="num px-5 py-3.5 text-right font-semibold whitespace-nowrap">{{ Format::rupiah($b->nominal_bayar) }}</td>
                                    <td class="px-5 py-3.5"><x-status jenis="ok">Lunas</x-status></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Ponsel: kartu daftar --}}
                <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 md:hidden">
                    @foreach ($baris as $b)
                        <li wire:key="daftar-{{ $b->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-0.5 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                            <span class="font-semibold">{{ $b->tagihan->periode->locale('id')->translatedFormat('F Y') }}</span>
                            <span class="num font-semibold">{{ Format::rupiah($b->nominal_bayar) }}</span>
                            <span class="teks-bantuan">Dibayar {{ $tanggal($b->tanggal_bayar) }}, Diterima {{ $tanggal($b->diverifikasi_pada) }}</span>
                            <span class="justify-self-end self-center text-status-ok">
                                <svg width="14" height="14" viewBox="0 0 16 16" role="img" aria-label="Lunas"><circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M4.8 8.2l2.1 2.1 4.3-4.6" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ($sisa > 0)
                    <button
                        type="button"
                        wire:click="tampilkanLagi"
                        wire:loading.attr="disabled"
                        wire:target="tampilkanLagi"
                        class="tombol w-full border border-border-strong bg-surface text-ink hover:bg-surface-muted md:w-fit"
                        data-test="tampilkan-lagi"
                    >Lainnya</button>
                @endif
            @endif
        @endif
    </section>
@else
    {{-- ===================== MODE KANTOR ===================== --}}
    @php
        $halaman = $this->halamanKantor;
        $ringkasan = $this->ringkasanKantor;
        $adaFilter = $this->filterPenghuniAktif !== '' || $this->filterPeriodeAktif !== '';
        $keluar = fn ($penghuni) => $penghuni?->status_penghuni === StatusPenghuni::Nonaktif;
    @endphp

    <section class="flex w-full flex-col gap-6">
        <x-judul-halaman judul="Riwayat" />

        <x-ringkasan-angka
            :items="[
                ['kunci' => 'jumlah', 'label' => 'Pembayaran', 'nilai' => (string) $ringkasan->jumlah],
                ['kunci' => 'total', 'label' => 'Total', 'nilai' => Format::rupiah($ringkasan->total)],
            ]"
        />

        {{-- Filter: sebaris di desktop, bertumpuk di ponsel --}}
        <div class="baris-filter flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:gap-4">
            <div class="grid grid-cols-2 gap-2 lg:contents">
                <div class="lg:w-[280px]">
                    <flux:field>
                        <flux:label>Penghuni</flux:label>
                        <flux:select wire:model.live="filterPenghuni" data-test="filter-penghuni">
                            <flux:select.option value="">Semua</flux:select.option>
                            @foreach ($this->opsiPenghuni as $opsi)
                                <flux:select.option value="{{ $opsi->id }}" wire:key="opsi-penghuni-{{ $opsi->id }}">{{ $opsi->user?->nama }}{{ $keluar($opsi) ? ' (sudah keluar)' : '' }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
                <div class="lg:w-[220px]">
                    <flux:field>
                        <flux:label>Periode</flux:label>
                        <flux:select wire:model.live="filterPeriode" data-test="filter-periode">
                            <flux:select.option value="">Semua</flux:select.option>
                            @foreach ($this->opsiPeriode as $nilai => $label)
                                <flux:select.option value="{{ $nilai }}" wire:key="opsi-periode-{{ $nilai }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
            </div>

        </div>

        <div wire:loading.class="opacity-60" wire:target="filterPenghuni,filterPeriode,gotoPage,nextPage,previousPage" class="transisi-opasitas flex flex-col gap-4">
            @if ($halaman->isEmpty())
                <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="kosong">
                    {{ $adaFilter ? 'Tidak ada pembayaran untuk filter ini.' : 'Belum ada pembayaran yang diterima.' }}
                </p>
            @else
                {{-- Desktop: tabel kartu --}}
                <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface lg:block">
                    <table class="w-full min-w-[900px] border-collapse">
                        <thead>
                            <tr>
                                @foreach (['Periode' => 'left', 'Penghuni' => 'left', 'Kamar' => 'left', 'Nominal' => 'right', 'Dibayar' => 'left', 'Diterima' => 'left', 'Odoo' => 'left'] as $judul => $rata)
                                    <th scope="col" @class(['border-b border-border px-5 py-3.5 whitespace-nowrap teks-kolom', 'text-left' => $rata === 'left', 'text-right' => $rata === 'right'])>{{ $judul }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($halaman as $b)
                                @php
                                    $kontrak = $b->tagihan->kontrak;
                                    $sinkron = $b->sinkronisasi;
                                @endphp
                                <tr wire:key="tabel-{{ $b->id }}" class="border-t border-row-divider first:border-t-0">
                                    <td class="px-5 py-3 whitespace-nowrap">{{ $b->tagihan->periode->locale('id')->translatedFormat('M Y') }}</td>
                                    <td class="px-5 py-3 font-semibold">{{ $kontrak->penghuni?->user?->nama }}@if ($keluar($kontrak->penghuni)) <span class="font-normal text-ink-muted">(sudah keluar)</span>@endif</td>
                                    <td class="px-5 py-3 whitespace-nowrap">{{ $kontrak->kamar?->nomor_kamar }}</td>
                                    <td class="num px-5 py-3 text-right font-semibold whitespace-nowrap">{{ Format::rupiah($b->nominal_bayar) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">{{ $tanggal($b->tanggal_bayar) }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-ink-muted">
                                        {{ $waktu($b->diverifikasi_pada) }}
                                        @if ($b->diverifikasiOleh)
                                            <span class="block teks-bantuan">oleh {{ $b->diverifikasiOleh->nama }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap" data-test="status-odoo">
                                        @if ($sinkron === null)
                                            <span class="teks-bantuan" title="Riwayat lama, tidak dikirim ke Odoo">Data Migrasi</span>
                                        @elseif ($sinkron->status_sinkronisasi === StatusSinkronisasi::Terkirim)
                                            <x-status jenis="ok">Terkirim</x-status>
                                        @elseif ($sinkron->status_sinkronisasi === StatusSinkronisasi::Menunggu)
                                            <x-status jenis="tunggu">Menunggu</x-status>
                                        @elseif ($this->bisaLihatSinkronisasi)
                                            <a href="{{ route('sinkronisasi-erp.index', ['status' => 'gagal', 'ref' => $sinkron->referensi_unik]) }}" wire:navigate class="no-underline" data-test="tautan-gagal"><x-status jenis="gagal">Gagal</x-status></a>
                                        @else
                                            <x-status jenis="gagal">Gagal</x-status>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Ponsel: kartu daftar --}}
                <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 lg:hidden">
                    @foreach ($halaman as $b)
                        @php
                            $kontrak = $b->tagihan->kontrak;
                            $sinkron = $b->sinkronisasi;
                        @endphp
                        <li wire:key="daftar-{{ $b->id }}" class="flex flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="font-semibold">{{ $kontrak->penghuni?->user?->nama }}@if ($keluar($kontrak->penghuni)) <span class="font-normal text-ink-muted">(sudah keluar)</span>@endif</span>
                                <span class="num font-semibold">{{ Format::rupiah($b->nominal_bayar) }}</span>
                            </div>
                            <span class="teks-bantuan">{{ $b->tagihan->periode->locale('id')->translatedFormat('M Y') }}, Kamar {{ $kontrak->kamar?->nomor_kamar }}, Diterima {{ $waktu($b->diverifikasi_pada) }}</span>
                            <div class="flex items-center gap-2" data-test="status-odoo">
                                <span class="teks-bantuan">Odoo</span>
                                @if ($sinkron === null)
                                    <span class="teks-bantuan" title="Riwayat lama, tidak dikirim ke Odoo">Data Migrasi</span>
                                @elseif ($sinkron->status_sinkronisasi === StatusSinkronisasi::Terkirim)
                                    <x-status jenis="ok">Terkirim</x-status>
                                @elseif ($sinkron->status_sinkronisasi === StatusSinkronisasi::Menunggu)
                                    <x-status jenis="tunggu">Menunggu</x-status>
                                @elseif ($this->bisaLihatSinkronisasi)
                                    <a href="{{ route('sinkronisasi-erp.index', ['status' => 'gagal', 'ref' => $sinkron->referensi_unik]) }}" wire:navigate class="no-underline" data-test="tautan-gagal"><x-status jenis="gagal">Gagal</x-status></a>
                                @else
                                    <x-status jenis="gagal">Gagal</x-status>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <flux:pagination :paginator="$halaman" />
            @endif
        </div>
    </section>
@endif
