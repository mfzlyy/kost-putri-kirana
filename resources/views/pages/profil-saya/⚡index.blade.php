<?php

use App\Models\Penghuni;
use App\Support\Format;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil saya')] class extends Component {
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

<section class="flex w-full max-w-[880px] flex-col gap-4 lg:gap-6">
    @if ($penghuni === null)
        <x-judul-halaman judul="Profil saya" deskripsi="Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos." />
    @else
        <x-judul-halaman :judul="$penghuni->user?->nama" :deskripsi="$nomorKamar ? 'Penghuni Kamar '.$nomorKamar : 'Penghuni'" />

        <div class="flex flex-wrap items-start gap-4">
            <section class="flex-[1_1_320px] rounded-2xl border border-border bg-surface p-5" aria-labelledby="judul-data-diri">
                <h2 id="judul-data-diri" class="m-0 mb-3.5 text-base font-[650]">Data diri</h2>
                <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5">
                    <dt class="text-ink-muted">Email akun</dt>
                    <dd class="m-0 break-words">{{ $penghuni->user?->email }}</dd>

                    <dt class="text-ink-muted">Telepon</dt>
                    <dd class="num m-0">{{ Format::telepon($penghuni->no_telepon) }}</dd>

                    <dt class="text-ink-muted">Nomor identitas</dt>
                    <dd class="m-0 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span class="num tracking-[0.02em]" aria-live="polite" data-test="nik">{{ $this->identitasTerbuka ? $this->identitasUtuh : $penghuni->no_identitas_tersamar }}</span>
                        @if ($this->identitasTerbuka)
                            <button type="button" aria-pressed="true" wire:click="sembunyikanIdentitas" class="-my-2.5 cursor-pointer py-2.5 text-sm font-semibold text-primary" data-test="sembunyikan-identitas">Sembunyikan</button>
                        @else
                            <button type="button" aria-pressed="false" wire:click="tampilkanIdentitas" class="-my-2.5 cursor-pointer py-2.5 text-sm font-semibold text-primary" data-test="tampilkan-identitas">Tampilkan</button>
                        @endif
                    </dd>
                </dl>
            </section>

            <section class="flex-[1_1_280px] rounded-2xl border border-border bg-surface p-5" aria-labelledby="judul-kontrak">
                <h2 id="judul-kontrak" class="m-0 mb-3.5 text-base font-[650]">Kontrak aktif</h2>
                @if ($kontrak)
                    <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] items-baseline gap-x-3.5 gap-y-2.5">
                        <dt class="text-ink-muted">Masa sewa</dt>
                        <dd class="m-0">{{ $kontrak->tanggal_mulai->locale('id')->translatedFormat('j M Y') }} sampai {{ $kontrak->tanggal_berakhir->locale('id')->translatedFormat('j M Y') }}</dd>

                        <dt class="text-ink-muted"><span class="md:hidden">Tarif per bulan</span><span class="hidden md:inline">Tarif</span></dt>
                        <dd class="num m-0">{{ Format::rupiah($kontrak->tarif_bulanan) }}<span class="hidden md:inline"> per bulan</span></dd>
                    </dl>
                @else
                    <p class="m-0 text-ink-muted" data-test="tanpa-kontrak">Belum ada kontrak aktif.</p>
                @endif
            </section>
        </div>

        <p class="m-0 text-sm text-ink-muted">Ada data yang salah? Hubungi pengelola kos untuk mengubahnya.</p>
    @endif
</section>
