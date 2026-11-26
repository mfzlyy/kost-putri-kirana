<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Exceptions\PenerbitanTagihanDitolak;
use App\Http\Middleware\PastikanPeran;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Support\Format;
use App\Support\Tagihan\HasilPenerbitan;
use App\Support\Tagihan\PenghitungNominal;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Tagihan')] class extends Component {
    /** Periode terpilih, YYYY-MM. Kosong atau tidak valid berarti bulan berjalan. */
    #[Url(as: 'periode')]
    public string $periode = '';

    #[Url(as: 'status')]
    public string $filterStatus = 'semua';

    /** Periode di dialog terbitkan, YYYY-MM. */
    public string $periodeTerbit = '';

    /** Pratinjau dialog hanya dihitung setelah dialog dibuka. */
    public bool $dialogDibuka = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Tagihan::class);

        // Policy viewAny juga mengizinkan penghuni (untuk Tagihan Saya); halaman ini khusus pemilik dan pengelola.
        abort_unless(
            in_array(auth()->user()->peran, [PeranPengguna::Pemilik, PeranPengguna::Pengelola], true),
            403,
            PastikanPeran::PESAN_DITOLAK,
        );
    }

    private function parsePeriode(string $nilai): ?CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $nilai) === 1 ? CarbonImmutable::parse($nilai.'-01') : null;
    }

    public function pilihPeriode(string $nilai): void
    {
        if ($this->parsePeriode($nilai) !== null) {
            $this->periode = $nilai;
        }
    }

    #[Computed]
    public function hariIni(): CarbonImmutable
    {
        return Kontrak::hariIni();
    }

    #[Computed]
    public function periodeTerpilih(): CarbonImmutable
    {
        return $this->parsePeriode($this->periode) ?? $this->hariIni->startOfMonth();
    }

    #[Computed]
    public function filterAktif(): string
    {
        return in_array($this->filterStatus, ['telat', 'belum', 'menunggu', 'lunas'], true) ? $this->filterStatus : 'semua';
    }

    #[Computed]
    public function bisaKelola(): bool
    {
        return auth()->user()->can('terbitkan', Tagihan::class);
    }

    #[Computed]
    public function jatuhTempoSiap(): bool
    {
        return PengaturanOperasional::aktif()->sudahSiapTerbitTagihan();
    }

    /**
     * Semua tunggakan per hari ini, semua periode, dihitung oleh scope Tagihan::tunggakan() (ATB-05).
     * Menjadi satu-satunya sumber penanda Telat, jadi tidak ada query per baris.
     *
     * @return Collection<int, Tagihan>
     */
    #[Computed]
    public function semuaTunggakan(): Collection
    {
        return Tagihan::query()
            ->tunggakan(now())
            ->get(['tagihan.id', 'tagihan.periode', 'tagihan.nominal', 'tagihan.jatuh_tempo'])
            ->keyBy('id');
    }

    /**
     * Baris tampilan: tagihan periode terpilih ditambah tunggakan dari periode sebelumnya, urut periode lalu nomor kamar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function semuaBaris(): Collection
    {
        $periode = $this->periodeTerpilih;
        $hariIni = $this->hariIni;
        $zona = config('app.timezone');

        $idTunggakanLama = $this->semuaTunggakan
            ->filter(fn (Tagihan $tagihan) => $tagihan->periode->lt($periode))
            ->keys()
            ->all();

        return Tagihan::query()
            ->with([
                'kontrak',
                'kontrak.kamar:id,nomor_kamar',
                'kontrak.penghuni:id,user_id',
                'kontrak.penghuni.user:id,nama',
                'pembayaranTerakhir',
            ])
            ->where(fn ($query) => $query
                ->where('tagihan.periode', $periode->toDateString())
                ->orWhereIn('tagihan.id', $idTunggakanLama))
            ->get()
            ->map(function (Tagihan $tagihan) use ($hariIni, $zona) {
                $telat = $this->semuaTunggakan->has($tagihan->id);
                $pembayaran = $tagihan->pembayaranTerakhir;
                $ditolak = $tagihan->status_tagihan === StatusTagihan::BelumDibayar
                    && $pembayaran?->status_verifikasi === StatusVerifikasi::Ditolak;

                return [
                    'id' => $tagihan->id,
                    'kamar' => $tagihan->kontrak?->kamar?->nomor_kamar ?? '-',
                    'nama' => $tagihan->kontrak?->penghuni?->user?->nama ?? '-',
                    'periode' => $tagihan->periode,
                    'jatuhTempo' => $tagihan->jatuh_tempo,
                    'nominal' => (int) round((float) $tagihan->nominal),
                    'status' => $tagihan->status_tagihan,
                    'telat' => $telat,
                    'hariLewat' => $telat
                        ? (int) CarbonImmutable::parse($tagihan->jatuh_tempo->toDateString())->diffInDays($hariIni)
                        : 0,
                    'buktiDitolak' => $ditolak ? $pembayaran->diverifikasi_pada?->setTimezone($zona) : null,
                ];
            })
            ->sort(fn (array $a, array $b) => strcmp($a['periode']->toDateString(), $b['periode']->toDateString())
                ?: strnatcmp($a['kamar'], $b['kamar']))
            ->values();
    }

    /**
     * @return array{semua: int, telat: int, belum: int, menunggu: int, lunas: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $baris = $this->semuaBaris;

        return [
            'semua' => $baris->count(),
            'telat' => $baris->where('telat', true)->count(),
            'belum' => $baris->where('status', StatusTagihan::BelumDibayar)->count(),
            'menunggu' => $baris->where('status', StatusTagihan::MenungguVerifikasi)->count(),
            'lunas' => $baris->where('status', StatusTagihan::Lunas)->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function barisTersaring(): Collection
    {
        $baris = $this->semuaBaris;

        $tersaring = match ($this->filterAktif) {
            'telat' => $baris->where('telat', true),
            'belum' => $baris->where('status', StatusTagihan::BelumDibayar),
            'menunggu' => $baris->where('status', StatusTagihan::MenungguVerifikasi),
            'lunas' => $baris->where('status', StatusTagihan::Lunas),
            default => $baris,
        };

        return $tersaring->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function grupLama(): Collection
    {
        return $this->barisTersaring->filter(fn (array $b) => $b['periode']->lt($this->periodeTerpilih))->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function grupPeriode(): Collection
    {
        return $this->barisTersaring->filter(fn (array $b) => $b['periode']->equalTo($this->periodeTerpilih))->values();
    }

    /**
     * Jumlah nominal tagihan periode terpilih per status, dan tunggakan semua periode.
     *
     * @return array{total: int, lunas: int, menunggu: int, belum: int, tunggakan: int, jumlahTunggakan: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $periode = $this->semuaBaris->filter(fn (array $b) => $b['periode']->equalTo($this->periodeTerpilih));

        return [
            'total' => (int) $periode->sum('nominal'),
            'lunas' => (int) $periode->where('status', StatusTagihan::Lunas)->sum('nominal'),
            'menunggu' => (int) $periode->where('status', StatusTagihan::MenungguVerifikasi)->sum('nominal'),
            'belum' => (int) $periode->where('status', StatusTagihan::BelumDibayar)->sum('nominal'),
            'tunggakan' => (int) round((float) $this->semuaTunggakan->sum('nominal')),
            'jumlahTunggakan' => $this->semuaTunggakan->count(),
        ];
    }

    /**
     * Jumlah tagihan periode terpilih dan tanggal jatuh tempo yang paling banyak dipakai; null bila belum diterbitkan.
     *
     * @return array{jumlah: int, tanggal: int}|null
     */
    #[Computed]
    public function infoPeriode(): ?array
    {
        $periode = $this->semuaBaris->filter(fn (array $b) => $b['periode']->equalTo($this->periodeTerpilih));

        if ($periode->isEmpty()) {
            return null;
        }

        $tanggal = $periode->countBy(fn (array $b) => $b['jatuhTempo']->day)->sortDesc()->keys()->first();

        return ['jumlah' => $periode->count(), 'tanggal' => (int) $tanggal];
    }

    /**
     * Rentang periode yang boleh diterbitkan, tiap bulan sebagai YYYY-MM.
     *
     * @return list<string>
     */
    #[Computed]
    public function pilihanPeriodeTerbit(): array
    {
        [$awal, $akhir] = Tagihan::rentangPeriodeTerbit();
        $hasil = [];

        for ($bulan = $awal; $bulan->lte($akhir); $bulan = $bulan->addMonthNoOverflow()) {
            $hasil[] = $bulan->format('Y-m');
        }

        return $hasil;
    }

    /**
     * Kontrak berhak pada periode terpilih yang belum punya tagihan: angka "Kontrak yang ditagih" di pratinjau dialog.
     * 0 bila periode di luar rentang terbit atau tanggal jatuh tempo belum diatur (tidak ada yang bisa diterbitkan).
     */
    #[Computed]
    public function belumDitagih(): int
    {
        try {
            return Tagihan::pratinjauPeriode($this->periodeTerpilih)->dibuat;
        } catch (PenerbitanTagihanDitolak) {
            return 0;
        }
    }

    /**
     * Periode tombol terbitkan: periode yang dilihat bila masih ada kontrak yang belum ditagih, atau bila periode itu
     * belum punya tagihan sama sekali (dialog lalu menjelaskan bila tidak ada kontrak yang berhak, F-10 butir 7).
     * Selain itu bulan setelahnya (asal masih dalam rentang terbit). Null bila tidak ada yang bisa diterbitkan,
     * dan tombol tidak tampil. Tanpa tanggal jatuh tempo tombol tampil nonaktif untuk periode yang dilihat.
     */
    #[Computed]
    public function periodeTombol(): ?CarbonImmutable
    {
        [$awal, $akhir] = Tagihan::rentangPeriodeTerbit();
        $dalamRentang = fn (CarbonImmutable $periode) => $periode->gte($awal) && $periode->lte($akhir);
        $terpilih = $this->periodeTerpilih;
        $berikutnya = $terpilih->addMonthNoOverflow();

        $periodeKosong = $this->infoPeriode === null;

        if (! $this->jatuhTempoSiap || $this->belumDitagih > 0 || $periodeKosong && $dalamRentang($terpilih)) {
            return $dalamRentang($terpilih) ? $terpilih : null;
        }

        return $dalamRentang($berikutnya) ? $berikutnya : null;
    }

    /**
     * Periode di dialog; isian yang tidak valid dianggap periode bawaan supaya tampilan tidak galat.
     */
    #[Computed]
    public function periodeDialog(): CarbonImmutable
    {
        return $this->parsePeriode($this->periodeTerbit) ?? $this->periodeTombol ?? $this->periodeTerpilih;
    }

    #[Computed]
    public function pratinjau(): ?HasilPenerbitan
    {
        $periode = $this->dialogDibuka ? $this->parsePeriode($this->periodeTerbit) : null;

        if ($periode === null) {
            return null;
        }

        try {
            return Tagihan::pratinjauPeriode($periode);
        } catch (PenerbitanTagihanDitolak) {
            return null;
        }
    }

    public function bukaTerbit(): void
    {
        $this->authorize('terbitkan', Tagihan::class);

        if (! $this->jatuhTempoSiap) {
            Flux::toast(variant: 'danger', text: PenerbitanTagihanDitolak::jatuhTempoBelumDiatur()->getMessage());

            return;
        }

        // Dialog membuka periode yang sama dengan label tombol.
        $periodeTombol = $this->periodeTombol;

        if ($periodeTombol === null) {
            return;
        }

        $this->resetErrorBag();
        $this->periodeTerbit = $periodeTombol->format('Y-m');
        $this->dialogDibuka = true;
        unset($this->pratinjau);

        Flux::modal('terbitkan-tagihan')->show();
    }

    public function updatedPeriodeTerbit(): void
    {
        $this->resetErrorBag('periodeTerbit');
        unset($this->pratinjau);
    }

    public function terbitkan(): void
    {
        $this->authorize('terbitkan', Tagihan::class);

        $periode = $this->parsePeriode($this->periodeTerbit);

        if ($periode === null) {
            $this->addError('periodeTerbit', 'Pilih periode yang valid.');

            return;
        }

        try {
            $hasil = Tagihan::terbitkanPeriode($periode);
        } catch (PenerbitanTagihanDitolak $e) {
            if (! $this->jatuhTempoSiap) {
                Flux::modal('terbitkan-tagihan')->close();
                Flux::toast(variant: 'danger', text: $e->getMessage());
            } else {
                $this->addError('periodeTerbit', $e->getMessage());
            }

            return;
        }

        $label = $hasil->periode->locale('id')->translatedFormat('F Y');

        if ($hasil->dibuat > 0) {
            $pesan = "{$hasil->dibuat} tagihan {$label} diterbitkan.".($hasil->dilewati > 0 ? " {$hasil->dilewati} dilewati karena sudah ada." : '');
            $varian = 'success';
        } elseif ($hasil->dilewati > 0) {
            $pesan = "Semua kontrak sudah punya tagihan {$label}. {$hasil->dilewati} dilewati.";
            $varian = 'warning';
        } else {
            $pesan = "Tidak ada kontrak aktif pada {$label}. Tidak ada tagihan yang dibuat.";
            $varian = 'warning';
        }

        $this->periode = $hasil->periode->format('Y-m');
        $this->dialogDibuka = false;
        $this->reset('periodeTerbit');
        unset($this->semuaTunggakan, $this->semuaBaris, $this->hitungan, $this->barisTersaring, $this->grupLama, $this->grupPeriode, $this->ringkasan, $this->infoPeriode, $this->belumDitagih, $this->periodeTombol, $this->pratinjau, $this->periodeTerpilih);

        Flux::modal('terbitkan-tagihan')->close();
        Flux::toast(variant: $varian, text: $pesan);
    }
}; ?>

