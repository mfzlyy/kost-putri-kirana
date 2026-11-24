<?php

use App\Enums\LokasiKeluhan;
use App\Exceptions\KeluhanGagalDikirim;
use App\Exceptions\LokasiKeluhanTidakValid;
use App\Models\Fasilitas;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\TiketKeluhan;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ajukan Keluhan')] #[Layout('layouts::app', ['tanpaTabPenghuni' => true])] class extends Component {
    public const BATAS_DESKRIPSI = 1000;

    /** Nilai LokasiKeluhan: kamar atau area_bersama. */
    public string $lokasi = '';

    /** ID fasilitas terpilih; kosong bila belum memilih. Selalu diperiksa ulang di server terhadap lokasi. */
    public string $fasilitas_id = '';

    public string $deskripsi = '';

    /** Galat domain dari model, ditampilkan di atas form. */
    public ?string $galatForm = null;

    public function mount(): void
    {
        $penghuni = $this->penghuni;

        if ($penghuni !== null) {
            $this->authorize('create', TiketKeluhan::class);
        }

        $this->lokasi = $this->kontrakAktif !== null
            ? LokasiKeluhan::Kamar->value
            : LokasiKeluhan::AreaBersama->value;
    }

    /**
     * Selalu penghuni milik pengguna yang login; penghuni_id tidak pernah datang dari browser (ATB-10).
     */
    #[Computed]
    public function penghuni(): ?Penghuni
    {
        return Penghuni::query()->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function kontrakAktif(): ?Kontrak
    {
        return $this->penghuni?->kontrakAktif()->with('kamar:id,nomor_kamar')->first();
    }

    /**
     * Fasilitas yang boleh dipilih untuk lokasi terpilih, dibaca dari basis data (bukan dari daftar di browser).
     *
     * @return Collection<int, Fasilitas>
     */
    #[Computed]
    public function daftarFasilitas(): Collection
    {
        $lokasi = LokasiKeluhan::tryFrom($this->lokasi);

        if ($lokasi === null) {
            return new Collection;
        }

        return Fasilitas::query()
            ->untukLokasi($lokasi, $this->kontrakAktif?->kamar)
            ->get(['fasilitas.id', 'fasilitas.nama_fasilitas']);
    }

    /**
     * Mengganti lokasi mengosongkan pilihan fasilitas, karena daftarnya berbeda.
     */
    public function updatedLokasi(): void
    {
        $this->fasilitas_id = '';
        $this->galatForm = null;
        $this->resetErrorBag();
        unset($this->daftarFasilitas);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'lokasi' => ['required', Rule::enum(LokasiKeluhan::class)],
            'fasilitas_id' => ['required', 'integer'],
            'deskripsi' => ['required', 'string', 'max:'.self::BATAS_DESKRIPSI],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'lokasi.required' => 'Pilih lokasi.',
            'lokasi.enum' => 'Pilih lokasi.',
            'fasilitas_id.required' => 'Pilih fasilitas yang rusak.',
            'fasilitas_id.integer' => 'Pilih fasilitas yang rusak.',
            'deskripsi.required' => 'Tulis deskripsi kerusakan.',
            'deskripsi.max' => 'Deskripsi paling banyak 1.000 karakter.',
        ];
    }

    public function kirim(): void
    {
        $this->authorize('create', TiketKeluhan::class);

        $this->galatForm = null;
        $this->deskripsi = trim($this->deskripsi);
        $this->validate();

        $lokasi = LokasiKeluhan::from($this->lokasi);
        $kontrak = $this->kontrakAktif;

        if ($lokasi === LokasiKeluhan::Kamar && $kontrak === null) {
            $this->addError('lokasi', LokasiKeluhanTidakValid::PESAN_TANPA_KONTRAK);

            return;
        }

        $fasilitas = Fasilitas::query()
            ->untukLokasi($lokasi, $kontrak?->kamar)
            ->whereKey((int) $this->fasilitas_id)
            ->first();

        if ($fasilitas === null) {
            $this->addError('fasilitas_id', LokasiKeluhanTidakValid::PESAN_FASILITAS);

            return;
        }

        try {
            $tiket = TiketKeluhan::ajukanKeluhan($this->penghuni, $lokasi, $fasilitas, $this->deskripsi, auth()->user());
        } catch (LokasiKeluhanTidakValid $e) {
            $this->addError($e->field, $e->getMessage());

            return;
        } catch (KeluhanGagalDikirim $e) {
            $this->galatForm = $e->getMessage();

            return;
        }

        session()->flash('keluhan_baru', $tiket->nomor_tiket);

        $this->redirectRoute('keluhan-saya.index', navigate: true);
    }
}; ?>

