<?php

use App\Enums\StatusAkun;
use App\Enums\StatusPenghuni;
use App\Exceptions\KontrakBentrok;
use App\Exceptions\KontrakSudahBerakhir;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\User;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kontrak')] class extends Component {
    /** Terisi hanya pada mode perpanjang: kontrak lama yang akan ditutup. */
    #[Locked]
    public ?int $kontrakId = null;

    public string $penghuni_id = '';

    public string $kamar_id = '';

    public string $tanggal_mulai = '';

    public string $tanggal_berakhir = '';

    public string $tarif_bulanan = '';

    /** Tarif terakhir yang diisikan otomatis dari kamar; dipakai agar tarif ketikan pengguna tidak tertimpa. */
    #[Locked]
    public string $tarifOtomatis = '';

    public function mount(?Kontrak $kontrak = null): void
    {
        if ($kontrak === null || ! $kontrak->exists) {
            $this->authorize('create', Kontrak::class);

            $this->tanggal_mulai = Kontrak::hariIni()->toDateString();

            return;
        }

        $this->authorize('perpanjang', $kontrak);

        $kontrak->load('kamar:id,nomor_kamar,tarif_bulanan');

        $this->kontrakId = $kontrak->id;
        $this->tanggal_mulai = $kontrak->tanggal_berakhir->addDay()->toDateString();
        $this->tarif_bulanan = self::formatTarif($kontrak->kamar->tarif_bulanan);
        $this->tarifOtomatis = $this->tarif_bulanan;
    }

    private static function formatTarif(int|float|string $nilai): string
    {
        return number_format((int) $nilai, 0, ',', '.');
    }

    #[Computed]
    public function kontrak(): ?Kontrak
    {
        if ($this->kontrakId === null) {
            return null;
        }

        return Kontrak::query()
            ->with(['penghuni:id,user_id', 'penghuni.user:id,nama', 'kamar:id,nomor_kamar,tarif_bulanan'])
            ->findOrFail($this->kontrakId);
    }

    /**
     * Penghuni yang boleh dipilih: aktif, akunnya aktif, dan belum punya kontrak aktif.
     */
    private function queryPenghuniTersedia()
    {
        return Penghuni::query()
            ->where('penghuni.status_penghuni', StatusPenghuni::Aktif->value)
            ->whereHas('user', fn ($query) => $query->where('status_akun', StatusAkun::Aktif->value))
            ->whereDoesntHave('kontrakAktif');
    }

    #[Computed]
    public function penghuniTersedia()
    {
        return $this->queryPenghuniTersedia()
            ->with('user:id,nama')
            ->orderBy(User::query()->select('nama')->whereColumn('users.id', 'penghuni.user_id'))
            ->orderBy('penghuni.id')
            ->get(['penghuni.id', 'penghuni.user_id']);
    }

    /**
     * Semua kamar beserta kontrak aktifnya, untuk kisi gantungan kunci.
     */
    #[Computed]
    public function daftarKamar()
    {
        return Kamar::query()
            ->with('kontrakAktif:id,kamar_id,tanggal_berakhir')
            ->orderBy('nomor_kamar')
            ->get(['id', 'nomor_kamar', 'tarif_bulanan']);
    }

    /**
     * Batas hari peringatan dibaca dari pengaturan operasional; null bila belum diatur (ISU-02).
     */
    #[Computed]
    public function batasPeringatan(): ?int
    {
        return PengaturanOperasional::aktif()->batas_peringatan_kontrak_hari;
    }

    #[Computed]
    public function kamarTerpilih(): ?Kamar
    {
        return ctype_digit($this->kamar_id)
            ? Kamar::query()->find((int) $this->kamar_id, ['id', 'nomor_kamar', 'tarif_bulanan'])
            : null;
    }

    /**
     * Memilih kamar mengisi tarif dari tarif kamar, selama pengguna belum mengubah tarifnya sendiri (F-07 butir 1).
     */
    public function updatedKamarId(): void
    {
        unset($this->kamarTerpilih);

        $kamar = $this->kamarTerpilih;

        if ($kamar === null) {
            return;
        }

        $belumDiubah = Format::parseRupiah($this->tarif_bulanan) === null
            || Format::parseRupiah($this->tarif_bulanan) === Format::parseRupiah($this->tarifOtomatis);

        if ($belumDiubah) {
            $this->tarif_bulanan = self::formatTarif($kamar->tarif_bulanan);
            $this->tarifOtomatis = $this->tarif_bulanan;
        }
    }

    public function simpan(): void
    {
        if ($this->kontrakId === null) {
            $this->authorize('create', Kontrak::class);
            $this->simpanBaru();

            return;
        }

        $kontrak = Kontrak::query()->findOrFail($this->kontrakId);

        $this->authorize('perpanjang', $kontrak);
        $this->simpanPerpanjangan($kontrak);
    }

    private function aturanTarif(): array
    {
        return [
            'required',
            function (string $atribut, mixed $nilai, Closure $gagal) {
                $tarif = Format::parseRupiah((string) $nilai);

                if ($tarif === null || $tarif < 1 || $tarif > 9999999999) {
                    $gagal('Isi tarif dalam rupiah, lebih dari 0.');
                }
            },
        ];
    }

    private function tanggalValid(string $nilai): bool
    {
        $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $nilai);

        return $tanggal !== false && $tanggal->format('Y-m-d') === $nilai;
    }

    private function simpanBaru(): void
    {
        $this->tanggal_mulai = trim($this->tanggal_mulai);
        $this->tanggal_berakhir = trim($this->tanggal_berakhir);

        $hariIni = Kontrak::hariIni()->toDateString();

        $this->validate([
            'penghuni_id' => [
                'bail',
                'required',
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    $penghuni = ctype_digit((string) $nilai)
                        ? Penghuni::query()
                            ->whereKey((int) $nilai)
                            ->where('status_penghuni', StatusPenghuni::Aktif->value)
                            ->whereHas('user', fn ($query) => $query->where('status_akun', StatusAkun::Aktif->value))
                            ->first(['id'])
                        : null;

                    if ($penghuni === null) {
                        $gagal('Pilih penghuni.');
                    } elseif (Kontrak::query()->aktif()->where('penghuni_id', $penghuni->id)->exists()) {
                        $gagal('Penghuni ini sudah punya kontrak aktif.');
                    }
                },
            ],
            'kamar_id' => [
                'bail',
                'required',
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    $kamar = ctype_digit((string) $nilai) ? Kamar::query()->find((int) $nilai, ['id', 'nomor_kamar']) : null;

                    if ($kamar === null) {
                        $gagal('Pilih kamar.');
                    } elseif (Kontrak::query()->aktif()->where('kamar_id', $kamar->id)->exists()) {
                        $gagal("Kamar {$kamar->nomor_kamar} sudah terisi.");
                    }
                },
            ],
            'tanggal_mulai' => [
                'bail',
                'required',
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    if (! $this->tanggalValid((string) $nilai)) {
                        $gagal('Isi tanggal mulai yang valid.');
                    }
                },
            ],
            'tanggal_berakhir' => [
                'bail',
                'required',
                function (string $atribut, mixed $nilai, Closure $gagal) use ($hariIni) {
                    if (! $this->tanggalValid((string) $nilai)) {
                        $gagal('Isi tanggal berakhir yang valid.');
                    } elseif ($this->tanggalValid($this->tanggal_mulai) && $nilai <= $this->tanggal_mulai) {
                        $gagal('Harus setelah tanggal mulai.');
                    } elseif ($nilai < $hariIni) {
                        $gagal('Tanggal berakhir sudah lewat. Kontrak yang sudah selesai dicatat lewat migrasi data.');
                    }
                },
            ],
            'tarif_bulanan' => $this->aturanTarif(),
        ], [
            'penghuni_id.required' => 'Pilih penghuni.',
            'kamar_id.required' => 'Pilih kamar.',
            'tanggal_mulai.required' => 'Isi tanggal mulai.',
            'tanggal_berakhir.required' => 'Isi tanggal berakhir.',
            'tarif_bulanan.required' => 'Isi tarif dalam rupiah, lebih dari 0.',
        ]);

        try {
            $kontrak = Kontrak::buat(
                (int) $this->penghuni_id,
                (int) $this->kamar_id,
                CarbonImmutable::parse($this->tanggal_mulai),
                CarbonImmutable::parse($this->tanggal_berakhir),
                Format::parseRupiah($this->tarif_bulanan),
            );
        } catch (KontrakBentrok $e) {
            // Jaring pengaman untuk permintaan bersamaan; pengecekan biasa sudah dilakukan di validasi.
            $this->addError($e->field === 'kamar' ? 'kamar_id' : 'penghuni_id', $e->getMessage());

            return;
        }

        $kontrak->load(['penghuni:id,user_id', 'penghuni.user:id,nama', 'kamar:id,nomor_kamar']);

        // Toast dibawa lewat session flash karena halaman daftar dimuat ulang oleh redirect.
        session()->flash('toast', "Kontrak Kamar {$kontrak->kamar->nomor_kamar} untuk {$kontrak->penghuni->user->nama} disimpan.");

        $this->redirectRoute('kontrak.index', navigate: true);
    }

    private function simpanPerpanjangan(Kontrak $kontrak): void
    {
        $this->tanggal_berakhir = trim($this->tanggal_berakhir);

        $mulaiBaru = $kontrak->tanggal_berakhir->addDay()->toDateString();

        $this->validate([
            'tanggal_berakhir' => [
                'bail',
                'required',
                function (string $atribut, mixed $nilai, Closure $gagal) use ($mulaiBaru) {
                    if (! $this->tanggalValid((string) $nilai)) {
                        $gagal('Isi tanggal berakhir yang valid.');
                    } elseif ($nilai <= $mulaiBaru) {
                        $gagal('Harus setelah tanggal mulai.');
                    }
                },
            ],
            'tarif_bulanan' => $this->aturanTarif(),
        ], [
            'tanggal_berakhir.required' => 'Isi tanggal berakhir.',
            'tarif_bulanan.required' => 'Isi tarif dalam rupiah, lebih dari 0.',
        ]);

        $nomorKamar = Kamar::query()->whereKey($kontrak->kamar_id)->value('nomor_kamar');
        $berakhirBaru = CarbonImmutable::parse($this->tanggal_berakhir);

        try {
            $kontrak->perpanjang($berakhirBaru, Format::parseRupiah($this->tarif_bulanan));
        } catch (KontrakSudahBerakhir $e) {
            abort(403, $e->getMessage());
        }

        session()->flash('toast', "Kontrak Kamar {$nomorKamar} diperpanjang sampai ".$berakhirBaru->locale('id')->translatedFormat('j F Y').'.');

        $this->redirectRoute('kontrak.index', navigate: true);
    }
}; ?>

