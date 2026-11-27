<?php

use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tagihan')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Tagihan::class);

        // Toast dibawa lewat session flash karena halaman ini dimuat ulang oleh redirect dari halaman unggah.
        if ($pesan = session('toast')) {
            Flux::toast(variant: session('toast_varian', 'success'), text: $pesan);
        }
    }

    /**
     * Selalu penghuni milik pengguna yang login; tidak pernah menerima ID penghuni dari URL atau browser (ATB-10).
     */
    #[Computed]
    public function penghuni(): ?Penghuni
    {
        return Penghuni::query()
            ->where('user_id', auth()->id())
            ->with(['user:id,nama', 'kontrakAktif.kamar:id,nomor_kamar'])
            ->first();
    }

    #[Computed]
    public function hariIni(): CarbonImmutable
    {
        return Kontrak::hariIni();
    }

    /**
     * Semua tagihan penghuni (termasuk kontrak lama), terbaru dulu.
     */
    #[Computed]
    public function halamanTagihan(): LengthAwarePaginator
    {
        return Tagihan::query()
            ->milikPenghuni($this->penghuni)
            ->with('pembayaranTerakhir')
            ->orderByDesc('tagihan.periode')
            ->orderByDesc('tagihan.id')
            ->paginate(12);
    }

    /**
     * Tagihan yang belum lunas, periode terlama dulu. Jumlahnya kecil, jadi dimuat seluruhnya
     * untuk kartu utama tanpa bergantung pada halaman daftar yang sedang dibuka.
     *
     * @return Collection<int, Tagihan>
     */
    #[Computed]
    public function belumLunas(): Collection
    {
        return Tagihan::query()
            ->milikPenghuni($this->penghuni)
            ->where('tagihan.status_tagihan', '<>', StatusTagihan::Lunas->value)
            ->with('pembayaranTerakhir')
            ->orderBy('tagihan.periode')
            ->orderBy('tagihan.id')
            ->get();
    }

    #[Computed]
    public function adaTagihan(): bool
    {
        return $this->halamanTagihan->total() > 0;
    }

    /**
     * ID tagihan yang tunggakan menurut scope Tagihan::tunggakan() (ATB-05), untuk yang tampil di halaman ini
     * dan di kartu utama: satu query, bukan satu per tagihan.
     *
     * @return list<int>
     */
    #[Computed]
    public function idTunggakan(): array
    {
        $id = $this->halamanTagihan->getCollection()->pluck('id')
            ->merge($this->belumLunas->pluck('id'))
            ->unique()
            ->values()
            ->all();

        if ($id === []) {
            return [];
        }

        return Tagihan::query()
            ->tunggakan($this->hariIni)
            ->whereIn('tagihan.id', $id)
            ->pluck('tagihan.id')
            ->map(fn ($nilai) => (int) $nilai)
            ->all();
    }

    /**
     * Data tampilan satu tagihan.
     *
     * @return array<string, mixed>
     */
    public function baris(Tagihan $tagihan): array
    {
        $zona = config('app.timezone');
        $pembayaran = $tagihan->pembayaranTerakhir;
        $telat = in_array($tagihan->id, $this->idTunggakan, true);
        $ditolak = $tagihan->status_tagihan === StatusTagihan::BelumDibayar
            && $pembayaran?->status_verifikasi === StatusVerifikasi::Ditolak;
        $menunggu = $tagihan->status_tagihan === StatusTagihan::MenungguVerifikasi
            && $pembayaran?->status_verifikasi === StatusVerifikasi::Menunggu;
        $diunggahPada = $menunggu ? $pembayaran->diunggah_pada->setTimezone($zona) : null;

        return [
            'id' => $tagihan->id,
            'periode' => $tagihan->periode,
            'jatuhTempo' => $tagihan->jatuh_tempo,
            'nominal' => (int) round((float) $tagihan->nominal),
            'status' => $tagihan->status_tagihan,
            'telatHari' => $telat ? (int) $tagihan->jatuh_tempo->diffInDays($this->hariIni) : 0,
            'alasanDitolak' => $ditolak ? $pembayaran->alasan_penolakan : null,
            'ditolakPada' => $ditolak ? $pembayaran->diverifikasi_pada?->setTimezone($zona) : null,
            'diunggahPada' => $diunggahPada,
            'terlambatKirim' => $telat && $diunggahPada !== null
                && $diunggahPada->toDateString() > $tagihan->jatuh_tempo->toDateString(),
            'tanggalBayar' => $tagihan->status_tagihan === StatusTagihan::Lunas ? $pembayaran?->tanggal_bayar : null,
        ];
    }

    /**
     * Keadaan kartu utama (F-11): tagihan belum dibayar terlama, atau bukti yang sedang dicek, atau semua lunas,
     * atau belum ada tagihan.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function kartuUtama(): array
    {
        if (! $this->adaTagihan) {
            return ['keadaan' => 'kosong'];
        }

        $belum = $this->belumLunas->where('status_tagihan', StatusTagihan::BelumDibayar);

        if ($belum->isNotEmpty()) {
            return [
                'keadaan' => 'belum',
                'baris' => $this->baris($belum->first()),
                'lainnya' => $belum->count() - 1,
            ];
        }

        $menunggu = $this->belumLunas->where('status_tagihan', StatusTagihan::MenungguVerifikasi);

        if ($menunggu->isNotEmpty()) {
            return ['keadaan' => 'menunggu', 'baris' => $this->baris($menunggu->first())];
        }

        return ['keadaan' => 'lunas'];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function daftar(): Collection
    {
        return $this->halamanTagihan->getCollection()->map(fn (Tagihan $tagihan) => $this->baris($tagihan));
    }
}; ?>

@php
    $penghuni = $this->penghuni;
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    @if ($penghuni === null)
        <x-judul-halaman judul="Tagihan" />
        <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.</p>
    @else
        @php
            $utama = $this->kartuUtama;
        @endphp

        <x-judul-halaman judul="Tagihan" />

        {{-- Kartu utama --}}
        <section class="flex flex-col gap-2.5 rounded-2xl border border-border bg-surface px-[18px] py-5 md:p-6" aria-labelledby="judul-utama" data-test="kartu-utama">
            @if ($utama['keadaan'] === 'belum')
                @php
                    $b = $utama['baris'];
                    $jatuhTempo = $b['jatuhTempo']->locale('id')->translatedFormat('j M');
                    $sisaHari = (int) $this->hariIni->diffInDays($b['jatuhTempo'], false);
                @endphp
                <div class="flex flex-col gap-2.5 md:flex-row md:items-center md:justify-between md:gap-x-8">
                    <div class="@container flex min-w-0 flex-col gap-2.5 md:flex-1 md:gap-1.5">
                        <h2 id="judul-utama" class="m-0 teks-bagian">Tagihan {{ $b['periode']->locale('id')->translatedFormat('F') }}</h2>
                        <div class="num teks-angka-utama">{{ Format::rupiah($b['nominal']) }}</div>

                        @if ($b['telatHari'] > 0)
                            <x-status jenis="telat">Jatuh Tempo {{ $jatuhTempo }}, Telat {{ $b['telatHari'] }} Hari</x-status>
                        @elseif ($sisaHari === 0)
                            <p class="m-0 teks-badan">Jatuh Tempo Hari Ini</p>
                        @else
                            <p class="m-0 teks-badan">Jatuh Tempo <strong class="teks-badan-kuat">{{ $jatuhTempo }}</strong>, {{ $sisaHari }} Hari Lagi</p>
                        @endif

                        @if ($b['alasanDitolak'] !== null)
                            <div role="status" class="rounded-[10px] bg-danger-container px-3.5 py-3 text-on-danger-container teks-badan" data-test="kotak-ditolak">
                                Ditolak{{ $b['ditolakPada'] ? ', '.$b['ditolakPada']->locale('id')->translatedFormat('j M') : '' }}: {{ $b['alasanDitolak'] }}
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2 max-md:mt-1.5 md:items-end">
                        <x-tombol
                            :href="route('tagihan-saya.unggah', $b['id'])"
                            wire:navigate
                            class="max-md:w-full"
                        >Unggah Bukti</x-tombol>
                        @if ($utama['lainnya'] > 0)
                            <p class="m-0 teks-bantuan" data-test="tagihan-lain">{{ $utama['lainnya'] }} tagihan lain belum dibayar.</p>
                        @endif
                    </div>
                </div>
            @elseif ($utama['keadaan'] === 'menunggu')
                <h2 id="judul-utama" class="m-0 teks-bagian">Bukti {{ $utama['baris']['periode']->locale('id')->translatedFormat('F') }} sedang dicek pengelola.</h2>
                <x-status jenis="tunggu">Menunggu</x-status>
            @elseif ($utama['keadaan'] === 'lunas')
                <h2 id="judul-utama" class="m-0 teks-bagian">Semua tagihan sudah lunas.</h2>
                <x-status jenis="ok">Lunas</x-status>
            @else
                <h2 id="judul-utama" class="m-0 teks-bagian">Belum ada tagihan.</h2>
            @endif
        </section>

        {{-- Semua tagihan --}}
        @if ($this->adaTagihan)
            <section aria-labelledby="judul-semua" class="flex flex-col gap-3">
                <h2 id="judul-semua" class="m-0 teks-bagian">Riwayat Tagihan</h2>

                {{-- Desktop: tabel kartu --}}
                <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
                    <table class="w-full min-w-[640px] table-fixed border-collapse">
                        {{-- Lebar kolom tetap: kepala kolom tidak bergeser saat halaman daftar diganti. --}}
                        <colgroup>
                            <col class="w-[25%]">
                            <col class="w-[19%]">
                            <col class="w-[22%]">
                            <col class="w-[34%]">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="border-b border-border px-5 py-3.5 text-left whitespace-nowrap teks-kolom">Periode</th>
                                <th scope="col" class="border-b border-border px-5 py-3.5 text-left whitespace-nowrap teks-kolom">Jatuh Tempo</th>
                                <th scope="col" class="border-b border-border px-5 py-3.5 text-right whitespace-nowrap teks-kolom">Nominal</th>
                                <th scope="col" class="border-b border-border px-5 py-3.5 text-left whitespace-nowrap teks-kolom">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->daftar as $b)
                                <tr wire:key="tabel-{{ $b['id'] }}" class="border-t border-row-divider first:border-t-0">
                                    <td class="px-5 py-3.5 align-top whitespace-nowrap teks-badan-kuat">{{ $b['periode']->locale('id')->translatedFormat('F Y') }}</td>
                                    <td class="px-5 py-3.5 align-top whitespace-nowrap">{{ $b['jatuhTempo']->locale('id')->translatedFormat('j M') }}</td>
                                    <td class="num px-5 py-3.5 text-right align-top whitespace-nowrap teks-badan-kuat">{{ Format::rupiah($b['nominal']) }}</td>
                                    <td class="px-5 py-3.5 align-top"><x-status-tagihan :baris="$b" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Ponsel: kartu daftar --}}
                <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 md:hidden">
                    @foreach ($this->daftar as $b)
                        <li wire:key="daftar-{{ $b['id'] }}" class="flex flex-col gap-1.5 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="teks-badan-kuat">{{ $b['periode']->locale('id')->translatedFormat('F Y') }}</span>
                                <span class="num teks-badan-kuat">{{ Format::rupiah($b['nominal']) }}</span>
                            </div>
                            <x-status-tagihan :baris="$b" :ponsel="true" />
                        </li>
                    @endforeach
                </ul>

                {{ $this->halamanTagihan->links() }}
            </section>
        @endif
    @endif
</section>