@php
    $bisaKelola = $this->bisaKelola;
    $periode = $this->periodeTerpilih;
    $hariIni = $this->hariIni;
    $siap = $this->jatuhTempoSiap;
    $hitungan = $this->hitungan;
    $ringkasan = $this->ringkasan;
    $info = $this->infoPeriode;
    $grupLama = $this->grupLama;
    $grupPeriode = $this->grupPeriode;
    $pr = $this->pratinjau;

    // Nama bulan Indonesia; tahun ditambahkan bila berbeda dari tahun berjalan ("Januari 2027").
    $bulan = fn ($tanggal) => $tanggal->locale('id')->translatedFormat($tanggal->year === $hariIni->year ? 'F' : 'F Y');
    $bulanTahun = fn ($tanggal) => $tanggal->locale('id')->translatedFormat('F Y');
    $tanggalPendek = fn ($tanggal) => $tanggal->locale('id')->translatedFormat('j M');
    $tanggalPanjang = fn ($tanggal) => $tanggal->locale('id')->translatedFormat('j F Y');
    $statusUi = fn ($status) => match ($status) {
        \App\Enums\StatusTagihan::Lunas => ['ok', 'Lunas'],
        \App\Enums\StatusTagihan::MenungguVerifikasi => ['tunggu', 'Menunggu'],
        default => ['belum', 'Belum Bayar'],
    };

    $labelPeriode = $bulan($periode);
    $periodeTombol = $this->periodeTombol;
    $labelAriaTerbit = $periodeTombol ? 'Terbitkan tagihan '.$bulanTahun($periodeTombol) : null;
    $belumDitagih = $this->belumDitagih;
    $adaIsi = $grupLama->isNotEmpty() || $grupPeriode->isNotEmpty();
    $belumAdaApaPun = $hitungan['semua'] === 0;


    $labelDialog = $bulanTahun($this->periodeDialog);

    $tautanPeriode = fn ($tanggal) => route('tagihan.index', ['periode' => $tanggal->format('Y-m')]);
    $pilihanPeriode = [
        ['tanggal' => $periode->subMonthNoOverflow(), 'aktif' => false],
        ['tanggal' => $periode, 'aktif' => true],
        ['tanggal' => $periode->addMonthNoOverflow(), 'aktif' => false],
    ];

    $kelasTh = 'border-b border-border px-5 py-3.5 whitespace-nowrap teks-kolom';
