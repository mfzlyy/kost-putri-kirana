<?php

use App\Models\Penghuni;
use App\Support\Format;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil')] class extends Component {
    #[Locked]
    public bool $identitasTerbuka = false;

    public function mount(): void
    {
        if ($this->penghuni !== null) {
            $this->authorize('view', $this->penghuni);
        }
    }

    /**
     * Selalu data milik pengguna yang login; tidak ada ID dari URL atau browser (ATB-10).
     */
    #[Computed]
    public function penghuni(): ?Penghuni
    {
        return Penghuni::query()
            ->where('user_id', auth()->id())
            ->with(['user:id,nama,email', 'kontrakAktif.kamar:id,nomor_kamar'])
            ->first();
    }

    /**
     * Nomor identitas utuh diambil di server hanya saat dibuka, tidak pernah menjadi properti publik (NFR-02).
     */
    #[Computed]
    public function identitasUtuh(): ?string
    {
        if (! $this->identitasTerbuka || $this->penghuni === null) {
            return null;
        }

        $this->authorize('lihatIdentitas', $this->penghuni);

        return Format::kelompokEmpat((string) $this->penghuni->no_identitas_enc);
    }

    public function tampilkanIdentitas(): void
    {
        abort_if($this->penghuni === null, 404);

        $this->authorize('lihatIdentitas', $this->penghuni);

        $this->identitasTerbuka = true;
    }

    public function sembunyikanIdentitas(): void
    {
        abort_if($this->penghuni === null, 404);

        $this->authorize('lihatIdentitas', $this->penghuni);

        $this->identitasTerbuka = false;
        unset($this->identitasUtuh);
    }
}; ?>

@php
    $penghuni = $this->penghuni;
    $kontrak = $penghuni?->kontrakAktif;
    $nomorKamar = $kontrak?->kamar?->nomor_kamar;
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    @if ($penghuni === null)
        <x-judul-halaman judul="Profil" />
        <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.</p>
    @else
        <x-judul-halaman judul="Profil" />

        <div class="flex flex-wrap items-start gap-4">
            <section class="flex-[1_1_320px] rounded-2xl border border-border bg-surface p-5" aria-labelledby="judul-data-diri">
                <h2 id="judul-data-diri" class="m-0 mb-3.5 teks-bagian">Data Diri</h2>
                <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5">
                    <dt class="text-ink-muted">Nama</dt>
                    <dd class="m-0 break-words" data-test="nama">{{ $penghuni->user?->nama }}</dd>

                    <dt class="text-ink-muted">Email</dt>
                    <dd class="m-0 break-words">{{ $penghuni->user?->email }}</dd>

                    <dt class="text-ink-muted">Telepon</dt>
                    <dd class="num m-0">{{ Format::telepon($penghuni->no_telepon) }}</dd>

                    <dt class="text-ink-muted">Nomor Identitas</dt>
                    <dd class="m-0 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span class="num tracking-[0.02em]" aria-live="polite" data-test="nik">{{ $this->identitasTerbuka ? $this->identitasUtuh : $penghuni->no_identitas_tersamar }}</span>
                        @if ($this->identitasTerbuka)
                            <button type="button" aria-pressed="true" wire:click="sembunyikanIdentitas" class="-my-2.5 cursor-pointer py-2.5 text-primary teks-tombol-kecil" data-test="sembunyikan-identitas">Sembunyikan</button>
                        @else
                            <button type="button" aria-pressed="false" wire:click="tampilkanIdentitas" class="-my-2.5 cursor-pointer py-2.5 text-primary teks-tombol-kecil" data-test="tampilkan-identitas">Tampilkan</button>
                        @endif
                    </dd>
                </dl>
            </section>

            <section class="flex-[1_1_280px] rounded-2xl border border-border bg-surface p-5" aria-labelledby="judul-kontrak">
                <h2 id="judul-kontrak" class="m-0 mb-3.5 teks-bagian">Kontrak</h2>
                @if ($kontrak)
                    <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5">
                        <dt class="text-ink-muted">Kamar</dt>
                        <dd class="num m-0">{{ $nomorKamar }}</dd>

                        <dt class="text-ink-muted">Masa Sewa</dt>
                        <dd class="m-0">{{ $kontrak->tanggal_mulai->locale('id')->translatedFormat('j M Y') }} sampai {{ $kontrak->tanggal_berakhir->locale('id')->translatedFormat('j M Y') }}</dd>

                        <dt class="text-ink-muted">Tarif</dt>
                        <dd class="num m-0">{{ Format::rupiah($kontrak->tarif_bulanan) }}</dd>
                    </dl>
                @else
                    <p class="m-0 text-ink-muted" data-test="tanpa-kontrak">Belum ada kontrak aktif.</p>
                @endif
            </section>
        </div>

    @endif

    {{-- Ponsel penghuni tidak punya menu pengguna sidebar, jadi Ganti password dan Keluar ada di sini. --}}
    <section class="flex flex-col overflow-hidden rounded-2xl border border-border bg-surface md:hidden" aria-label="Akun" data-test="kotak-akun">
        <a href="{{ route('security.edit') }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 border-b border-row-divider px-4 font-semibold text-ink no-underline hover:text-ink">
            Ganti Password
            <flux:icon.chevron-right class="size-[18px] text-ink-muted" />
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex min-h-11 w-full cursor-pointer items-center px-4 text-left font-semibold text-danger" data-test="keluar-ponsel">Keluar</button>
        </form>
    </section>
</section>