@php
    $kontrakLama = $this->kontrak;
    $perpanjang = $kontrakLama !== null;
    $kataJumlah = [1 => 'Satu', 2 => 'Dua', 3 => 'Tiga', 4 => 'Empat', 5 => 'Lima'];
    $jumlahGalat = count($errors->keys());
    $tanpaPenghuni = ! $perpanjang && $this->penghuniTersedia->isEmpty();
    $batas = $this->batasPeringatan;
    $tanggalPanjang = fn ($tanggal) => $tanggal->locale('id')->translatedFormat('j F Y');
    $judul = $perpanjang ? 'Perpanjang kontrak Kamar '.$kontrakLama->kamar?->nomor_kamar : 'Buat kontrak';
@endphp

<section class="flex w-full flex-col gap-4 lg:gap-6">
    <div class="flex flex-col gap-2.5">
        <a href="{{ route('kontrak.index') }}" wire:navigate class="hidden w-fit lg:inline">Kontrak</a>
        <div class="flex items-start gap-1">
            <a href="{{ route('kontrak.index') }}" wire:navigate aria-label="Kembali ke kontrak" class="-ms-2 inline-flex size-11 shrink-0 items-center justify-center rounded-lg lg:hidden">
                <flux:icon.arrow-left class="size-5" />
            </a>
            <x-judul-halaman class="min-w-0 flex-1" :judul="$judul" />
        </div>
    </div>

    <form wire:submit="simpan" novalidate class="flex max-w-[860px] flex-col gap-[22px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
        @if ($jumlahGalat > 0)
            <p role="alert" class="m-0 rounded-[10px] bg-danger-container px-3.5 py-3 text-sm text-on-danger-container" data-test="ringkasan-galat">
                {{ $kataJumlah[$jumlahGalat] ?? $jumlahGalat }} isian perlu diperbaiki sebelum disimpan.
            </p>
        @endif

        @if ($perpanjang)
            {{-- Ringkasan hanya-baca --}}
            <dl class="m-0 grid grid-cols-1 gap-3 sm:grid-cols-3" data-test="ringkasan-kontrak">
                <div>
                    <dt class="text-sm text-ink-muted">Penghuni</dt>
                    <dd class="m-0 font-semibold">{{ $kontrakLama->penghuni?->user?->nama }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-muted">Kamar</dt>
                    <dd class="num m-0 font-semibold">{{ $kontrakLama->kamar?->nomor_kamar }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-muted">Mulai</dt>
                    <dd class="m-0 font-semibold">{{ $tanggalPanjang($kontrakLama->tanggal_berakhir->addDay()) }}</dd>
                </div>
            </dl>

            <p class="m-0 rounded-xl bg-primary-container px-3.5 py-3 text-sm text-on-primary-container" data-test="catatan-perpanjang">
                Kontrak lama ({{ $tanggalPanjang($kontrakLama->tanggal_mulai) }} sampai {{ $tanggalPanjang($kontrakLama->tanggal_berakhir) }}, {{ Format::rupiah($kontrakLama->tarif_bulanan) }} per bulan) ditutup dan tetap tersimpan di riwayat. Kontrak baru dimulai {{ $tanggalPanjang($kontrakLama->tanggal_berakhir->addDay()) }}.
            </p>

            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Berakhir</flux:label>
                    <flux:input type="date" wire:model="tanggal_berakhir" data-test="input-berakhir" />
                    <flux:error name="tanggal_berakhir" />
                </flux:field>

                <flux:field>
                    <flux:label>Tarif per bulan</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix class="font-semibold text-ink-muted">Rp</flux:input.group.prefix>
                        <flux:input
                            wire:model="tarif_bulanan"
                            inputmode="numeric"
                            autocomplete="off"
                            class:input="num"
                            x-data
                            x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                            data-test="input-tarif"
                        />
                    </flux:input.group>
                    <flux:description>Diisi dari tarif Kamar {{ $kontrakLama->kamar?->nomor_kamar }} saat ini. Tarif kontrak lama {{ Format::rupiah($kontrakLama->tarif_bulanan) }}.</flux:description>
                    <flux:error name="tarif_bulanan" />
                </flux:field>
            </div>
        @else
            @if ($tanpaPenghuni)
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="tanpa-penghuni">
                    <flux:callout.text>Semua penghuni aktif sudah punya kontrak. <a href="{{ route('penghuni.tambah') }}" wire:navigate class="font-semibold">Tambah data penghuni</a> di halaman Penghuni.</flux:callout.text>
                </flux:callout>
            @else
                <flux:field>
                    <flux:label>Penghuni</flux:label>
                    <flux:select wire:model="penghuni_id" placeholder="Pilih penghuni" data-test="pilih-penghuni">
                        @foreach ($this->penghuniTersedia as $penghuni)
                            <flux:select.option value="{{ $penghuni->id }}" wire:key="penghuni-{{ $penghuni->id }}">{{ $penghuni->user?->nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>Hanya penghuni tanpa kontrak aktif.</flux:description>
                    <flux:error name="penghuni_id" />
                </flux:field>
            @endif

            {{-- Kisi gantungan kunci: radio group yang bisa dipakai keyboard. Kamar terisi pudar dan tidak bisa dipilih. --}}
            <fieldset class="m-0 min-w-0 border-0 p-0">
                <legend id="legenda-kamar" class="mb-2 p-0 text-sm font-semibold">Kamar</legend>

                <div role="radiogroup" aria-labelledby="legenda-kamar" class="grid grid-cols-4 gap-2 md:grid-cols-8" data-test="kisi-kamar">
                    @foreach ($this->daftarKamar as $kamar)
                        @php
                            $kontrakAktif = $kamar->kontrakAktif;
                            $terisi = $kontrakAktif !== null;
                            $keterangan = ! $terisi
                                ? 'Kosong'
                                : ($batas !== null && $kontrakAktif->tanggal_berakhir->toDateString() <= Kontrak::hariIni()->addDays($batas)->toDateString()
                                    ? 's.d. '.$kontrakAktif->tanggal_berakhir->locale('id')->translatedFormat('j M')
                                    : 'Terisi');
                        @endphp
                        <div class="relative" wire:key="kamar-{{ $kamar->id }}">
                            <input
                                type="radio"
                                id="kamar-{{ $kamar->id }}"
                                name="kamar_id"
                                value="{{ $kamar->id }}"
                                wire:model.live="kamar_id"
                                class="peer sr-only"
                                @if ($terisi) disabled aria-disabled="true" @endif
                                data-test="kamar-{{ $kamar->nomor_kamar }}"
                            />
                            <label
                                for="kamar-{{ $kamar->id }}"
                                @class([
                                    'relative flex min-h-[84px] flex-col justify-between rounded-[10px] border px-2.5 pt-[22px] pb-2.5 before:absolute before:top-[7px] before:left-1/2 before:-ml-1 before:size-2 before:rounded-full before:border before:border-border before:bg-background',
                                    'cursor-pointer border-border bg-surface peer-checked:border-primary peer-checked:bg-primary-container peer-checked:shadow-[inset_0_0_0_1px_var(--color-primary)] peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary' => ! $terisi,
                                    'cursor-not-allowed border-border bg-surface-disabled text-[#8A918F]' => $terisi,
                                ])
                            >
                                <strong class="num font-stretch-expanded">{{ $kamar->nomor_kamar }}</strong>
                                <span class="text-xs">{{ $keterangan }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <flux:error name="kamar_id" />
            </fieldset>

            <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                <flux:field>
                    <flux:label>Mulai</flux:label>
                    <flux:input type="date" wire:model="tanggal_mulai" data-test="input-mulai" />
                    <flux:error name="tanggal_mulai" />
                </flux:field>

                <flux:field>
                    <flux:label>Berakhir</flux:label>
                    <flux:input type="date" wire:model="tanggal_berakhir" data-test="input-berakhir" />
                    <flux:error name="tanggal_berakhir" />
                </flux:field>

                <flux:field>
                    <flux:label>Tarif per bulan</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix class="font-semibold text-ink-muted">Rp</flux:input.group.prefix>
                        <flux:input
                            wire:model="tarif_bulanan"
                            inputmode="numeric"
                            autocomplete="off"
                            class:input="num"
                            x-data
                            x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                            data-test="input-tarif"
                        />
                    </flux:input.group>
                    <flux:description>
                        {{ $this->kamarTerpilih ? 'Diisi dari tarif Kamar '.$this->kamarTerpilih->nomor_kamar.'. Bisa disesuaikan.' : 'Pilih kamar untuk mengisi tarif.' }}
                    </flux:description>
                    <flux:error name="tarif_bulanan" />
                </flux:field>
            </div>
        @endif

        <div class="flex flex-col gap-2 border-t border-divider pt-[18px] sm:flex-row">
            <flux:button type="submit" variant="primary" class="h-12! w-full sm:h-11! sm:w-auto" wire:loading.attr="disabled" wire:target="simpan" :disabled="$tanpaPenghuni" data-test="simpan-kontrak">
                {{ $perpanjang ? 'Simpan perpanjangan' : 'Simpan kontrak' }}
            </flux:button>
            <x-tautan-batal :href="route('kontrak.index')" />
        </div>
    </form>
</section>
