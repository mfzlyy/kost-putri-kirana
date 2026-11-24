<?php

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use App\Models\Penghuni;
use App\Models\TiketKeluhan;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Keluhan')] class extends Component {
    use WithPagination;

    /** Nomor tiket yang baru dikirim, dari flash redirect halaman Ajukan Keluhan. Hanya ada sampai halaman dimuat ulang. */
    #[Locked]
    public ?string $nomorBaru = null;

    public function mount(): void
    {
        $this->authorize('viewAny', TiketKeluhan::class);

        $this->nomorBaru = session('keluhan_baru');
    }

    /**
     * Selalu penghuni milik pengguna yang login; tidak pernah menerima ID penghuni dari URL atau browser (ATB-10).
     */
    #[Computed]
    public function penghuni(): ?Penghuni
    {
        return Penghuni::query()->where('user_id', auth()->id())->first();
    }

    /**
     * Tiket milik penghuni ini saja, terbaru dulu. Fasilitas, kamar, dan riwayat dimuat sekaligus (tanpa N+1).
     */
    #[Computed]
    public function halaman(): LengthAwarePaginator
    {
        return TiketKeluhan::query()
            ->milikPenghuni($this->penghuni)
            ->with(['fasilitas.kamar', 'riwayatStatus'])
            ->orderByDesc('tiket_keluhan.tanggal_lapor')
            ->orderByDesc('tiket_keluhan.id')
            ->paginate(10);
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
}; ?>

@php
    $penghuni = $this->penghuni;
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    @if ($penghuni === null)
        <x-judul-halaman judul="Keluhan" />
        <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.</p>
    @else
        <x-judul-halaman judul="Keluhan">
            @can('create', App\Models\TiketKeluhan::class)
                <a href="{{ route('keluhan-saya.ajukan') }}" wire:navigate class="tombol bg-primary text-white no-underline hover:bg-primary-hover hover:text-white max-sm:w-full">Ajukan</a>
            @endcan
        </x-judul-halaman>

        @if ($nomorBaru !== null)
            <div role="status" class="flex items-center gap-2.5 rounded-xl bg-primary-container px-4 py-3.5 text-on-primary-container teks-badan" data-test="flash-keluhan">
                <svg viewBox="0 0 16 16" class="size-4 shrink-0 text-status-ok" aria-hidden="true"><circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M4.8 8.2l2.1 2.1 4.3-4.6" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Keluhan terkirim dengan nomor <strong class="font-semibold">{{ $nomorBaru }}</strong>.</span>
            </div>
        @endif

        @php
            $halaman = $this->halaman;
        @endphp

        @if ($halaman->total() === 0)
            <p class="m-0 rounded-2xl border border-border bg-surface px-4 py-10 text-center text-ink-muted" data-test="pesan-kosong">Belum ada keluhan.</p>
        @else
            <ul class="m-0 grid list-none grid-cols-1 items-start gap-4 p-0 lg:grid-cols-2" data-test="daftar-keluhan">
                @foreach ($halaman as $tiket)
                    <li
                        wire:key="tiket-{{ $tiket->id }}"
                        @class([
                            'flex flex-col gap-2.5 rounded-2xl bg-surface p-4 md:p-5',
                            'border border-border' => $tiket->nomor_tiket !== $nomorBaru,
                            'border-2 border-primary' => $tiket->nomor_tiket === $nomorBaru,
                        ])
                        data-test="kartu-tiket"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="num teks-badan-kuat" data-test="nomor-tiket">{{ $tiket->nomor_tiket }}</span>
                            <span class="teks-bantuan">{{ $tiket->tanggal_lapor->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j M') }}</span>
                        </div>

                        <p class="m-0 teks-badan" data-test="fasilitas-lokasi">{{ $tiket->fasilitas->nama_fasilitas }}, {{ $this->lokasiTiket($tiket) }}</p>

                        <div class="flex items-center gap-2.5">
                            <x-meter-tiket :status="$tiket->status_tiket" />
                            <x-status :jenis="$this->jenisStatus($tiket->status_tiket)">{{ $tiket->status_tiket->label() }}</x-status>
                        </div>

                        <div class="mt-1 border-t border-divider pt-3">
                            <h2 class="m-0 mb-2 teks-label">Riwayat Status</h2>
                            <ol class="m-0 flex list-none flex-col gap-1.5 p-0" data-test="riwayat-status">
                                @foreach ($tiket->riwayatStatus as $riwayat)
                                    <li wire:key="riwayat-{{ $riwayat->id }}" class="flex items-baseline justify-between gap-3 teks-badan">
                                        <span>{{ $riwayat->status_sesudah->label() }}</span>
                                        <span class="num teks-bantuan">{{ App\Support\Format::tanggalJam($riwayat->waktu_perubahan) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{ $halaman->links() }}
        @endif
    @endif
</section>
