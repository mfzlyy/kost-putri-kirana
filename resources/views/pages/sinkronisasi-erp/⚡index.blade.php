<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Exceptions\SinkronisasiTidakBisaDikirimUlang;
use App\Models\SinkronisasiErp;
use App\Support\Format;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Sinkronisasi')] class extends Component {
    use WithPagination;

    private const FILTER = ['semua', 'gagal', 'menunggu', 'terkirim'];

    private const BAWAAN = 'gagal';

    private const PER_HALAMAN = 20;

    /** Filter status; nilai lain dianggap bawaan (gagal). */
    #[Url(as: 'status')]
    public string $status = self::BAWAAN;

    /** referensi_unik yang disorot, dari tautan "Gagal" di Riwayat. Nilai tidak dikenal diabaikan. */
    #[Url(as: 'ref', except: '')]
    public string $sorot = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SinkronisasiErp::class);

        $this->status = $this->filterAktif();
        $this->menujuSorotan();
    }

    public function filterAktif(): string
    {
        return in_array($this->status, self::FILTER, true) ? $this->status : self::BAWAAN;
    }

    /**
     * Jumlah baris per chip filter, dari satu query.
     *
     * @return array{semua: int, gagal: int, menunggu: int, terkirim: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $perStatus = SinkronisasiErp::query()
            ->toBase()
            ->selectRaw('status_sinkronisasi, count(*) as jumlah')
            ->groupBy('status_sinkronisasi')
            ->pluck('jumlah', 'status_sinkronisasi');

        $ambil = fn (string $nama): int => (int) ($perStatus[$nama] ?? 0);

        return [
            'semua' => (int) $perStatus->sum(),
            'gagal' => $ambil('gagal'),
            'menunggu' => $ambil('menunggu'),
            'terkirim' => $ambil('terkirim'),
        ];
    }

    /**
     * Baris sesuai filter beserta sumbernya. Kolom penghuni dibatasi: nomor identitas dan kontak tidak dimuat.
     */
    #[Computed]
    public function halaman(): LengthAwarePaginator
    {
        $halaman = $this->muatHalaman();

        // Halaman terakhir bisa kosong setelah barisnya pindah status.
        if ($halaman->isEmpty() && $halaman->currentPage() > 1) {
            $this->setPage($halaman->lastPage());
            $halaman = $this->muatHalaman();
        }

        return $halaman;
    }

    /**
     * Isi kolom Sumber: teks, nominal (null bila jenisnya tidak punya nominal), dan tautan ke asalnya.
     *
     * @return array{teks: string, nominal: string|null, url: string}
     */
    public function sumber(SinkronisasiErp $baris): array
    {
        if ($baris->jenis_transaksi === JenisTransaksiErp::Pembayaran) {
            $tagihan = $baris->pembayaran->tagihan;
            $kontrak = $tagihan->kontrak;

            return [
                'teks' => implode(', ', [
                    $kontrak->penghuni->user->nama,
                    'Kamar '.$kontrak->kamar->nomor_kamar,
                    $tagihan->periode->locale('id')->translatedFormat('F Y'),
                ]),
                'nominal' => Format::rupiah($baris->pembayaran->nominal_bayar),
                'url' => route('riwayat-transaksi.index', [
                    'penghuni' => $kontrak->penghuni_id,
                    'periode' => $tagihan->periode->format('Y-m'),
                ]),
            ];
        }

        $tiket = $baris->tiket;

        return [
            'teks' => implode(', ', [$tiket->nomor_tiket, $tiket->fasilitas->lokasiErp(), $tiket->fasilitas->nama_fasilitas]),
            'nominal' => $baris->jenis_transaksi === JenisTransaksiErp::BiayaPerbaikan
                ? Format::rupiah($tiket->biaya_perbaikan)
                : null,
            'url' => route('tiket-keluhan.index', ['tiket' => $tiket->id]),
        ];
    }

    public function pilihStatus(string $nilai): void
    {
        $this->status = in_array($nilai, self::FILTER, true) ? $nilai : self::BAWAAN;
        $this->sorot = '';
        $this->resetPage();
    }

    /** Sorotan hanya penanda saat datang dari tautan; berpindah halaman melepasnya. */
    public function updatedPaginators(): void
    {
        $this->sorot = '';
    }

    public function kirimUlang(int $id): void
    {
        $baris = SinkronisasiErp::query()->findOrFail($id);

        $this->authorize('kirimUlang', $baris);

        try {
            $baris->kirimUlang(auth()->user());
        } catch (SinkronisasiTidakBisaDikirimUlang $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
            $this->segarkan();

            return;
        }

        Flux::toast(variant: 'success', text: $baris->referensi_unik.' dikirim ulang.');
        $this->segarkan();
    }

    /** Dipanggil wire:poll selama ada baris menunggu di halaman yang tampil. */
    public function muatUlang(): void
    {
        $this->authorize('viewAny', SinkronisasiErp::class);

        $this->segarkan();
    }

    /**
     * Pindah ke chip dan halaman yang memuat baris sorotan.
     */
    private function menujuSorotan(): void
    {
        if ($this->sorot === '') {
            return;
        }

        $baris = SinkronisasiErp::query()->where('referensi_unik', $this->sorot)->first();

        if ($baris === null) {
            $this->sorot = '';

            return;
        }

        if ($this->status !== 'semua') {
            $this->status = $baris->status_sinkronisasi->value;
        }

        $urutan = $this->dasar()->terbaruDicoba()->pluck('id')->search($baris->id);

        // getPage() mendaftarkan ?page= ke URL; nilainya lalu diisi langsung supaya sorotan tidak ikut dilepas.
        $this->getPage();
        $this->paginators['page'] = intdiv((int) $urutan, self::PER_HALAMAN) + 1;
    }

    private function dasar(): \Illuminate\Database\Eloquent\Builder
    {
        $query = SinkronisasiErp::query();

        if ($this->filterAktif() !== 'semua') {
            $query->where('status_sinkronisasi', $this->filterAktif());
        }

        return $query;
    }

    private function muatHalaman(): LengthAwarePaginator
    {
        return $this->dasar()
            ->with([
                'pembayaran:id,tagihan_id,nominal_bayar',
                'pembayaran.tagihan:id,kontrak_id,periode',
                'pembayaran.tagihan.kontrak:id,penghuni_id,kamar_id',
                'pembayaran.tagihan.kontrak.kamar:id,nomor_kamar',
                'pembayaran.tagihan.kontrak.penghuni:id,user_id',
                'pembayaran.tagihan.kontrak.penghuni.user:id,nama',
                'tiket:id,nomor_tiket,fasilitas_id,biaya_perbaikan',
                'tiket.fasilitas:id,nama_fasilitas,kamar_id',
                'tiket.fasilitas.kamar:id,nomor_kamar',
            ])
            ->terbaruDicoba()
            ->paginate(self::PER_HALAMAN);
    }

    /**
     * Daftar dan angka chip dimuat ulang; angka gagal di menu sidebar ikut diperbarui lewat event window.
     */
    private function segarkan(): void
    {
        unset($this->hitungan, $this->halaman);

        $this->dispatch('antrean-sinkronisasi', jumlah: $this->hitungan['gagal']);
    }
}; ?>