@endphp

<section class="flex w-full flex-col gap-6">
    <x-judul-halaman judul="Tagihan" :lebar="true">
        <nav aria-label="Periode" class="grid h-10 w-full grid-cols-3 overflow-hidden rounded-lg border border-border-strong bg-surface sm:inline-flex sm:w-auto sm:gap-1 sm:rounded-[10px] sm:border-0 sm:bg-surface-muted sm:p-1" data-test="pemilih-periode">
            @foreach ($pilihanPeriode as $pilihan)
                <a
                    href="{{ $tautanPeriode($pilihan['tanggal']) }}"
                    wire:click.prevent="pilihPeriode('{{ $pilihan['tanggal']->format('Y-m') }}')"
                    @if ($pilihan['aktif']) aria-current="true" @endif
                    @class([
                        'segmen flex items-center justify-center border-s border-border-strong no-underline first:border-s-0 sm:h-8 sm:rounded-[7px] sm:border-0 sm:px-3.5 teks-chip',
                        'bg-chip-active text-white sm:bg-surface sm:text-ink sm:shadow-[0_1px_0_#dedbd3]' => $pilihan['aktif'],
                        'bg-surface text-ink sm:bg-transparent sm:text-[#33403d]' => ! $pilihan['aktif'],
                    ])
                >{{ $bulan($pilihan['tanggal']) }}</a>
            @endforeach
        </nav>

        @if ($bisaKelola && $periodeTombol)
            <flux:button variant="primary" wire:click="bukaTerbit" :disabled="! $siap" :aria-label="$labelAriaTerbit" class="tombol-utama max-sm:w-full" data-test="terbitkan-tagihan">
                Terbitkan
            </flux:button>
        @endif
    </x-judul-halaman>

    @unless ($siap)
        <flux:callout variant="warning" icon="exclamation-triangle" data-test="callout-jatuh-tempo">
            <flux:callout.text>
                @if ($bisaKelola)
                    Atur tanggal jatuh tempo di <a href="{{ route('pengaturan.index') }}" wire:navigate class="font-semibold">Pengaturan</a> dulu.
                @else
                    Tanggal jatuh tempo belum diatur.
                @endif
            </flux:callout.text>
        </flux:callout>
    @endunless

    <x-ringkasan-angka
        :label="'Ringkasan '.$labelPeriode"
        :items="[
            ['kunci' => 'total', 'label' => 'Total', 'nilai' => Format::rupiah($ringkasan['total'])],
            ['kunci' => 'lunas', 'label' => 'Lunas', 'nilai' => Format::rupiah($ringkasan['lunas'])],
            ['kunci' => 'menunggu', 'label' => 'Menunggu', 'nilai' => Format::rupiah($ringkasan['menunggu'])],
            ['kunci' => 'belum', 'label' => 'Belum Bayar', 'nilai' => Format::rupiah($ringkasan['belum'])],
            ['kunci' => 'tunggakan', 'label' => 'Tunggakan', 'nilai' => Format::rupiah($ringkasan['tunggakan']), 'keterangan' => $ringkasan['jumlahTunggakan'].' tagihan, semua periode', 'nada' => 'bahaya'],
        ]"
    />

    <div role="group" aria-label="Saring status" class="baris-filter -mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
        <x-chip-filter :aktif="$this->filterAktif === 'semua'" :jumlah="$hitungan['semua']" wire:click="$set('filterStatus', 'semua')">Semua</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'telat'" :jumlah="$hitungan['telat']" wire:click="$set('filterStatus', 'telat')">Telat</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'belum'" :jumlah="$hitungan['belum']" wire:click="$set('filterStatus', 'belum')">Belum Bayar</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'menunggu'" :jumlah="$hitungan['menunggu']" wire:click="$set('filterStatus', 'menunggu')">Menunggu</x-chip-filter>
        <x-chip-filter :aktif="$this->filterAktif === 'lunas'" :jumlah="$hitungan['lunas']" wire:click="$set('filterStatus', 'lunas')">Lunas</x-chip-filter>
    </div>

    <div wire:loading.class="opacity-60" class="transisi-opasitas flex flex-col gap-4">
        @if (! $adaIsi)
            <div class="flex flex-col items-center gap-4 rounded-2xl border border-border bg-surface px-4 py-10 text-center" data-test="pesan-kosong">
                @if ($belumAdaApaPun)
                    <p class="m-0 text-ink-muted">Tagihan {{ $labelPeriode }} belum diterbitkan.</p>
                    @if ($bisaKelola && $periodeTombol)
                        <flux:button variant="primary" wire:click="bukaTerbit" :disabled="! $siap" :aria-label="$labelAriaTerbit" class="tombol-utama" data-test="terbitkan-tagihan-kosong">Terbitkan</flux:button>
                    @endif
                @else
                    <p class="m-0 text-ink-muted">Tidak ada tagihan yang sesuai filter.</p>
                @endif
            </div>
        @else
            {{-- Desktop: tabel dalam kartu --}}
            <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
                <table class="w-full min-w-[760px] border-collapse" data-test="tabel-tagihan">
                    <thead>
                        <tr>
                            <th scope="col" class="{{ $kelasTh }} text-start">Kamar</th>
                            <th scope="col" class="{{ $kelasTh }} text-start">Penghuni</th>
                            <th scope="col" class="{{ $kelasTh }} text-start">Periode</th>
                            <th scope="col" class="{{ $kelasTh }} text-start">Jatuh Tempo</th>
                            <th scope="col" class="{{ $kelasTh }} text-end">Nominal</th>
                            <th scope="col" class="{{ $kelasTh }} text-start">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([['Telat Sebelumnya', $grupLama, 'grup-lama'], [$labelPeriode, $grupPeriode, 'grup-periode']] as [$judulGrup, $grup, $kunciGrup])
                            @if ($grup->isNotEmpty())
                                <tr class="bg-[#faf9f6]" data-test="{{ $kunciGrup }}">
                                    <th scope="colgroup" colspan="6" class="h-10 border-t border-row-divider px-5 text-start first:border-t-0 teks-kolom">{{ $judulGrup }}</th>
                                </tr>
                                @foreach ($grup as $b)
                                    @php($ui = $statusUi($b['status']))
                                    <tr wire:key="tabel-{{ $b['id'] }}" class="h-[60px] border-t border-row-divider" data-test="baris-tagihan">
                                        <td class="num px-5 teks-nomor-kamar">{{ $b['kamar'] }}</td>
                                        <td class="px-5 whitespace-nowrap">{{ $b['nama'] }}</td>
                                        <td class="px-5 whitespace-nowrap">{{ $bulan($b['periode']) }}</td>
                                        <td class="px-5 whitespace-nowrap">
                                            @if ($b['telat'])
                                                <x-status jenis="telat">{{ $tanggalPendek($b['jatuhTempo']) }}, Lewat {{ $b['hariLewat'] }} Hari</x-status>
                                            @else
                                                {{ $tanggalPendek($b['jatuhTempo']) }}
                                            @endif
                                        </td>
                                        <td class="num px-5 text-end font-semibold whitespace-nowrap">{{ Format::rupiah($b['nominal']) }}</td>
                                        <td class="px-5">
                                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5">
                                                <x-status :jenis="$ui[0]">{{ $ui[1] }}</x-status>
                                                @if ($b['buktiDitolak'])
                                                    <span class="whitespace-nowrap teks-bantuan">Ditolak, {{ $tanggalPendek($b['buktiDitolak']) }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Ponsel: judul grup dan kartu per tagihan --}}
            <div class="flex flex-col gap-4 md:hidden" data-test="daftar-ponsel">
                @foreach ([['Telat Sebelumnya', $grupLama], [$labelPeriode, $grupPeriode]] as [$judulGrup, $grup])
                    @if ($grup->isNotEmpty())
                        <h2 class="m-0 mb-[-4px] teks-bagian">{{ $judulGrup }}</h2>
                        <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0">
                            @foreach ($grup as $b)
                                @php($ui = $statusUi($b['status']))
                                <li wire:key="kartu-{{ $b['id'] }}" class="flex flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="flex min-w-0 items-baseline gap-2.5">
                                            <span class="num teks-nomor-kamar">{{ $b['kamar'] }}</span>
                                            <span class="truncate font-semibold">{{ $b['nama'] }}</span>
                                        </span>
                                        <strong class="num shrink-0">{{ Format::rupiah($b['nominal']) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="teks-bantuan">{{ $b['periode']->locale('id')->translatedFormat('M') }}, Jatuh Tempo {{ $tanggalPendek($b['jatuhTempo']) }}</span>
                                        <x-status :jenis="$ui[0]">{{ $ui[1] }}</x-status>
                                    </div>
                                    @if ($b['telat'])
                                        <x-status jenis="telat" class="teks-bantuan!">Lewat {{ $b['hariLewat'] }} Hari</x-status>
                                    @endif
                                    @if ($b['buktiDitolak'])
                                        <span class="num teks-bantuan">Ditolak, {{ $tanggalPendek($b['buktiDitolak']) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    @if ($bisaKelola)
        <x-dialog-konfirmasi
            name="terbitkan-tagihan"
            :judul="'Terbitkan Tagihan '.$labelDialog.'?'"
            label-aksi="Terbitkan"
            variant="biasa"
            wire:click="terbitkan"
            :disabled="$pr === null || $pr->dibuat === 0"
            data-test="konfirmasi-terbitkan"
        >
            <x-slot:ringkasan>
                <dt>Kontrak Ditagih</dt>
                <dd data-test="pratinjau-dibuat">{{ $pr?->dibuat ?? 0 }}</dd>
                <dt>Sudah Ditagih</dt>
                <dd data-test="pratinjau-dilewati">{{ $pr?->dilewati ?? 0 }}</dd>
                <dt>Jatuh Tempo</dt>
                <dd data-test="pratinjau-jatuh-tempo">{{ $pr?->jatuhTempo ? $tanggalPanjang($pr->jatuhTempo) : '-' }}</dd>
                <dt>Total</dt>
                <dd data-test="pratinjau-total">{{ Format::rupiah($pr?->totalNominal ?? 0) }}</dd>
                @if (($pr?->tidakPenuh ?? 0) > 0)
                    <dt>Tidak Penuh Sebulan</dt>
                    <dd data-test="pratinjau-tidak-penuh">{{ $pr->tidakPenuh }} (dihitung {{ (new \App\Support\Tagihan\PenghitungNominal)->strategi() === \App\Support\Tagihan\PenghitungNominal::PRORATA ? 'prorata harian' : 'tarif penuh' }})</dd>
                @endif
            </x-slot:ringkasan>

            <x-slot:akibat>{{ $pr?->dibuat ?? 0 }} tagihan baru akan langsung tampil di Tagihan penghuni dan tidak bisa dibatalkan. Kontrak yang sudah ditagih dilewati.</x-slot:akibat>

            @if ($pr?->jatuhTempo && $pr->jatuhTempo->lt($hariIni))
                <x-slot:catatan>Jatuh tempo {{ $tanggalPendek($pr->jatuhTempo) }} sudah lewat, langsung terhitung tunggakan.</x-slot:catatan>
            @endif

            <x-slot:isi>
                <flux:field>
                    <flux:label>Periode</flux:label>
                    <flux:select wire:model.live="periodeTerbit" data-test="pilih-periode-terbit">
                        @foreach ($this->pilihanPeriodeTerbit as $nilai)
                            <flux:select.option value="{{ $nilai }}" wire:key="periode-{{ $nilai }}">{{ $bulanTahun(\Carbon\CarbonImmutable::parse($nilai.'-01')) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="periodeTerbit" />
                </flux:field>

                @if ($pr !== null && $pr->dibuat === 0)
                    <p class="m-0 teks-bantuan" data-test="pratinjau-kosong">Tidak ada kontrak yang perlu ditagih untuk {{ $labelDialog }}.</p>
                @endif
            </x-slot:isi>
        </x-dialog-konfirmasi>
    @endif
</section>
