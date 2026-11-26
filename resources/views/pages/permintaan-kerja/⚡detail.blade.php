<?php

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use App\Exceptions\StatusTiketBerubah;
use App\Models\TiketKeluhan;
use App\Support\Format;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pekerjaan')] class extends Component {
    /** Batas atas biaya perbaikan (rupiah), untuk mencegah salah ketik. Kolom numeric(12,2) memuatnya. */
    private const BIAYA_MAKSIMAL = 100000000;

    #[Locked]
    public int $tiketId = 0;

    /** Isian Biaya Perbaikan sebagaimana diketik ("1.250.000"). */
    public string $biaya = '';

    /**
     * Tiket vendor lain atau milik peran lain ditolak 403 oleh TiketKeluhanPolicy::view (ATB-08).
     */
    public function mount(TiketKeluhan $tiket): void
    {
        $this->authorize('view', $tiket);

        $this->tiketId = $tiket->id;
    }

    /**
     * Tiket beserta fasilitas, kamar, dan riwayat. Relasi penghuni tidak pernah dimuat (F-19 butir 2).
     */
    #[Computed]
    public function tiket(): TiketKeluhan
    {
        return TiketKeluhan::query()
            ->with(['fasilitas.kamar', 'riwayatStatus'])
            ->findOrFail($this->tiketId);
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
     * Biaya dari isian sebagai bilangan bulat rupiah, atau null bila isian tidak valid.
     */
    private function biayaDariIsian(): ?int
    {
        $isian = trim((string) preg_replace('/^\s*Rp\.?\s*/i', '', $this->biaya));

        // Hanya angka dengan pemisah ribuan: huruf dan tanda minus ditolak, bukan dibuang diam-diam.
        if (preg_match('/^\d[\d.,]*$/', $isian) !== 1) {
            return null;
        }

        return Format::parseRupiah($isian);
    }

    /**
     * Biaya untuk ringkasan dialog konfirmasi, "Rp150.000"; "-" bila isian belum valid.
     */
    #[Computed]
    public function ringkasanBiaya(): string
    {
        return Format::rupiah($this->biayaDariIsian());
    }

    private function validasiBiaya(): int
    {
        $this->biaya = trim($this->biaya);

        $this->validate(
            [
                'biaya' => [
                    'required',
                    function (string $atribut, mixed $nilai, Closure $gagal) {
                        $biaya = $this->biayaDariIsian();

                        if ($biaya === null || $biaya < 0) {
                            $gagal('Biaya harus angka rupiah, 0 atau lebih.');
                        } elseif ($biaya > self::BIAYA_MAKSIMAL) {
                            $gagal('Biaya paling banyak Rp100.000.000.');
                        }
                    },
                ],
            ],
            ['biaya.required' => 'Isi biaya perbaikan.'],
        );

        return $this->biayaDariIsian();
    }

    /**
     * Vendor mulai mengerjakan dari halaman detail (F-19).
     */
    public function mulai(): void
    {
        $tiket = TiketKeluhan::query()->findOrFail($this->tiketId);

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

    /**
     * Klik Selesai di form: biaya diperiksa, lalu dialog konfirmasi dibuka (F-20 butir 2). Belum ada yang disimpan.
     */
    public function periksa(): void
    {
        $tiket = TiketKeluhan::query()->findOrFail($this->tiketId);

        $this->authorize('kerjakan', $tiket);

        if ($tiket->status_tiket !== StatusTiket::Dikerjakan) {
            Flux::toast(variant: 'warning', text: StatusTiketBerubah::PESAN);
            $this->segarkan();

            return;
        }

        $this->validasiBiaya();

        Flux::modal('selesaikan-tiket')->show();
    }

    /**
     * Konfirmasi di dialog: tiket selesai, biaya tercatat, antrean ERP dibuat (F-20). Berhasil: kembali ke daftar.
     */
    public function selesaikan(): void
    {
        $tiket = TiketKeluhan::query()->findOrFail($this->tiketId);

        $this->authorize('kerjakan', $tiket);

        try {
            $biaya = $this->validasiBiaya();
        } catch (ValidationException $e) {
            Flux::modal('selesaikan-tiket')->close();

            throw $e;
        }

        try {
            $tiket->selesaikan($biaya, auth()->user());
        } catch (StatusTiketBerubah $e) {
            Flux::modal('selesaikan-tiket')->close();
            Flux::toast(variant: 'warning', text: $e->getMessage());
            $this->segarkan();

            return;
        }

        // Toast dibawa lewat session flash karena halaman daftar dimuat ulang oleh redirect.
        session()->flash('toast', $tiket->nomor_tiket.' selesai. Biaya '.Format::rupiah($biaya).' tercatat.');

        $this->redirectRoute('permintaan-kerja.index', navigate: true);
    }

    private function segarkan(): void
    {
        unset($this->tiket);
        $this->reset('biaya');
        $this->resetErrorBag();
    }
}; ?>

@php
    $tiket = $this->tiket;
    $zona = config('app.timezone');
    $tanggal = fn ($waktu, string $format) => $waktu->setTimezone($zona)->locale('id')->translatedFormat($format);
    $ditugaskanPada = $tiket->riwayatStatus->firstWhere('status_sesudah', StatusTiket::Ditugaskan)?->waktu_perubahan;
    $selesaiPada = $tiket->riwayatStatus->firstWhere('status_sesudah', StatusTiket::Selesai)?->waktu_perubahan;
    $riwayat = $tiket->riwayatStatus->filter(fn ($baris) => $baris->status_sesudah !== StatusTiket::Diajukan);
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="{{ route('permintaan-kerja.index') }}" wire:navigate class="tautan-aksi w-fit max-lg:hidden">Pekerjaan</a>
        <div class="flex items-start gap-1">
            <a
                href="{{ route('permintaan-kerja.index') }}"
                wire:navigate
                aria-label="Kembali ke pekerjaan"
                class="tombol-ikon -ms-2 inline-flex size-11 shrink-0 items-center justify-center text-ink lg:hidden"
            >
                <flux:icon.arrow-left class="size-5" />
            </a>
            <div class="flex min-w-0 flex-col gap-1.5">
                <h1 class="teks-judul num m-0" data-test="nomor-tiket">{{ $tiket->nomor_tiket }}</h1>
                <p class="m-0 break-words teks-badan-kuat" data-test="jenis-kerusakan">{{ $tiket->jenis_kerusakan }}</p>
                <div class="flex items-center gap-2.5">
                    <x-meter-tiket :status="$tiket->status_tiket" />
                    <x-status :jenis="$this->jenisStatus($tiket->status_tiket)" data-test="status-tiket">{{ $tiket->status_tiket->label() }}</x-status>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-6 rounded-2xl border border-border bg-surface p-4 sm:p-6">
        <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5" data-test="data-tiket">
            <dt class="text-ink-muted">Lokasi</dt>
            <dd class="m-0" data-test="lokasi-tiket">{{ $this->lokasiTiket($tiket) }}</dd>

            <dt class="text-ink-muted">Fasilitas</dt>
            <dd class="m-0 break-words" data-test="fasilitas-tiket">{{ $tiket->fasilitas->nama_fasilitas }}</dd>

            <dt class="text-ink-muted">Deskripsi</dt>
            <dd class="m-0 whitespace-pre-line break-words" data-test="deskripsi-tiket">{{ $tiket->deskripsi }}</dd>

            <dt class="text-ink-muted">Instruksi</dt>
            <dd class="m-0 whitespace-pre-line break-words" data-test="instruksi-tiket">{{ $tiket->catatan_instruksi }}</dd>

            <dt class="text-ink-muted">Ditugaskan</dt>
            <dd class="m-0" data-test="tanggal-ditugaskan">{{ $ditugaskanPada ? $tanggal($ditugaskanPada, 'j F Y') : '-' }}</dd>

            @if ($tiket->status_tiket === StatusTiket::Selesai)
                <dt class="text-ink-muted">Biaya Perbaikan</dt>
                <dd class="num m-0" data-test="biaya-perbaikan">{{ Format::rupiah($tiket->biaya_perbaikan) }}</dd>

                <dt class="text-ink-muted">Selesai</dt>
                <dd class="m-0" data-test="tanggal-selesai">{{ $selesaiPada ? $tanggal($selesaiPada, 'j F Y') : '-' }}</dd>
            @endif
        </dl>

        <div class="border-t border-divider pt-5">
            <h2 class="m-0 mb-3 teks-label">Riwayat Status</h2>
            <ol class="m-0 flex list-none flex-col gap-2.5 p-0" data-test="riwayat-status">
                @foreach ($riwayat as $baris)
                    <li wire:key="riwayat-{{ $baris->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 teks-badan">
                        <span class="font-[650]">{{ $baris->status_sesudah->label() }}</span>
                        <span class="num teks-bantuan">{{ Format::tanggalJam($baris->waktu_perubahan) }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    @if ($tiket->status_tiket === StatusTiket::Ditugaskan)
        <div class="flex">
            <flux:button
                type="button"
                variant="primary"
                wire:click="mulai"
                wire:loading.attr="disabled"
                wire:target="mulai"
                class="tombol-utama max-sm:w-full"
                data-test="mulai-tiket"
            >Mulai</flux:button>
        </div>
    @elseif ($tiket->status_tiket === StatusTiket::Dikerjakan)
        <form wire:submit="periksa" novalidate class="flex flex-col gap-[18px] rounded-2xl border border-border bg-surface p-4 sm:p-6" data-test="form-selesaikan">
            <h2 class="m-0 teks-bagian">Selesaikan</h2>

            <flux:field class="sm:max-w-[320px]">
                <flux:label>Biaya Perbaikan</flux:label>
                <flux:input.group>
                    <flux:input.group.prefix class="font-semibold text-ink-muted">Rp</flux:input.group.prefix>
                    <flux:input
                        wire:model="biaya"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                        class:input="num"
                        x-data
                        x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                        data-test="input-biaya"
                    />
                </flux:input.group>
                <flux:description>Isi 0 bila tanpa biaya.</flux:description>
                <flux:error name="biaya" />
            </flux:field>

            <div class="flex flex-col gap-2 border-t border-divider pt-[18px] sm:flex-row">
                <flux:button type="submit" variant="primary" class="tombol-utama max-sm:w-full" wire:loading.attr="disabled" wire:target="periksa" data-test="selesai-tiket">Selesai</flux:button>
            </div>
        </form>

        <x-dialog-konfirmasi
            name="selesaikan-tiket"
            :judul="'Selesaikan '.$tiket->nomor_tiket.'?'"
            label-aksi="Selesai"
            variant="biasa"
            wire:click="selesaikan"
            data-test="konfirmasi-selesai"
        >
            <x-slot:ringkasan>
                <dt>Biaya</dt>
                <dd class="num">{{ $this->ringkasanBiaya }}</dd>
            </x-slot:ringkasan>

            <x-slot:akibat>Pekerjaan akan ditandai selesai dan biaya dicatat. Setelah ini tidak bisa diubah.</x-slot:akibat>
        </x-dialog-konfirmasi>
    @endif
</section>