@php
    $halaman = $this->halaman;
    $hitungan = $this->hitungan;
    $adaMenunggu = $halaman->getCollection()->contains(fn ($baris) => $baris->status_sinkronisasi === StatusSinkronisasi::Menunggu);
    $jenisStatus = [
        StatusSinkronisasi::Gagal->value => 'gagal',
        StatusSinkronisasi::Menunggu->value => 'tunggu',
        StatusSinkronisasi::Terkirim->value => 'ok',
    ];
    $filter = [
        'semua' => ['label' => 'Semua', 'kosong' => 'Belum ada transaksi untuk Odoo.'],
        'gagal' => ['label' => 'Gagal', 'kosong' => 'Tidak ada transaksi yang gagal.'],
        'menunggu' => ['label' => 'Menunggu', 'kosong' => 'Tidak ada transaksi yang menunggu.'],
        'terkirim' => ['label' => 'Terkirim', 'kosong' => 'Belum ada transaksi yang terkirim.'],
    ];
    $kelasTombol = 'tombol-kecil border border-border-strong bg-surface text-ink hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-60';
@endphp

<section class="flex w-full flex-col gap-6">
    <x-judul-halaman judul="Sinkronisasi" />

    <div role="group" aria-label="Saring status" class="baris-filter -mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
        @foreach ($filter as $nilai => $chip)
            <x-chip-filter
                wire:key="chip-{{ $nilai }}"
                :aktif="$this->filterAktif() === $nilai"
                :jumlah="$hitungan[$nilai]"
                wire:click="pilihStatus('{{ $nilai }}')"
            >{{ $chip['label'] }}</x-chip-filter>
        @endforeach
    </div>

    <div class="relative">
        {{-- Elemen tersendiri supaya polling berhenti begitu dilepas; menutupi daftar agar .visible mengikuti daftar. --}}
        @if ($adaMenunggu)
            <span wire:key="pemantau" wire:poll.5s.visible="muatUlang" class="pointer-events-none absolute inset-0" aria-hidden="true" data-test="pemantau"></span>
        @endif

        <div wire:key="daftar" wire:loading.class="opacity-60" wire:target="pilihStatus,gotoPage,nextPage,previousPage" class="transisi-opasitas flex flex-col gap-4">
            @if ($halaman->isEmpty())
                <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="kosong">{{ $filter[$this->filterAktif()]['kosong'] }}</p>
            @else
                {{-- Desktop: tabel kartu. relative menahan teks sr-only di kepala tabel tetap di dalam area gulir. --}}
                <div class="relative hidden overflow-x-auto rounded-2xl border border-border bg-surface lg:block">
                    <table class="w-full min-w-[820px] border-collapse" data-test="tabel-sinkronisasi">
                        <thead>
                            <tr>
                                @foreach (['Referensi', 'Jenis', 'Sumber', 'Status', 'Waktu'] as $judul)
                                    {{-- Sumber memakai sisa lebar tabel; kolom lain selebar isinya. --}}
                                    <th scope="col" @class(['border-b border-border text-left whitespace-nowrap teks-kolom', 'w-full' => $judul === 'Sumber'])>{{ $judul }}</th>
                                @endforeach
                                <th scope="col" class="border-b border-border"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($halaman as $baris)
                                @php
                                    $sumber = $this->sumber($baris);
                                    $disorot = $baris->referensi_unik === $this->sorot;
                                @endphp
                                <tr
                                    wire:key="tabel-{{ $baris->id }}"
                                    @class(['border-t border-row-divider first:border-t-0', 'bg-primary-container!' => $disorot])
                                    @if ($disorot) aria-current="true" x-data x-init="$nextTick(() => $el.scrollIntoView({ block: 'center' }))" @endif
                                    data-test="baris-sinkronisasi"
                                >
                                    <td class="num font-semibold whitespace-nowrap" data-test="referensi">{{ $baris->referensi_unik }}</td>
                                    <td class="whitespace-nowrap">{{ $baris->labelJenis() }}</td>
                                    <td data-test="sumber">
                                        <a href="{{ $sumber['url'] }}" wire:navigate>{{ $sumber['teks'] }}</a>
                                        @if ($sumber['nominal'] !== null)
                                            <span class="num block teks-bantuan">{{ $sumber['nominal'] }}</span>
                                        @endif
                                        @if ($baris->status_sinkronisasi === StatusSinkronisasi::Gagal && filled($baris->pesan_error))
                                            <p class="m-0 mt-1 [overflow-wrap:anywhere] teks-galat" data-test="pesan-error">{{ $baris->pesan_error }}</p>
                                        @endif
                                    </td>
                                    <td><x-status :jenis="$jenisStatus[$baris->status_sinkronisasi->value]">{{ $baris->status_sinkronisasi->label() }}</x-status></td>
                                    <td class="num whitespace-nowrap" data-test="waktu">{{ $baris->waktu_kirim ? Format::tanggalJam($baris->waktu_kirim) : '–' }}</td>
                                    <td class="text-right">
                                        @if ($baris->status_sinkronisasi === StatusSinkronisasi::Gagal)
                                            <button type="button" wire:click="kirimUlang({{ $baris->id }})" wire:loading.attr="disabled" wire:target="kirimUlang({{ $baris->id }})" class="{{ $kelasTombol }}" data-test="kirim-ulang">
                                                <span wire:loading.remove wire:target="kirimUlang({{ $baris->id }})">Kirim Ulang</span>
                                                <span wire:loading wire:target="kirimUlang({{ $baris->id }})">Mengirim…</span>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Ponsel: kartu per baris --}}
                <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 lg:hidden" data-test="daftar-sinkronisasi">
                    @foreach ($halaman as $baris)
                        @php
                            $sumber = $this->sumber($baris);
                            $disorot = $baris->referensi_unik === $this->sorot;
                        @endphp
                        <li
                            wire:key="kartu-{{ $baris->id }}"
                            @class(['flex min-w-0 flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0', 'bg-primary-container' => $disorot])
                            @if ($disorot) aria-current="true" x-data x-init="$nextTick(() => $el.scrollIntoView({ block: 'center' }))" @endif
                            data-test="kartu-sinkronisasi"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <span class="num font-semibold">{{ $baris->referensi_unik }}</span>
                                <x-status :jenis="$jenisStatus[$baris->status_sinkronisasi->value]">{{ $baris->status_sinkronisasi->label() }}</x-status>
                            </div>
                            <span class="teks-bantuan">{{ $baris->labelJenis() }}</span>
                            <a href="{{ $sumber['url'] }}" wire:navigate class="[overflow-wrap:anywhere]">{{ $sumber['teks'] }}</a>
                            @if ($sumber['nominal'] !== null)
                                <span class="num font-semibold">{{ $sumber['nominal'] }}</span>
                            @endif
                            <span class="num teks-bantuan">Waktu {{ $baris->waktu_kirim ? Format::tanggalJam($baris->waktu_kirim) : '–' }}</span>
                            @if ($baris->status_sinkronisasi === StatusSinkronisasi::Gagal)
                                @if (filled($baris->pesan_error))
                                    <p class="m-0 [overflow-wrap:anywhere] teks-galat">{{ $baris->pesan_error }}</p>
                                @endif
                                <button type="button" wire:click="kirimUlang({{ $baris->id }})" wire:loading.attr="disabled" wire:target="kirimUlang({{ $baris->id }})" class="{{ $kelasTombol }} mt-1.5 h-11! w-full" data-test="kirim-ulang-ponsel">
                                    <span wire:loading.remove wire:target="kirimUlang({{ $baris->id }})">Kirim Ulang</span>
                                    <span wire:loading wire:target="kirimUlang({{ $baris->id }})">Mengirim…</span>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <flux:pagination :paginator="$halaman" />
            @endif
        </div>
    </div>
</section>
