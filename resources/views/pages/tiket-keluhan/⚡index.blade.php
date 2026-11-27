<?php

use App\Enums\LokasiKeluhan;
use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusTiket;
use App\Exceptions\TiketSudahDitugaskan;
use App\Exceptions\VendorTidakAktif;
use App\Http\Middleware\PastikanPeran;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Keluhan')] class extends Component {
    use WithPagination;

    /** Filter status: semua, perlu (bawaan), diajukan, ditugaskan, dikerjakan, selesai. Nilai lain dianggap bawaan. */
    #[Url(as: 'status')]
    public string $status = 'perlu';

    /** ID tiket yang dibuka (?tiket=). Nilai yang tidak ada atau tidak lolos Policy diabaikan. */
    #[Url(as: 'tiket')]
    public string $tiket = '';

    /** Isian form Tugaskan Vendor. */
    public string $vendorId = '';

    public string $jenisKerusakan = '';

    public string $instruksi = '';

    /** Diisi browser: layar >= 1024px, tempat daftar dan panel detail tampil berdampingan. */
    public bool $layarLebar = false;

    private const FILTER = ['semua', 'perlu', 'diajukan', 'ditugaskan', 'dikerjakan', 'selesai'];

    private const STATUS_PERLU = ['diajukan', 'ditugaskan', 'dikerjakan'];

    public function mount(): void
    {
        $this->authorize('viewAny', TiketKeluhan::class);

        // viewAny juga mengizinkan penghuni dan vendor (daftar milik sendiri); halaman ini khusus kantor.
        abort_unless(
            in_array(auth()->user()->peran, [PeranPengguna::Pengelola, PeranPengguna::Pemilik], true),
            403,
            PastikanPeran::PESAN_DITOLAK,
        );
    }

    #[Computed]
    public function filterAktif(): string
    {
        return in_array($this->status, self::FILTER, true) ? $this->status : 'perlu';
    }

    /**
     * Jumlah tiket per chip filter, dari satu query.
     *
     * @return array{semua: int, perlu: int, diajukan: int, ditugaskan: int, dikerjakan: int, selesai: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $perStatus = TiketKeluhan::query()
            ->selectRaw('status_tiket, count(*) as jumlah')
            ->groupBy('status_tiket')
            ->pluck('jumlah', 'status_tiket');

        $ambil = fn (string $nama): int => (int) ($perStatus[$nama] ?? 0);

        return [
            'semua' => (int) $perStatus->sum(),
            'perlu' => array_sum(array_map($ambil, self::STATUS_PERLU)),
            'diajukan' => $ambil('diajukan'),
            'ditugaskan' => $ambil('ditugaskan'),
            'dikerjakan' => $ambil('dikerjakan'),
            'selesai' => $ambil('selesai'),
        ];
    }

    /**
     * Daftar tiket sesuai filter: diajukan di atas, lalu status lain; terlama di atas (F-17 butir 1 dan 4).
     */
    #[Computed]
    public function halaman(): LengthAwarePaginator
    {
        $query = TiketKeluhan::query()
            ->with(['fasilitas.kamar', 'penghuni.user:id,nama', 'vendor:id,nama_vendor'])
            ->antreanKantor();

        match ($this->filterAktif) {
            'semua' => null,
            'perlu' => $query->whereIn('tiket_keluhan.status_tiket', self::STATUS_PERLU),
            default => $query->where('tiket_keluhan.status_tiket', $this->filterAktif),
        };

        return $query->paginate(20);
    }

    /**
     * Pilihan eksplisit dari URL; null bila kosong, tidak ada, atau tidak lolos Policy view.
     * Dimuat langsung dari id (tidak harus ada di halaman daftar saat ini) beserta riwayat statusnya.
     */
    #[Computed]
    public function dipilih(): ?TiketKeluhan
    {
        if (! ctype_digit($this->tiket)) {
            return null;
        }

        $tiket = $this->muatTiket((int) $this->tiket);

        return $tiket !== null && auth()->user()->can('view', $tiket) ? $tiket : null;
    }

    /**
     * Vendor aktif untuk form penugasan.
     *
     * @return Collection<int, Vendor>
     */
    #[Computed]
    public function vendorAktif(): Collection
    {
        return Vendor::query()->aktif()->get(['vendor.id', 'vendor.nama_vendor', 'vendor.jenis_layanan']);
    }

    private function muatTiket(int $id): ?TiketKeluhan
    {
        return TiketKeluhan::query()
            ->with(['fasilitas.kamar', 'penghuni.user:id,nama', 'vendor', 'riwayatStatus.diubahOleh:id,nama'])
            ->find($id);
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

    public function pilihStatus(string $nilai): void
    {
        $this->status = in_array($nilai, self::FILTER, true) ? $nilai : 'perlu';
        $this->tiket = '';
        $this->resetPage();
        $this->kosongkanForm();
    }

    public function pilih(int $id): void
    {
        $tiket = TiketKeluhan::query()->find($id);

        $this->tiket = $tiket !== null && auth()->user()->can('view', $tiket) ? (string) $id : '';
        $this->kosongkanForm();
    }

    public function kembali(): void
    {
        $this->tiket = '';
        $this->kosongkanForm();
    }

    private function kosongkanForm(): void
    {
        $this->reset('vendorId', 'jenisKerusakan', 'instruksi');
        $this->resetErrorBag();
    }

    /**
     * Daftar dan angka dimuat ulang; angka antrean di menu sidebar ikut diperbarui lewat event window.
     */
    private function segarkan(): void
    {
        unset($this->hitungan, $this->halaman, $this->dipilih, $this->vendorAktif);

        $this->dispatch('antrean-keluhan', jumlah: $this->hitungan['diajukan']);
    }

    /**
     * Setelah penugasan. Desktop: panel tetap terbuka pada tiket teratas berikutnya di daftar yang sedang disaring, dan
     * tertutup bila tidak ada lagi. Ponsel: tetap di tiket yang baru ditugaskan.
     */
    private function lanjutSetelahTugaskan(int $id): void
    {
        $berikutnya = $id;

        if ($this->layarLebar) {
            // Halaman terakhir bisa kosong setelah tiketnya keluar dari filter.
            if ($this->halaman->isEmpty() && $this->halaman->currentPage() > 1) {
                $this->setPage($this->halaman->lastPage());
                unset($this->halaman);
            }

            $berikutnya = $this->halaman->getCollection()->first(fn (TiketKeluhan $tiket) => $tiket->id !== $id)?->id;
        }

        $this->tiket = $berikutnya === null ? '' : (string) $berikutnya;
        unset($this->dipilih);
    }

    /**
     * @return array<string, mixed>
     */
    private function aturanTugaskan(): array
    {
        $idVendorAktif = User::query()
            ->where('status_akun', StatusAkun::Aktif->value)
            ->where('peran', PeranPengguna::Vendor->value)
            ->select('id');

        return [
            'vendorId' => ['required', 'integer', Rule::exists('vendor', 'id')->where(fn ($q) => $q->whereIn('user_id', $idVendorAktif))],
            'jenisKerusakan' => ['required', 'string', 'max:100'],
            'instruksi' => ['required', 'string', 'max:1000'],
        ];
    }

    public function tugaskan(int $id): void
    {
        $tiket = TiketKeluhan::query()->findOrFail($id);

        $this->authorize('tugaskan', $tiket);

        $this->jenisKerusakan = trim($this->jenisKerusakan);
        $this->instruksi = trim($this->instruksi);

        $this->validate($this->aturanTugaskan(), [
            'vendorId.required' => 'Pilih vendor.',
            'vendorId.integer' => 'Vendor tidak ditemukan.',
            'vendorId.exists' => 'Vendor tidak ditemukan.',
            'jenisKerusakan.required' => 'Isi jenis kerusakan.',
            'jenisKerusakan.max' => 'Jenis kerusakan paling banyak 100 karakter.',
            'instruksi.required' => 'Isi instruksi untuk vendor.',
            'instruksi.max' => 'Instruksi paling banyak 1.000 karakter.',
        ]);

        $vendor = Vendor::query()->findOrFail((int) $this->vendorId);

        try {
            $tiket->tugaskanVendor($vendor, $this->jenisKerusakan, $this->instruksi, auth()->user());
        } catch (TiketSudahDitugaskan|VendorTidakAktif $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
            $this->segarkan();

            return;
        }

        Flux::toast(variant: 'success', text: $tiket->nomor_tiket.' ditugaskan ke '.$vendor->nama_vendor.'.');

        $this->kosongkanForm();
        $this->segarkan();
        $this->lanjutSetelahTugaskan($tiket->id);
    }
}; ?>

