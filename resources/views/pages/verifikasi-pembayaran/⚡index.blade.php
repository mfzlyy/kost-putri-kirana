<?php

use App\Exceptions\PembayaranSudahDiputuskan;
use App\Http\Middleware\PastikanPeran;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Verifikasi')] class extends Component {
    /** ID pembayaran yang dibuka (?bukti=). Nilai yang tidak ada di antrean diabaikan. */
    #[Url(as: 'bukti')]
    public string $bukti = '';

    /** Alasan penolakan di dialog Tolak bukti. */
    public string $alasan = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        // viewAny juga mengizinkan penghuni (riwayat milik sendiri); halaman ini khusus pengelola.
        abort_unless(auth()->user()->can('verifikasi', Pembayaran::class), 403, PastikanPeran::PESAN_DITOLAK);
    }

    #[Computed]
    public function hariIni(): CarbonImmutable
    {
        return Kontrak::hariIni();
    }

    /**
     * Bukti yang menunggu, unggahan terlama di atas (F-13 butir 1).
     *
     * @return Collection<int, Pembayaran>
     */
    #[Computed]
    public function antrean(): Collection
    {
        return Pembayaran::query()
            ->menunggu()
            ->with([
                'tagihan.kontrak.kamar:id,nomor_kamar',
                'tagihan.kontrak.penghuni:id,user_id',
                'tagihan.kontrak.penghuni.user:id,nama',
            ])
            ->orderBy('pembayaran.diunggah_pada')
            ->orderBy('pembayaran.id')
            ->get();
    }

    /**
     * ID tagihan antrean yang tunggakan menurut Tagihan::tunggakan() (ATB-05): satu query untuk seluruh antrean.
     *
     * @return list<int>
     */
    #[Computed]
    public function idTunggakan(): array
    {
        if ($this->antrean->isEmpty()) {
            return [];
        }

        return Tagihan::query()
            ->tunggakan($this->hariIni)
            ->whereIn('tagihan.id', $this->antrean->pluck('tagihan_id'))
            ->pluck('tagihan.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Pilihan eksplisit dari URL; null bila kosong atau tidak ada di antrean (sudah diputuskan, bukan menunggu, tidak dikenal).
     */
    #[Computed]
    public function dipilih(): ?Pembayaran
    {
        if (! ctype_digit($this->bukti)) {
            return null;
        }

        return $this->antrean->firstWhere('id', (int) $this->bukti);
    }

    /**
     * Bukti yang ditampilkan di panel: pilihan eksplisit, atau item teratas (bawaan desktop).
     */
    #[Computed]
    public function panel(): ?Pembayaran
    {
        return $this->dipilih ?? $this->antrean->first();
    }

    /**
     * @return array{mime: string, ekstensi: string, ukuran: int}|null
     */
    #[Computed]
    public function berkas(): ?array
    {
        return $this->panel?->infoBerkasBukti();
    }

    public function pilih(int $id): void
    {
        $this->authorize('verifikasi', Pembayaran::class);

        $this->bukti = $this->antrean->contains('id', $id) ? (string) $id : '';
    }

    public function kembali(): void
    {
        $this->authorize('verifikasi', Pembayaran::class);

        $this->bukti = '';
    }

    private function ambilMenunggu(int $id): ?Pembayaran
    {
        return Pembayaran::query()
            ->menunggu()
            ->with(['tagihan.kontrak.kamar:id,nomor_kamar'])
            ->find($id);
    }

    private function judulBukti(Pembayaran $pembayaran): string
    {
        $tagihan = $pembayaran->tagihan;

        return 'Kamar '.$tagihan->kontrak->kamar?->nomor_kamar.' '.$tagihan->periode->locale('id')->translatedFormat('F');
    }

    /**
     * Antrean dimuat ulang; desktop memilih item teratas berikutnya dan ponsel kembali ke daftar.
     * Angka antrean di menu sidebar ikut diperbarui lewat event window.
     */
    private function segarkan(): void
    {
        $this->bukti = '';
        $this->reset('alasan');
        $this->resetErrorBag();
        unset($this->antrean, $this->idTunggakan, $this->dipilih, $this->panel, $this->berkas);

        $this->dispatch('antrean-verifikasi', jumlah: $this->antrean->count());
    }

    private function sudahDiputuskan(string $pesan): void
    {
        Flux::modal('terima-pembayaran')->close();
        Flux::modal('tolak-bukti')->close();
        Flux::toast(variant: 'warning', text: $pesan);
        $this->segarkan();
    }

    public function bukaTolak(int $id): void
    {
        $pembayaran = $this->ambilMenunggu($id);

        if ($pembayaran === null) {
            $this->authorize('verifikasi', Pembayaran::class);
            $this->sudahDiputuskan(PembayaranSudahDiputuskan::PESAN);

            return;
        }

        $this->authorize('verifikasi', $pembayaran);

        $this->reset('alasan');
        $this->resetErrorBag();
        Flux::modal('tolak-bukti')->show();
    }

    public function terima(int $id): void
    {
        $pembayaran = $this->ambilMenunggu($id);

        if ($pembayaran === null) {
            $this->authorize('verifikasi', Pembayaran::class);
            $this->sudahDiputuskan(PembayaranSudahDiputuskan::PESAN);

            return;
        }

        $this->authorize('verifikasi', $pembayaran);

        try {
            $pembayaran->terima(auth()->user());
        } catch (PembayaranSudahDiputuskan $e) {
            $this->sudahDiputuskan($e->getMessage());

            return;
        }

        Flux::modal('terima-pembayaran')->close();
        Flux::toast(variant: 'success', text: 'Pembayaran '.$this->judulBukti($pembayaran).' diterima.');
        $this->segarkan();
    }

    public function tolak(int $id): void
    {
        $pembayaran = $this->ambilMenunggu($id);

        if ($pembayaran === null) {
            $this->authorize('verifikasi', Pembayaran::class);
            $this->sudahDiputuskan(PembayaranSudahDiputuskan::PESAN);

            return;
        }

        $this->authorize('verifikasi', $pembayaran);

        $this->alasan = trim($this->alasan);

        $this->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Isi alasan penolakan.',
            'alasan.min' => 'Alasan paling sedikit 5 karakter.',
            'alasan.max' => 'Alasan paling banyak 500 karakter.',
        ]);

        try {
            $pembayaran->tolak(auth()->user(), $this->alasan);
        } catch (PembayaranSudahDiputuskan $e) {
            $this->sudahDiputuskan($e->getMessage());

            return;
        }

        Flux::modal('tolak-bukti')->close();
        Flux::toast(variant: 'success', text: 'Bukti '.$this->judulBukti($pembayaran).' ditolak.');
        $this->segarkan();
    }
}; ?>

