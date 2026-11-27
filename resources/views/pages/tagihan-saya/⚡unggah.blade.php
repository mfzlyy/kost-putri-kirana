<?php

use App\Exceptions\TagihanTidakBisaDiberiBukti;
use App\Models\Kontrak;
use App\Models\Tagihan;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Unggah Bukti')] #[Layout('layouts::app', ['tanpaTabPenghuni' => true])] class extends Component {
    use WithFileUploads {
        _uploadErrored as protected uploadGagalBawaan;
    }

    public const PESAN_BERKAS = 'Berkas harus JPG, PNG, atau PDF dan paling besar 5 MB.';

    #[Locked]
    public int $tagihanId;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $bukti = null;

    /** Tanggal transfer, YYYY-MM-DD. */
    public string $tanggal_bayar = '';

    public function mount(Tagihan $tagihan): void
    {
        $this->tagihanId = $tagihan->id;

        if ($this->tolakBilaTidakBerhak($tagihan)) {
            return;
        }

        $this->tanggal_bayar = Kontrak::hariIni()->toDateString();
    }

    #[Computed]
    public function tagihan(): Tagihan
    {
        return Tagihan::query()->with('kontrak')->findOrFail($this->tagihanId);
    }

    #[Computed]
    public function hariIni(): CarbonImmutable
    {
        return Kontrak::hariIni();
    }

    /**
     * Bukan pemilik: 403. Pemilik tetapi status tagihan tidak lagi belum dibayar: kembali ke Tagihan saya
     * dengan pesan Policy (F-12 butir 4). Mengembalikan true bila pengguna sudah dialihkan.
     */
    private function tolakBilaTidakBerhak(Tagihan $tagihan): bool
    {
        $respons = Gate::inspect('unggahBukti', $tagihan);

        if ($respons->allowed()) {
            return false;
        }

        abort_unless($respons->message() === TagihanTidakBisaDiberiBukti::PESAN, 403);

        $this->alihkanKeDaftar($respons->message(), 'warning');

        return true;
    }

    private function alihkanKeDaftar(string $pesan, string $varian): void
    {
        // Toast dibawa lewat session flash karena halaman Tagihan saya dimuat ulang oleh redirect.
        session()->flash('toast', $pesan);
        session()->flash('toast_varian', $varian);

        $this->redirectRoute('tagihan-saya.index', navigate: true);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'bukti' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                'max:5120',
                // Isi berkas diperiksa langsung: berkas teks yang diberi nama .jpg tidak lolos.
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    if ($nilai instanceof UploadedFile && ! array_key_exists((string) Tagihan::mimeIsiBerkas($nilai), Tagihan::EKSTENSI_BUKTI)) {
                        $gagal(self::PESAN_BERKAS);
                    }
                },
            ],
            'tanggal_bayar' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->hariIni->toDateString()],
        ];
    }

    /**
     * Semua kegagalan berkas memakai satu pesan yang menyebut format dan batas ukuran (F-12 butir 2).
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'bukti.required' => 'Pilih berkas bukti transfer.',
            'bukti.file' => self::PESAN_BERKAS,
            'bukti.mimes' => self::PESAN_BERKAS,
            'bukti.mimetypes' => self::PESAN_BERKAS,
            'bukti.max' => self::PESAN_BERKAS,
            'bukti.uploaded' => self::PESAN_BERKAS,
            'tanggal_bayar.required' => 'Isi tanggal transfer.',
            'tanggal_bayar.date_format' => 'Tanggal transfer tidak valid.',
            'tanggal_bayar.before_or_equal' => 'Tanggal transfer tidak boleh setelah hari ini.',
        ];
    }

    /**
     * Unggahan sementara ditolak server (terlalu besar atau gagal): pesan yang sama dengan validasi akhir.
     */
    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        try {
            $this->uploadGagalBawaan($name, $errorsInJson, $isMultiple);
        } catch (ValidationException) {
            throw ValidationException::withMessages([$name => self::PESAN_BERKAS]);
        }
    }

    /**
     * Berkas yang tidak lolos pemeriksaan langsung dibuang, jadi area unggah kembali ke "Pilih berkas".
     */
    public function updatedBukti(): void
    {
        try {
            $this->validateOnly('bukti');
        } catch (ValidationException $e) {
            $this->buangBerkasSementara();

            throw $e;
        }
    }

    private function buangBerkasSementara(): void
    {
        $this->bukti?->delete();
        $this->bukti = null;
    }

    public function kirim(): void
    {
        $tagihan = $this->tagihan;

        if ($this->tolakBilaTidakBerhak($tagihan)) {
            $this->buangBerkasSementara();

            return;
        }

        $this->validate();

        try {
            $tagihan->unggahBukti($this->bukti, CarbonImmutable::parse($this->tanggal_bayar));
        } catch (TagihanTidakBisaDiberiBukti $e) {
            $this->buangBerkasSementara();
            $this->alihkanKeDaftar($e->getMessage(), 'warning');

            return;
        } catch (Throwable $e) {
            report($e);

            // Status tagihan tidak berubah dan berkas yang sempat tersimpan sudah dihapus; penghuni bisa mencoba lagi.
            $this->buangBerkasSementara();
            $this->addError('bukti', 'Bukti belum terkirim karena ada gangguan. Pilih berkas lagi lalu coba kirim.');

            return;
        }

        $this->buangBerkasSementara();
        $bulan = $tagihan->periode->locale('id')->translatedFormat('F');
        $this->alihkanKeDaftar("Bukti {$bulan} terkirim. Tunggu pengelola mengecek.", 'success');
    }
}; ?>