@php
    $halaman = $this->halaman;
    $hitungan = $this->hitungan;
    // Panel hanya ada bila pengguna memilih tiket (klik atau ?tiket=).
    $panel = $this->dipilih;
    $adaPilihan = $panel !== null;
    $zona = config('app.timezone');
    $tanggal = fn ($waktu, string $format) => $waktu->setTimezone($zona)->locale('id')->translatedFormat($format);
    $bisaTugaskan = $panel !== null && $panel->status_tiket === StatusTiket::Diajukan && auth()->user()->can('tugaskan', $panel);
    $filter = [
        ['nilai' => 'semua', 'label' => 'Semua'],
        ['nilai' => 'perlu', 'label' => 'Perlu Ditangani'],
        ['nilai' => 'diajukan', 'label' => 'Diajukan'],
        ['nilai' => 'ditugaskan', 'label' => 'Ditugaskan'],
        ['nilai' => 'dikerjakan', 'label' => 'Dikerjakan'],
        ['nilai' => 'selesai', 'label' => 'Selesai'],
    ];
@endphp

<section class="flex w-full flex-col gap-6" x-data="daftarPanel('tiket')" x-on:keydown.escape.window="tekanEsc($event)">
    {{-- Ponsel yang sedang membuka satu tiket tidak memakai kepala halaman, chip, dan daftar; judulnya ada di panel detail. --}}
    <x-judul-halaman :class="$adaPilihan ? 'max-lg:hidden' : ''" judul="Keluhan" />

    <x-chip-filter.baris label="Saring status" :class="$adaPilihan ? 'baris-filter max-lg:hidden' : 'baris-filter'">
        @foreach ($filter as $chip)
            <x-chip-filter
                wire:key="chip-{{ $chip['nilai'] }}"
                :aktif="$this->filterAktif === $chip['nilai']"
                :jumlah="$hitungan[$chip['nilai']]"
                wire:click="pilihStatus('{{ $chip['nilai'] }}')"
            >{{ $chip['label'] }}</x-chip-filter>
        @endforeach
    </x-chip-filter.baris>

    {{-- Tanpa pilihan: daftar selebar konten. Dengan pilihan (desktop): daftar 40%, panel 60%. --}}
    <div wire:loading.class="opacity-60" wire:target="pilihStatus,gotoPage,nextPage,previousPage" @class(['transisi-opasitas flex flex-col gap-4', 'lg:grid lg:grid-cols-[minmax(280px,2fr)_minmax(0,3fr)] lg:items-start lg:gap-6' => $adaPilihan])>
        {{-- Daftar --}}
        <div @class(['flex min-w-0 flex-col gap-4', 'max-lg:hidden' => $adaPilihan])>
            @if ($halaman->isEmpty())
                <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">
                    {{ $hitungan['semua'] === 0 ? 'Belum ada keluhan.' : 'Tidak ada tiket yang sesuai filter.' }}
                </p>
            @else
                <section aria-label="Daftar tiket keluhan" class="daftar-klik overflow-hidden rounded-2xl border border-border bg-surface" data-test="daftar-tiket">
                    @foreach ($halaman as $item)
                        @php
                            $terpilih = $item->id === $panel?->id;
                        @endphp
                        <a
                            wire:key="tiket-{{ $item->id }}"
                            href="{{ route('tiket-keluhan.index', ['tiket' => $item->id]) }}"
                            x-on:click.prevent="buka({{ $item->id }})"
                            data-item="{{ $item->id }}"
                            @if ($terpilih) aria-current="true" @endif
                            class="baris-pilih grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-0.5 border-t border-row-divider px-4 py-3.5 text-ink no-underline first:border-t-0 hover:text-ink"
                            data-test="item-tiket"
                        >
                            <span class="min-w-0">
                                <span class="num me-2 teks-bantuan" data-test="nomor-tiket">{{ $item->nomor_tiket }}</span>
                                <span class="teks-badan-kuat">{{ $item->fasilitas->nama_fasilitas }}</span>
                            </span>
                            <x-status :jenis="$this->jenisStatus($item->status_tiket)">{{ $item->status_tiket->label() }}</x-status>
                            <span class="teks-bantuan">{{ $this->lokasiTiket($item) }}, {{ $tanggal($item->tanggal_lapor, 'j M') }}</span>
                            <span class="justify-self-end teks-bantuan">{{ $item->vendor?->nama_vendor ?? 'Belum Ada Vendor' }}</span>
                        </a>
                    @endforeach
                </section>
            @endif

            {{ $halaman->links() }}
        </div>

        {{-- Panel detail --}}
        @if ($adaPilihan)
            @php
                $riwayat = $panel->riwayatStatus;
                $sudahDitugaskan = $panel->status_tiket !== StatusTiket::Diajukan;
                $durasi = $panel->durasiPenanganan();
                $judulTiket = $panel->fasilitas->nama_fasilitas.', '.$this->lokasiTiket($panel);
            @endphp
            {{-- Panel masuk dari kanan saat baru dibuka; saat pilihan diganti hanya isinya yang beranimasi. --}}
            <section
                wire:key="panel-detail"
                aria-labelledby="judul-panel"
                wire:loading.class="opacity-60"
                wire:target="pilih"
                x-init="siap && $el.classList.add('panel-geser')"
                x-on:animationend.self="$el.classList.remove('panel-geser')"
                class="transisi-opasitas min-w-0 rounded-2xl border border-border bg-surface p-4 lg:p-6"
                data-panel
                data-test="panel-tiket"
            >
                <div wire:key="panel-{{ $panel->id }}" x-init="siap && ! $el.parentElement.classList.contains('panel-geser') && $el.classList.add('panel-masuk')" class="flex flex-col gap-5" data-test="isi-panel">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-1">
                            <a
                                href="{{ route('tiket-keluhan.index') }}"
                                wire:click.prevent="kembali"
                                aria-label="Kembali ke daftar tiket"
                                class="tombol-ikon -ms-2 inline-flex size-11 shrink-0 items-center justify-center text-ink lg:hidden"
                            >
                                <flux:icon.arrow-left class="size-5" />
                            </a>
                            <div class="min-w-0">
                                <p class="num m-0 teks-bantuan" data-test="nomor-panel">{{ $panel->nomor_tiket }}</p>
                                <h2 id="judul-panel" tabindex="-1" class="m-0 mt-0.5 teks-bagian">{{ $judulTiket }}</h2>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <x-meter-tiket :status="$panel->status_tiket" />
                            <x-status :jenis="$this->jenisStatus($panel->status_tiket)">{{ $panel->status_tiket->label() }}</x-status>
                            <button type="button" x-on:click="tutup()" aria-label="Tutup detail" class="tombol-ikon -my-2 -me-2 flex size-10 shrink-0 items-center justify-center text-ink-muted max-lg:hidden" data-test="tutup-panel">
                                <flux:icon.x-mark class="size-5" />
                            </button>
                        </div>
                    </div>

                    <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5" data-test="data-tiket">
                        <dt class="text-ink-muted">Pelapor</dt>
                        <dd class="m-0">{{ $panel->penghuni?->user?->nama }}</dd>

                        <dt class="text-ink-muted">Diajukan</dt>
                        <dd class="m-0">{{ $tanggal($panel->tanggal_lapor, 'j M Y') }}, {{ $panel->tanggal_lapor->setTimezone($zona)->format('H.i') }}</dd>

                        <dt class="text-ink-muted">Deskripsi</dt>
                        <dd class="m-0 whitespace-pre-line break-words" data-test="deskripsi-tiket">{{ $panel->deskripsi }}</dd>

                        @if ($sudahDitugaskan)
                            <dt class="text-ink-muted">Vendor</dt>
                            <dd class="m-0" data-test="vendor-tiket">{{ $panel->vendor?->nama_vendor }}, {{ $panel->vendor?->jenis_layanan }}</dd>

                            <dt class="text-ink-muted">Jenis Kerusakan</dt>
                            <dd class="m-0 break-words" data-test="jenis-kerusakan">{{ $panel->jenis_kerusakan }}</dd>

                            <dt class="text-ink-muted">Instruksi</dt>
                            <dd class="m-0 whitespace-pre-line break-words" data-test="instruksi-tiket">{{ $panel->catatan_instruksi }}</dd>
                        @endif

                        @if ($panel->status_tiket === StatusTiket::Selesai)
                            <dt class="text-ink-muted">Biaya Perbaikan</dt>
                            <dd class="num m-0" data-test="biaya-perbaikan">{{ App\Support\Format::rupiah($panel->biaya_perbaikan) }}</dd>

                            @if ($durasi !== null)
                                <dt class="text-ink-muted">Durasi</dt>
                                <dd class="m-0" data-test="durasi-tiket">{{ App\Support\Format::durasi($durasi) }}</dd>
                            @endif
                        @endif
                    </dl>

                    <div class="border-t border-divider pt-5">
                        <h3 class="m-0 mb-3 teks-label">Riwayat Status</h3>
                        <ol class="m-0 flex list-none flex-col gap-2.5 p-0" data-test="riwayat-status">
                            @foreach ($riwayat as $baris)
                                <li wire:key="riwayat-{{ $baris->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 teks-badan">
                                    <span class="teks-badan-kuat">{{ $baris->status_sesudah->label() }}</span>
                                    <span class="num teks-bantuan">{{ App\Support\Format::tanggalJam($baris->waktu_perubahan) }}</span>
                                    <span class="teks-bantuan">{{ $baris->diubahOleh?->nama }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Form hanya dirender untuk pengelola dan tiket diajukan; pemilik tidak mendapat form sama sekali. --}}
                    @if ($bisaTugaskan)
                        @php
                            $vendorAktif = $this->vendorAktif;
                        @endphp
                        <form wire:submit="tugaskan({{ $panel->id }})" novalidate class="flex flex-col gap-[18px] border-t border-divider pt-5" data-test="form-tugaskan">
                            <h3 class="m-0 teks-bagian">Tugaskan Vendor</h3>

                            <fieldset class="m-0 min-w-0 border-0 p-0" @error('vendorId') aria-describedby="galat-vendor" @enderror>
                                <legend class="mb-1.5 p-0 teks-label">Vendor</legend>
                                @if ($vendorAktif->isEmpty())
                                    <p class="m-0 text-ink-muted teks-badan" data-test="vendor-kosong">Belum ada vendor aktif. Tambah di <a href="{{ route('akun.index') }}" wire:navigate>Akun</a>.</p>
                                @else
                                    <div class="flex flex-col gap-2" data-test="daftar-vendor">
                                        @foreach ($vendorAktif as $vendor)
                                            <label
                                                wire:key="vendor-{{ $vendor->id }}"
                                                @class([
                                                    'pilihan relative flex min-h-12 cursor-pointer flex-col justify-center rounded-lg border bg-surface px-3.5 py-2.5 has-[:checked]:border-primary has-[:checked]:bg-primary-container has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary',
                                                    'border-border-strong' => ! $errors->has('vendorId'),
                                                    'border-red-400' => $errors->has('vendorId'),
                                                ])
                                            >
                                                <input
                                                    type="radio"
                                                    name="vendor_id"
                                                    value="{{ $vendor->id }}"
                                                    wire:model="vendorId"
                                                    class="sr-only"
                                                    @error('vendorId') aria-invalid="true" @enderror
                                                >
                                                <span class="teks-badan-kuat">{{ $vendor->nama_vendor }}</span>
                                                <span class="teks-bantuan">{{ $vendor->jenis_layanan }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                @error('vendorId')
                                    <p id="galat-vendor" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-vendor">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <div>
                                <label for="jenis-kerusakan" class="mb-1.5 block teks-label">Jenis Kerusakan</label>
                                <input
                                    id="jenis-kerusakan"
                                    type="text"
                                    wire:model="jenisKerusakan"
                                    placeholder="Contoh: Freon habis"
                                    autocomplete="off"
                                    @error('jenisKerusakan') aria-invalid="true" aria-describedby="galat-jenis" @enderror
                                    @class([
                                        'h-11 w-full rounded-lg border bg-surface px-3 text-ink teks-isian',
                                        'border-border-strong' => ! $errors->has('jenisKerusakan'),
                                        'border-red-400' => $errors->has('jenisKerusakan'),
                                    ])
                                >
                                @error('jenisKerusakan')
                                    <p id="galat-jenis" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-jenis-kerusakan">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="instruksi" class="mb-1.5 block teks-label">Instruksi</label>
                                <textarea
                                    id="instruksi"
                                    wire:model="instruksi"
                                    rows="4"
                                    placeholder="Contoh: Cek freon dan bersihkan filter"
                                    @error('instruksi') aria-invalid="true" aria-describedby="galat-instruksi" @enderror
                                    @class([
                                        'min-h-[96px] w-full resize-y rounded-lg border bg-surface px-3 py-2.5 text-ink teks-isian',
                                        'border-border-strong' => ! $errors->has('instruksi'),
                                        'border-red-400' => $errors->has('instruksi'),
                                    ])
                                ></textarea>
                                @error('instruksi')
                                    <p id="galat-instruksi" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-instruksi">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col lg:flex-row">
                                <x-tombol
                                    type="submit"
                                    :disabled="$vendorAktif->isEmpty()"
                                    class="w-full lg:w-auto"
                                    data-test="tombol-tugaskan"
                                >
                                    <span wire:loading.remove wire:target="tugaskan">Tugaskan</span>
                                    <span wire:loading wire:target="tugaskan">Menugaskan…</span>
                                </x-tombol>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
</section>