@php
    $penghuni = $this->penghuni;
@endphp

<section class="flex w-full max-w-[880px] flex-col gap-6">
    {{-- Ponsel: header putih 60px dengan tombol kembali. Desktop: tautan kembali lalu judul. --}}
    <header class="max-md:-mx-4 max-md:-mt-5 max-md:flex max-md:h-[60px] max-md:items-center max-md:gap-1 max-md:border-b max-md:border-divider max-md:bg-surface max-md:px-2 md:flex md:flex-col md:gap-2">
        <a
            href="{{ route('keluhan-saya.index') }}"
            wire:navigate
            aria-label="Kembali ke keluhan"
            class="flex size-11 shrink-0 items-center justify-center rounded-lg text-ink md:hidden"
        >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <a href="{{ route('keluhan-saya.index') }}" wire:navigate class="font-semibold no-underline max-md:hidden">Keluhan</a>
        <h1 class="teks-judul m-0">Ajukan Keluhan</h1>
    </header>

    @if ($penghuni === null)
        <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-kosong">Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.</p>
    @else
        @php
            $kontrak = $this->kontrakAktif;
            $daftar = $this->daftarFasilitas;
            $adaKontrak = $kontrak !== null;
            $nomorKamar = $kontrak?->kamar?->nomor_kamar;
        @endphp

        <form wire:submit="kirim" novalidate class="flex w-full flex-col gap-[22px] md:max-w-[640px] md:gap-5 md:rounded-2xl md:border md:border-border md:bg-surface md:p-6">
            @if ($galatForm !== null)
                <div role="alert" class="rounded-[10px] bg-danger-container px-3.5 py-3 text-on-danger-container teks-badan" data-test="galat-form">{{ $galatForm }}</div>
            @endif

            {{-- Lokasi --}}
            <fieldset class="m-0 min-w-0 border-0 p-0" @error('lokasi') aria-describedby="galat-lokasi" @enderror>
                <legend class="mb-1.5 p-0 teks-label">Lokasi</legend>
                <div @class([
                    'inline-grid grid-cols-2 overflow-hidden rounded-lg border max-md:w-full md:min-w-[320px]',
                    'border-border-strong' => ! $errors->has('lokasi'),
                    'border-red-400' => $errors->has('lokasi'),
                ])>
                    @foreach ([
                        ['nilai' => 'kamar', 'label' => $adaKontrak ? 'Kamar '.$nomorKamar : 'Kamar', 'nonaktif' => ! $adaKontrak],
                        ['nilai' => 'area_bersama', 'label' => 'Area Bersama', 'nonaktif' => false],
                    ] as $pilihan)
                        <label class="relative flex h-11 cursor-pointer items-center justify-center bg-surface px-4 font-semibold has-[:checked]:bg-chip-active has-[:checked]:text-white has-[:disabled]:cursor-not-allowed has-[:disabled]:bg-surface-disabled has-[:disabled]:text-ink-muted has-[:focus-visible]:outline-2 has-[:focus-visible]:-outline-offset-2 has-[:focus-visible]:outline-primary">
                            <input
                                type="radio"
                                name="lokasi"
                                value="{{ $pilihan['nilai'] }}"
                                wire:model.live="lokasi"
                                class="sr-only"
                                @disabled($pilihan['nonaktif'])
                                @error('lokasi') aria-invalid="true" @enderror
                            >
                            {{ $pilihan['label'] }}
                        </label>
                    @endforeach
                </div>
                @if (! $adaKontrak)
                    <p class="m-0 mt-1.5 teks-bantuan" data-test="bantuan-kontrak">Belum ada kontrak aktif.</p>
                @endif
                @error('lokasi')
                    <p id="galat-lokasi" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-lokasi">{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- Fasilitas --}}
            <fieldset class="m-0 min-w-0 border-0 p-0" @error('fasilitas_id') aria-describedby="galat-fasilitas" @enderror>
                <legend class="mb-1.5 p-0 teks-label">Fasilitas</legend>
                @if ($daftar->isEmpty())
                    <p class="m-0 text-ink-muted teks-badan" data-test="fasilitas-kosong">Belum ada fasilitas di lokasi ini. Hubungi pengelola kos.</p>
                @else
                    <div class="flex flex-wrap gap-2" data-test="daftar-fasilitas">
                        @foreach ($daftar as $fasilitas)
                            <label
                                wire:key="fasilitas-{{ $lokasi }}-{{ $fasilitas->id }}"
                                @class([
                                    'relative inline-flex h-10 cursor-pointer items-center justify-center rounded-lg border bg-surface px-3.5 font-[550] has-[:checked]:border-2 has-[:checked]:border-primary has-[:checked]:bg-primary-container has-[:checked]:font-semibold has-[:checked]:text-on-primary-container has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary',
                                    'border-border-strong' => ! $errors->has('fasilitas_id'),
                                    'border-red-400' => $errors->has('fasilitas_id'),
                                ])
                            >
                                <input
                                    type="radio"
                                    name="fasilitas_id"
                                    value="{{ $fasilitas->id }}"
                                    wire:model="fasilitas_id"
                                    class="sr-only"
                                    @error('fasilitas_id') aria-invalid="true" @enderror
                                >
                                {{ $fasilitas->nama_fasilitas }}
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('fasilitas_id')
                    <p id="galat-fasilitas" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-fasilitas">{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- Deskripsi --}}
            <div x-data="{ jumlah: {{ mb_strlen($deskripsi) }} }">
                <label for="deskripsi" class="mb-1.5 block teks-label">Deskripsi</label>
                <textarea
                    id="deskripsi"
                    wire:model="deskripsi"
                    x-on:input="jumlah = $event.target.value.length"
                    placeholder="Contoh: AC tidak dingin sejak kemarin malam"
                    @class([
                        'min-h-[120px] w-full resize-y rounded-lg border bg-surface px-3 py-3 text-ink teks-isian',
                        'border-border-strong' => ! $errors->has('deskripsi'),
                        'border-red-400' => $errors->has('deskripsi'),
                    ])
                    @error('deskripsi') aria-invalid="true" aria-describedby="galat-deskripsi penghitung" @else aria-describedby="penghitung" @enderror
                ></textarea>
                <p id="penghitung" class="num m-0 mt-1.5 teks-bantuan" data-test="penghitung"><span x-text="jumlah.toLocaleString('id-ID')">{{ number_format(mb_strlen($deskripsi), 0, ',', '.') }}</span>/1.000</p>
                @error('deskripsi')
                    <p id="galat-deskripsi" role="alert" class="m-0 mt-1.5 teks-galat" data-test="galat-deskripsi">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-4 md:border-t md:border-divider md:pt-[18px]">
                <button
                    type="submit"
                    @disabled($daftar->isEmpty())
                    class="tombol w-full bg-primary text-white hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60 md:w-auto"
                >
                    <span wire:loading.remove wire:target="kirim">Kirim</span>
                    <span wire:loading wire:target="kirim">Mengirim…</span>
                </button>
            </div>
        </form>
    @endif
</section>