@php
    $tagihan = $this->tagihan;
    $bulan = $tagihan->periode->locale('id')->translatedFormat('F');
    $nominal = Format::rupiah($tagihan->nominal);
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    {{-- Ponsel: header putih 60px dengan tombol kembali. Desktop: tautan kembali lalu judul. --}}
    <header class="max-md:-mx-4 max-md:-mt-5 max-md:flex max-md:h-[60px] max-md:items-center max-md:gap-1 max-md:border-b max-md:border-divider max-md:bg-surface max-md:px-2 md:flex md:flex-col md:gap-2.5">
        <a
            href="{{ route('tagihan-saya.index') }}"
            wire:navigate
            aria-label="Kembali"
            class="tombol-ikon flex size-11 shrink-0 items-center justify-center text-ink md:hidden"
        >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <a href="{{ route('tagihan-saya.index') }}" wire:navigate class="tautan-aksi w-fit max-md:hidden">Tagihan</a>
        <h1 class="teks-judul m-0">Unggah Bukti</h1>
    </header>

    <div class="flex flex-col gap-5 md:flex-row md:flex-wrap md:items-start md:gap-6">
        {{-- Nominal: baris di ponsel, kartu di desktop (kolom kanan). --}}
        <aside class="flex flex-col gap-3.5 max-md:order-first md:order-2 md:flex-[1_1_240px]">
            <div class="md:rounded-2xl md:border md:border-border md:bg-surface md:p-5">
                <div class="flex items-baseline justify-between gap-3 md:block">
                    <span class="teks-kolom">Nominal</span>
                    <div class="@container min-w-0 flex-1 text-end md:text-start">
                        <div class="num teks-angka-utama" data-test="nominal">{{ $nominal }}</div>
                    </div>
                </div>
            </div>

        </aside>

        <form wire:submit="kirim" novalidate class="flex flex-col gap-[22px] md:order-1 md:flex-[2_1_420px] md:gap-5 md:rounded-2xl md:border md:border-border md:bg-surface md:p-6">
            <div>
                <span class="mb-1.5 block teks-label" id="label-bukti">Bukti</span>
                <label
                    for="bukti"
                    aria-labelledby="label-bukti"
                    class="kartu-klik flex items-center gap-3.5 rounded-xl border border-border bg-surface p-3.5 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary"
                >
                    <span aria-hidden="true" class="flex h-16 w-[52px] shrink-0 items-center justify-center rounded-md border border-divider bg-surface-disabled text-ink-muted">
                        <flux:icon.document class="size-[22px]" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        @if ($bukti)
                            <span class="truncate teks-badan-kuat" data-test="nama-berkas">{{ $bukti->getClientOriginalName() }}</span>
                            <span class="num teks-bantuan" data-test="ukuran-berkas">{{ Format::ukuranBerkas($bukti->getSize()) }}</span>
                        @else
                            <span class="text-ink-muted">Belum ada berkas</span>
                        @endif
                        <span wire:loading wire:target="bukti" class="teks-bantuan" role="status">Mengunggah…</span>
                    </span>
                    <x-tombol elemen="span" varian="sekunder" ukuran="kecil" class="shrink-0">{{ $bukti ? 'Ganti' : 'Pilih' }}</x-tombol>
                    <input
                        id="bukti"
                        type="file"
                        wire:model="bukti"
                        accept=".jpg,.jpeg,.png,.pdf"
                        class="sr-only"
                        @error('bukti') aria-invalid="true" aria-describedby="galat-bukti" @enderror
                    >
                </label>
                <p class="m-0 mt-1.5 teks-bantuan">JPG, PNG, PDF. Maks 5 MB.</p>
                @error('bukti')
                    <p id="galat-bukti" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-bukti">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:max-w-[260px]">
                <label for="tanggal_bayar" class="mb-1.5 block teks-label">Tanggal Transfer</label>
                <input
                    id="tanggal_bayar"
                    type="date"
                    wire:model="tanggal_bayar"
                    max="{{ $this->hariIni->toDateString() }}"
                    @class([
                        'w-full rounded-lg border bg-surface px-3 text-ink teks-isian',
                        'border-border-strong' => ! $errors->has('tanggal_bayar'),
                        'border-red-400' => $errors->has('tanggal_bayar'),
                    ])
                    @error('tanggal_bayar') aria-invalid="true" aria-describedby="galat-tanggal" @enderror
                >
                @error('tanggal_bayar')
                    <p id="galat-tanggal" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-tanggal">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-4 md:border-t md:border-divider md:pt-[18px]">
                <x-tombol
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="bukti,kirim"
                    class="w-full md:w-auto"
                >
                    <span wire:loading.remove wire:target="bukti,kirim">Kirim</span>
                    <span wire:loading wire:target="bukti">Mengunggah…</span>
                    <span wire:loading wire:target="kirim">Mengirim…</span>
                </x-tombol>
            </div>
        </form>
    </div>
</section>
