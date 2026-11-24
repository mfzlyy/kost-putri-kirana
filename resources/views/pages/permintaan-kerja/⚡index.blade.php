<?php

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use App\Exceptions\StatusTiketBerubah;
use App\Models\TiketKeluhan;
use App\Models\Vendor;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pekerjaan')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', TiketKeluhan::class);

        if ($pesan = session('toast')) {
            Flux::toast(variant: 'success', text: $pesan);
        }
    }

    /**
     * Selalu vendor milik pengguna yang login; tidak pernah menerima ID vendor dari URL atau browser (ATB-08).
     */
    #[Computed]
    public function vendor(): ?Vendor
    {
        return Vendor::query()->where('user_id', auth()->id())->first();
    }

    /**
     * Tiket ditugaskan dan dikerjakan milik vendor ini, yang terlama ditugaskan di atas (F-19 butir 1).
     * Hanya kolom tiket, fasilitas, kamar, dan riwayat yang dimuat; relasi penghuni tidak pernah disentuh (F-19 butir 2).
     *
     * @return Collection<int, TiketKeluhan>
     */
    #[Computed]
    public function perluDikerjakan(): Collection
    {
        if ($this->vendor === null) {
            return collect();
        }

        return TiketKeluhan::query()
            ->milikVendor($this->vendor)
            ->whereIn('tiket_keluhan.status_tiket', [StatusTiket::Ditugaskan->value, StatusTiket::Dikerjakan->value])
            ->with(['fasilitas.kamar', 'riwayatStatus'])
            ->get()
            ->sortBy([
                fn (TiketKeluhan $a, TiketKeluhan $b) => $this->waktuStatus($a, StatusTiket::Ditugaskan)?->getTimestamp() <=> $this->waktuStatus($b, StatusTiket::Ditugaskan)?->getTimestamp(),
                fn (TiketKeluhan $a, TiketKeluhan $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * Tiket selesai milik vendor ini yang waktu selesainya jatuh di bulan berjalan (zona waktu aplikasi), terbaru di atas.
     *
     * @return Collection<int, TiketKeluhan>
     */
    #[Computed]
    public function selesaiBulanIni(): Collection
    {
        if ($this->vendor === null) {
            return collect();
        }

        $awal = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        $akhir = $awal->addMonth();

        return TiketKeluhan::query()
            ->milikVendor($this->vendor)
            ->where('tiket_keluhan.status_tiket', StatusTiket::Selesai->value)
            ->whereHas('riwayatStatus', fn ($riwayat) => $riwayat
                ->where('status_sesudah', StatusTiket::Selesai->value)
                ->where('waktu_perubahan', '>=', $awal)
                ->where('waktu_perubahan', '<', $akhir))
            ->with(['fasilitas.kamar', 'riwayatStatus'])
            ->get()
            ->sortByDesc(fn (TiketKeluhan $tiket) => $this->waktuStatus($tiket, StatusTiket::Selesai)?->getTimestamp())
            ->values();
    }

    /**
     * Waktu tiket masuk ke status tertentu, dari riwayat yang sudah dimuat.
     */
    public function waktuStatus(TiketKeluhan $tiket, StatusTiket $status): ?CarbonImmutable
    {
        return $tiket->riwayatStatus->firstWhere('status_sesudah', $status)?->waktu_perubahan;
    }

    public function tanggalPendek(?CarbonImmutable $waktu): string
    {
        return $waktu === null ? '-' : $waktu->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j M');
    }

    public function lokasiTiket(TiketKeluhan $tiket): string
    {
        if ($tiket->lokasi === LokasiKeluhan::AreaBersama || $tiket->fasilitas->kamar === null) {
            return 'Area Bersama';
        }

        return 'Kamar '.$tiket->fasilitas->kamar->nomor_kamar;
    }

    /**
     * Ikon status: selesai memakai gaya ok, yang lain menunggu langkah berikutnya.
     */
    public function jenisStatus(StatusTiket $status): string
    {
        return $status === StatusTiket::Selesai ? 'ok' : 'tunggu';
    }

    /**
     * Vendor mulai mengerjakan tiket (F-19). ID tiket dari browser hanya penunjuk; hak diperiksa Policy dari vendor
     * milik pengguna yang login.
     */
    public function mulai(int $id): void
    {
        $tiket = TiketKeluhan::query()->findOrFail($id);

        $this->authorize('kerjakan', $tiket);

        try {
            $tiket->mulaiKerjakan(auth()->user());
        } catch (StatusTiketBerubah $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
            $this->segarkan();

            return;
        }

        Flux::toast(variant: 'success', text: $tiket->nomor_tiket.' mulai dikerjakan.');

        $this->segarkan();
    }

    private function segarkan(): void
    {
        unset($this->perluDikerjakan, $this->selesaiBulanIni);
    }
}; ?>

@php
    $perluDikerjakan = $this->perluDikerjakan;
    $selesaiBulanIni = $this->selesaiBulanIni;
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    <x-judul-halaman judul="Pekerjaan" />

    @if ($this->vendor === null)
        <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Data vendor Anda belum dilengkapi pengelola. Hubungi pengelola kos.</p>
    @else
        <div class="flex flex-col gap-3">
            <h2 class="m-0 teks-bagian">Perlu Dikerjakan <span class="num" data-test="jumlah-perlu">{{ $perluDikerjakan->count() }}</span></h2>

            @if ($perluDikerjakan->isEmpty())
                <p class="m-0 inline-flex items-center gap-2 rounded-2xl border border-border bg-surface px-4 py-10 text-ink-muted justify-center" data-test="pesan-kosong">
                    <x-status jenis="kosong">Belum ada permintaan kerja.</x-status>
                </p>
            @else
                <ul class="m-0 grid list-none grid-cols-1 items-start gap-4 p-0 lg:grid-cols-2" data-test="daftar-perlu-dikerjakan">
                    @foreach ($perluDikerjakan as $tiket)
                        <li
                            wire:key="perlu-{{ $tiket->id }}"
                            class="relative flex flex-col gap-2 rounded-2xl border border-border bg-surface p-4 text-ink focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary md:p-5"
                            data-test="kartu-tiket"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="num teks-bantuan" data-test="nomor-tiket">{{ $tiket->nomor_tiket }}</span>
                                <span class="teks-bantuan" data-test="waktu-ditugaskan">Ditugaskan {{ $this->tanggalPendek($this->waktuStatus($tiket, StatusTiket::Ditugaskan)) }}</span>
                            </div>

                            <h3 class="m-0 min-w-0 break-words teks-badan-kuat" data-test="jenis-kerusakan">
                                <a href="{{ route('permintaan-kerja.detail', $tiket) }}" wire:navigate class="text-ink no-underline after:absolute after:inset-0 after:rounded-2xl after:content-[''] hover:text-ink focus-visible:outline-none">{{ $tiket->jenis_kerusakan }}</a>
                            </h3>

                            <p class="m-0 teks-bantuan" data-test="fasilitas-lokasi">{{ $this->lokasiTiket($tiket) }}, {{ $tiket->fasilitas->nama_fasilitas }}</p>

                            <div class="flex items-center gap-2.5">
                                <x-meter-tiket :status="$tiket->status_tiket" />
                                <x-status :jenis="$this->jenisStatus($tiket->status_tiket)">{{ $tiket->status_tiket->label() }}</x-status>
                            </div>

                            <div class="relative z-10 mt-1 flex max-sm:[&>*]:flex-1">
                                @if ($tiket->status_tiket === StatusTiket::Ditugaskan)
                                    <flux:button
                                        type="button"
                                        variant="primary"
                                        wire:click="mulai({{ $tiket->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="mulai({{ $tiket->id }})"
                                        class="max-sm:w-full"
                                        data-test="mulai-tiket"
                                    >Mulai</flux:button>
                                @else
                                    <a href="{{ route('permintaan-kerja.detail', $tiket) }}" wire:navigate class="tombol border border-border-strong bg-surface text-ink no-underline hover:bg-surface-muted hover:text-ink max-sm:w-full" data-test="selesai-tiket">Selesai</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($selesaiBulanIni->isNotEmpty())
            <div class="flex flex-col gap-3">
                <h2 class="m-0 teks-bagian">Selesai Bulan Ini</h2>

                <ul class="m-0 flex list-none flex-col overflow-hidden rounded-2xl border border-border bg-surface p-0" data-test="daftar-selesai">
                    @foreach ($selesaiBulanIni as $tiket)
                        <li wire:key="selesai-{{ $tiket->id }}" class="relative border-t border-row-divider first:border-t-0 hover:bg-surface-muted" data-test="kartu-selesai">
                            <a href="{{ route('permintaan-kerja.detail', $tiket) }}" wire:navigate class="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-0.5 px-4 py-3.5 text-ink no-underline hover:text-ink">
                                <span class="min-w-0">
                                    <span class="num me-2 teks-bantuan" data-test="nomor-tiket">{{ $tiket->nomor_tiket }}</span>
                                    <span class="font-[650]">{{ $tiket->fasilitas->nama_fasilitas }}, {{ $this->lokasiTiket($tiket) }}</span>
                                </span>
                                <span class="num justify-self-end teks-badan-kuat" data-test="biaya-selesai">{{ Format::rupiah($tiket->biaya_perbaikan) }}</span>
                                <span class="teks-bantuan">Selesai {{ $this->tanggalPendek($this->waktuStatus($tiket, StatusTiket::Selesai)) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</section>