@php
    $antrean = $this->antrean;
    $jumlah = $antrean->count();
    $panel = $this->panel;
    $adaPilihan = $this->dipilih !== null;
    $zona = config('app.timezone');
    $namaDepan = fn (?string $nama) => explode(' ', trim((string) $nama))[0];
@endphp

<section class="flex w-full flex-col gap-6">
    {{-- Ponsel yang sedang membuka satu bukti tidak memakai kepala halaman; judulnya ada di panel periksa. --}}
    <x-judul-halaman :class="$adaPilihan ? 'max-lg:hidden' : ''" judul="Verifikasi" />

    @if ($jumlah === 0)
        <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Tidak ada bukti yang menunggu.</p>
    @endif

    @if ($panel)
        {{-- siap = false saat halaman dibuka, jadi isi panel pertama tidak beranimasi. --}}
        <div x-data="{ siap: false }" x-init="$nextTick(() => siap = true)" class="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(300px,1fr)_minmax(0,1.5fr)] lg:items-start lg:gap-6">
            {{-- Antrean --}}
            <section aria-label="Antrean bukti bayar" @class(['daftar-klik overflow-hidden rounded-2xl border border-border bg-surface', 'max-lg:hidden' => $adaPilihan])>
                @foreach ($antrean as $item)
                    @php
                        $tagihanItem = $item->tagihan;
                        $kontrakItem = $tagihanItem->kontrak;
                        $telat = in_array($tagihanItem->id, $this->idTunggakan, true);
                        $terpilih = $item->id === $panel->id;
                    @endphp
                    <a
                        wire:key="antrean-{{ $item->id }}"
                        href="{{ route('verifikasi-pembayaran.index', ['bukti' => $item->id]) }}"
                        wire:click.prevent="pilih({{ $item->id }})"
                        @if ($terpilih) aria-current="true" @endif
                        class="baris-pilih grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-0.5 border-t border-row-divider px-4 py-3.5 text-ink no-underline first:border-t-0 hover:text-ink"
                        data-test="item-antrean"
                    >
                        <span class="teks-badan-kuat">Kamar {{ $kontrakItem->kamar?->nomor_kamar }}, {{ $namaDepan($kontrakItem->penghuni?->user?->nama) }}</span>
                        <span class="num teks-badan-kuat">{{ Format::rupiah($tagihanItem->nominal) }}</span>
                        <span class="teks-bantuan">{{ $tagihanItem->periode->locale('id')->translatedFormat('F') }}, diunggah {{ Format::waktuUnggah($item->diunggah_pada) }}</span>
                        <span class="justify-self-end">
                            @if ($telat)
                                <x-status jenis="telat">Telat {{ (int) $tagihanItem->jatuh_tempo->diffInDays($this->hariIni) }} Hari</x-status>
                            @endif
                        </span>
                    </a>
                @endforeach
            </section>

            {{-- Panel periksa --}}
            @php
                $tagihan = $panel->tagihan;
                $kontrak = $tagihan->kontrak;
                $nomorKamar = $kontrak->kamar?->nomor_kamar;
                $bulan = $tagihan->periode->locale('id')->translatedFormat('F');
                $telatPanel = in_array($tagihan->id, $this->idTunggakan, true);
                $berkas = $this->berkas;
                $urlBukti = route('bukti-bayar.tampil', $panel->id);
                $diunggah = $panel->diunggah_pada->setTimezone($zona)->locale('id');
            @endphp
            <section
                aria-labelledby="judul-panel"
                wire:loading.class="opacity-60"
                wire:target="pilih"
                @class(['transisi-opasitas rounded-2xl border border-border bg-surface p-4 lg:p-6', 'max-lg:hidden' => ! $adaPilihan])
                data-test="panel-periksa"
            >
                <div wire:key="panel-{{ $panel->id }}" x-init="siap && $el.classList.add('panel-masuk')" class="flex flex-col gap-5" data-test="isi-panel">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-1">
                            <a
                                href="{{ route('verifikasi-pembayaran.index') }}"
                                wire:click.prevent="kembali"
                                aria-label="Kembali"
                                class="tombol-ikon -ms-2 inline-flex size-11 shrink-0 items-center justify-center text-ink lg:hidden"
                            >
                                <flux:icon.arrow-left class="size-5" />
                            </a>
                            <div class="min-w-0">
                                <h2 id="judul-panel" class="m-0 teks-bagian">Kamar {{ $nomorKamar }}, Tagihan {{ $bulan }}</h2>
                                <p class="m-0 mt-1 text-ink-muted">{{ $kontrak->penghuni?->user?->nama }}</p>
                            </div>
                        </div>
                        <x-status jenis="tunggu">Menunggu</x-status>
                    </div>

                    <div class="flex flex-col gap-5 lg:flex-row lg:flex-wrap lg:items-stretch">
                        <figure class="m-0 flex min-h-[200px] flex-col items-center justify-center gap-2.5 rounded-xl border border-divider bg-surface-disabled p-4 text-center text-ink-muted lg:min-h-[280px] lg:flex-[1_1_220px]" data-test="pratinjau">
                            @if ($berkas === null)
                                <flux:icon.document class="size-10" />
                                <figcaption class="teks-badan" data-test="bukti-tidak-tersedia">Bukti tidak tersedia.</figcaption>
                            @else
                                @if ($berkas['ekstensi'] === 'pdf')
                                    <flux:icon.document-text class="size-10" />
                                    <span class="teks-badan-kuat">Berkas PDF</span>
                                @else
                                    <img
                                        src="{{ $urlBukti }}"
                                        alt="Bukti transfer Kamar {{ $nomorKamar }} {{ $bulan }}"
                                        class="max-h-[420px] w-full rounded-lg object-contain"
                                    >
                                @endif
                                <figcaption class="teks-badan">{{ strtoupper($berkas['ekstensi']) }}, {{ Format::ukuranBerkas($berkas['ukuran']) }}</figcaption>
                                <a href="{{ $urlBukti }}" target="_blank" rel="noopener" class="tautan-aksi teks-tombol-kecil">Buka</a>
                            @endif
                        </figure>

                        <div class="flex min-w-0 flex-col gap-[18px] lg:flex-[1_1_240px]">
                            <div class="@container">
                                <p class="m-0 text-ink-muted">Nominal</p>
                                <p class="num m-0 mt-0.5 teks-angka-utama" data-test="nominal-tagihan">{{ Format::rupiah($tagihan->nominal) }}</p>
                            </div>
                            <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5">
                                <dt class="text-ink-muted">Tanggal Bayar</dt>
                                <dd class="m-0">{{ $panel->tanggal_bayar->locale('id')->translatedFormat('j F Y') }}</dd>

                                <dt class="text-ink-muted">Diunggah</dt>
                                <dd class="m-0">{{ $diunggah->translatedFormat('j F') }}, {{ $diunggah->format('H.i') }}</dd>

                                <dt class="text-ink-muted">Jatuh Tempo</dt>
                                <dd class="m-0 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span>{{ $tagihan->jatuh_tempo->locale('id')->translatedFormat('j F') }}</span>
                                    @if ($telatPanel)
                                        <x-status jenis="telat">Telat {{ (int) $tagihan->jatuh_tempo->diffInDays($this->hariIni) }} Hari</x-status>
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-divider pt-5 lg:flex-row lg:flex-wrap">
                        <flux:modal.trigger name="terima-pembayaran">
                            <x-tombol class="w-full lg:w-auto" data-test="buka-terima">Terima</x-tombol>
                        </flux:modal.trigger>
                        <x-tombol
                            varian="bahaya"
                            wire:click="bukaTolak({{ $panel->id }})"
                            class="w-full lg:w-auto"
                            data-test="buka-tolak"
                        >Tolak</x-tombol>
                    </div>
                </div>
            </section>
        </div>

        <x-dialog-konfirmasi
            name="terima-pembayaran"
            :judul="'Terima Pembayaran Kamar '.$nomorKamar.'?'"
            label-aksi="Terima"
            variant="biasa"
            wire:click="terima({{ $panel->id }})"
            data-test="konfirmasi-terima"
        >
            <x-slot:ringkasan>
                <dt>Penghuni</dt>
                <dd>{{ $kontrak->penghuni?->user?->nama }}</dd>
                <dt>Nominal</dt>
                <dd class="num">{{ Format::rupiah($tagihan->nominal) }}</dd>
                <dt>Tanggal Bayar</dt>
                <dd>{{ $panel->tanggal_bayar->locale('id')->translatedFormat('j F Y') }}</dd>
            </x-slot:ringkasan>

            <x-slot:akibat>Tagihan ini akan ditandai lunas dan dicatat ke pembukuan. Keputusan ini tidak bisa dibatalkan.</x-slot:akibat>
        </x-dialog-konfirmasi>

        <x-dialog-konfirmasi
            name="tolak-bukti"
            :judul="'Tolak Bukti Kamar '.$nomorKamar.'?'"
            label-aksi="Tolak"
            variant="bahaya"
            wire:click="tolak({{ $panel->id }})"
            data-test="konfirmasi-tolak"
        >
            <x-slot:ringkasan>
                <dt>Penghuni</dt>
                <dd>{{ $kontrak->penghuni?->user?->nama }}</dd>
                <dt>Nominal</dt>
                <dd class="num">{{ Format::rupiah($tagihan->nominal) }}</dd>
            </x-slot:ringkasan>

            <x-slot:akibat>Penghuni akan melihat alasan ini dan bisa mengunggah bukti baru.</x-slot:akibat>

            <x-slot:isi>
                <div>
                    <label for="alasan" class="mb-1.5 block teks-label">Alasan</label>
                    <textarea
                        id="alasan"
                        wire:model="alasan"
                        rows="4"
                        @error('alasan') aria-invalid="true" aria-describedby="galat-alasan" @enderror
                        @class([
                            'min-h-[110px] w-full rounded-lg border bg-surface px-3 py-2.5 text-ink teks-isian',
                            'border-border-strong' => ! $errors->has('alasan'),
                            'border-red-400' => $errors->has('alasan'),
                        ])
                    ></textarea>
                    <p class="m-0 mt-1.5 teks-bantuan">Tampil ke penghuni. Contoh: Nominal kurang Rp100.000.</p>
                    @error('alasan')
                        <p id="galat-alasan" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-alasan">{{ $message }}</p>
                    @enderror
                </div>
            </x-slot:isi>
        </x-dialog-konfirmasi>
    @endif
</section>
