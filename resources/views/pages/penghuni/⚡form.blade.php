<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusPenghuni;
use App\Models\Penghuni;
use App\Models\User;
use App\Support\Format;
use Flux\Flux;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Penghuni')] class extends Component {
    #[Locked]
    public ?int $penghuniId = null;

    /** Nama terakhir yang diisikan otomatis dari akun; dipakai agar ketikan pengguna tidak tertimpa. */
    #[Locked]
    public string $namaOtomatis = '';

    #[Locked]
    public bool $identitasTerbuka = false;

    public string $user_id = '';

    public string $nama = '';

    /** Hanya menampung ketikan baru. Nomor identitas tersimpan tidak pernah dimasukkan ke properti publik (NFR-02). */
    public string $nik = '';

    public string $no_telepon = '';

    public function mount(?Penghuni $penghuni = null): void
    {
        if ($penghuni === null || ! $penghuni->exists) {
            $this->authorize('create', Penghuni::class);

            return;
        }

        $this->authorize('update', $penghuni);

        $penghuni->load('user:id,nama,email');

        $this->penghuniId = $penghuni->id;
        $this->nama = (string) $penghuni->user?->nama;
        $this->no_telepon = (string) $penghuni->no_telepon;
    }

    #[Computed]
    public function penghuni(): ?Penghuni
    {
        if ($this->penghuniId === null) {
            return null;
        }

        return Penghuni::query()
            ->with(['user:id,nama,email', 'kontrakAktif.kamar:id,nomor_kamar'])
            ->findOrFail($this->penghuniId);
    }

    /**
     * Akun yang boleh dipilih: berperan penghuni, aktif, dan belum punya data penghuni.
     */
    private function queryAkunTersedia()
    {
        return User::query()
            ->where('peran', PeranPengguna::Penghuni->value)
            ->where('status_akun', StatusAkun::Aktif->value)
            ->whereDoesntHave('penghuni');
    }

    #[Computed]
    public function akunTersedia()
    {
        return $this->queryAkunTersedia()->orderBy('nama')->get(['id', 'nama', 'email']);
    }

    /**
     * Hasil Policy untuk menandai keluar; pesannya ditampilkan bila ditolak (masih ada kontrak aktif).
     */
    #[Computed]
    public function izinNonaktifkan(): ?Response
    {
        return $this->penghuni === null ? null : Gate::inspect('nonaktifkan', $this->penghuni);
    }

    /**
     * Nomor identitas utuh diambil di server hanya saat dibuka, tidak pernah menjadi properti publik.
     */
    #[Computed]
    public function identitasUtuh(): ?string
    {
        if (! $this->identitasTerbuka || $this->penghuniId === null) {
            return null;
        }

        $penghuni = Penghuni::query()->findOrFail($this->penghuniId);

        $this->authorize('lihatIdentitas', $penghuni);

        return Format::kelompokEmpat((string) $penghuni->no_identitas_enc);
    }

    /**
     * Memilih akun mengisi Nama lengkap, selama pengguna belum mengubah isiannya sendiri.
     */
    public function updatedUserId(string $nilai): void
    {
        if ($this->penghuniId !== null) {
            return;
        }

        $akun = $nilai === '' ? null : $this->queryAkunTersedia()->whereKey((int) $nilai)->first(['id', 'nama']);

        if ($akun !== null && (trim($this->nama) === '' || $this->nama === $this->namaOtomatis)) {
            $this->nama = $akun->nama;
            $this->namaOtomatis = $akun->nama;
        }
    }

    public function tampilkanIdentitas(): void
    {
        $this->authorize('lihatIdentitas', Penghuni::query()->findOrFail($this->penghuniId));

        $this->identitasTerbuka = true;
    }

    public function sembunyikanIdentitas(): void
    {
        $this->authorize('lihatIdentitas', Penghuni::query()->findOrFail($this->penghuniId));

        $this->identitasTerbuka = false;
        unset($this->identitasUtuh);
    }

    public function simpan(): void
    {
        $penghuni = null;

        if ($this->penghuniId === null) {
            $this->authorize('create', Penghuni::class);
        } else {
            $penghuni = Penghuni::query()->findOrFail($this->penghuniId);
            $this->authorize('update', $penghuni);
        }

        $this->nama = trim($this->nama);
        $this->nik = preg_replace('/\s+/', '', $this->nik) ?? '';
        $this->no_telepon = Format::normalisasiTelepon($this->no_telepon);

        $aturan = [
            'nama' => ['required', 'string', 'max:100'],
            'nik' => $penghuni !== null && $this->nik === ''
                ? ['nullable']
                : [
                    'bail',
                    'required',
                    'regex:/^[0-9]+$/',
                    function (string $atribut, mixed $nilai, Closure $gagal) {
                        $jumlah = strlen((string) $nilai);

                        if ($jumlah !== 16) {
                            $gagal("NIK harus 16 angka. Baru {$jumlah} angka.");
                        }
                    },
                ],
            'no_telepon' => ['bail', 'required', 'regex:/^0[0-9]{9,13}$/'],
        ];

        if ($penghuni === null) {
            $aturan['user_id'] = [
                'bail',
                'required',
                Rule::exists('users', 'id')
                    ->where('peran', PeranPengguna::Penghuni->value)
                    ->where('status_akun', StatusAkun::Aktif->value),
                Rule::unique('penghuni', 'user_id'),
            ];
        }

        $this->validate($aturan, [
            'user_id.required' => 'Pilih akun penghuni.',
            'user_id.exists' => 'Pilih akun penghuni.',
            'user_id.unique' => 'Akun ini sudah punya data penghuni.',
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.max' => 'Nama lengkap paling banyak 100 karakter.',
            'nik.required' => 'Nomor identitas wajib diisi.',
            'nik.regex' => 'NIK hanya berisi angka.',
            'no_telepon.required' => 'Nomor telepon wajib diisi.',
            'no_telepon.regex' => 'Isi nomor yang diawali 0 atau +62, 10 sampai 14 angka.',
        ]);

        DB::transaction(function () use ($penghuni) {
            if ($penghuni === null) {
                User::query()->whereKey((int) $this->user_id)->update(['nama' => $this->nama]);

                Penghuni::query()->create([
                    'user_id' => (int) $this->user_id,
                    'no_identitas_enc' => $this->nik,
                    'no_telepon' => $this->no_telepon,
                ]);

                return;
            }

            $penghuni->user()->update(['nama' => $this->nama]);

            $penghuni->no_telepon = $this->no_telepon;

            if ($this->nik !== '') {
                $penghuni->no_identitas_enc = $this->nik;
            }

            $penghuni->save();
        });

        // Toast dibawa lewat session flash karena halaman daftar dimuat ulang oleh redirect.
        session()->flash('toast', "Data {$this->nama} disimpan.");

        $this->redirectRoute('penghuni.index', navigate: true);
    }

    public function nonaktifkanPenghuni(): void
    {
        abort_if($this->penghuniId === null, 404);

        $penghuni = Penghuni::query()->findOrFail($this->penghuniId);

        $this->authorize('nonaktifkan', $penghuni);

        $penghuni->nonaktifkan();

        $namaTersimpan = $this->penghuni->user?->nama;
        unset($this->penghuni, $this->izinNonaktifkan);

        Flux::modal('tandai-keluar')->close();
        Flux::toast(variant: 'success', text: "{$namaTersimpan} ditandai sudah keluar.");
    }

    public function aktifkanKembali(): void
    {
        abort_if($this->penghuniId === null, 404);

        $penghuni = Penghuni::query()->findOrFail($this->penghuniId);

        $this->authorize('aktifkan', $penghuni);

        $penghuni->aktifkan();

        $namaTersimpan = $this->penghuni->user?->nama;
        unset($this->penghuni, $this->izinNonaktifkan);

        Flux::toast(variant: 'success', text: "{$namaTersimpan} aktif kembali.");
    }
}; ?>

@php
    $penghuni = $this->penghuni;
    $kataJumlah = [1 => 'Satu', 2 => 'Dua', 3 => 'Tiga', 4 => 'Empat'];
    $jumlahGalat = count($errors->keys());
    $tanpaAkun = $penghuni === null && $this->akunTersedia->isEmpty();
    $namaTampil = $penghuni?->user?->nama ?? $nama;
@endphp

<section class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="{{ route('penghuni.index') }}" wire:navigate class="tautan-aksi hidden w-fit lg:inline">Penghuni</a>
        <div class="flex items-start gap-1">
            <a href="{{ route('penghuni.index') }}" wire:navigate aria-label="Kembali ke penghuni" class="tombol-ikon -ms-2 inline-flex size-11 shrink-0 items-center justify-center lg:hidden">
                <flux:icon.arrow-left class="size-5" />
            </a>
            <x-judul-halaman class="min-w-0 flex-1" :judul="$penghuni ? 'Ubah Penghuni' : 'Tambah Penghuni'" />
        </div>
    </div>

    <div class="flex flex-wrap items-start gap-6">
        <form wire:submit="simpan" novalidate class="flex flex-[2_1_460px] flex-col gap-[18px] rounded-2xl border border-border bg-surface p-4 sm:p-6">
            @if ($jumlahGalat > 0)
                <p role="alert" class="m-0 rounded-[10px] bg-danger-container px-3.5 py-3 text-on-danger-container teks-badan" data-test="ringkasan-galat">
                    {{ $kataJumlah[$jumlahGalat] ?? $jumlahGalat }} isian perlu diperbaiki sebelum disimpan.
                </p>
            @endif

            @if ($penghuni === null)
                @if ($tanpaAkun)
                    <flux:callout variant="warning" icon="exclamation-triangle" data-test="tanpa-akun">
                        <flux:callout.text>
                            Semua akun penghuni sudah punya data. Buat akun baru di <a href="{{ route('akun.index') }}" wire:navigate class="font-semibold">Akun</a>.
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <flux:field>
                        <flux:label>Akun</flux:label>
                        <flux:select wire:model.live="user_id" placeholder="Pilih akun penghuni" data-test="pilih-akun">
                            @foreach ($this->akunTersedia as $akun)
                                <flux:select.option value="{{ $akun->id }}" wire:key="akun-{{ $akun->id }}">{{ $akun->nama }}, {{ $akun->email }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>
                            Hanya akun yang belum punya data.
                        </flux:description>
                        <flux:error name="user_id" />
                    </flux:field>
                @endif
            @else
                <flux:field>
                    <flux:label>Akun</flux:label>
                    <p class="m-0 min-h-11 content-center" data-test="akun-terkunci">{{ $penghuni->user?->email }}</p>
                    <flux:description>Akun tidak bisa diganti.</flux:description>
                </flux:field>
            @endif

            <flux:field>
                <flux:label>Nama Lengkap</flux:label>
                <flux:input wire:model="nama" maxlength="100" autocomplete="off" data-test="input-nama" />
                <flux:error name="nama" />
            </flux:field>

            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nomor Identitas</flux:label>
                    <flux:input wire:model="nik" inputmode="numeric" autocomplete="off" data-test="input-nik" />
                    @if ($penghuni)
                        <flux:description class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="num tracking-[0.02em]" aria-live="polite" data-test="nik-tersimpan">Tersimpan: {{ $this->identitasTerbuka ? $this->identitasUtuh : $penghuni->no_identitas_tersamar }}.</span>
                            @if ($this->identitasTerbuka)
                                <button type="button" aria-pressed="true" wire:click="sembunyikanIdentitas" class="tautan-aksi -my-2.5 py-2.5 teks-tombol-kecil" data-test="sembunyikan-identitas">Sembunyikan</button>
                            @else
                                <button type="button" aria-pressed="false" wire:click="tampilkanIdentitas" class="tautan-aksi -my-2.5 py-2.5 teks-tombol-kecil" data-test="tampilkan-identitas">Tampilkan</button>
                            @endif
                            <span>Kosongkan bila tidak berubah.</span>
                        </flux:description>
                    @endif
                    <flux:error name="nik" />
                </flux:field>

                <flux:field>
                    <flux:label>Nomor Telepon</flux:label>
                    <flux:input wire:model="no_telepon" inputmode="tel" autocomplete="off" data-test="input-telepon" />
                    <flux:error name="no_telepon" />
                </flux:field>
            </div>

            <div class="flex flex-col gap-2 border-t border-divider pt-[18px] sm:flex-row">
                <flux:button type="submit" variant="primary" class="tombol-utama max-sm:w-full" wire:loading.attr="disabled" wire:target="simpan" :disabled="$tanpaAkun" data-test="simpan-penghuni">
                    Simpan
                </flux:button>
                <x-tautan-batal :href="route('penghuni.index')" />
            </div>
        </form>

        <div class="flex flex-[1_1_260px] flex-col gap-4">
            @if ($penghuni)
                @if ($penghuni->status_penghuni === StatusPenghuni::Aktif)
                    <section class="flex flex-col gap-2.5 rounded-2xl border border-border bg-surface p-5" aria-labelledby="judul-status" data-test="kartu-status">
                        <h2 id="judul-status" class="m-0 teks-bagian">Penghuni Keluar</h2>

                        @if ($this->izinNonaktifkan->denied())
                            <p class="m-0 teks-bantuan" data-test="alasan-tidak-bisa-keluar">{{ $this->izinNonaktifkan->message() }}</p>
                            <button type="button" disabled class="h-10 w-fit cursor-not-allowed rounded-lg border border-border bg-background px-3.5 text-[#8A918F] teks-tombol-kecil">Tandai Keluar</button>
                        @else
                            <flux:modal.trigger name="tandai-keluar">
                                <button type="button" class="tombol-bahaya h-10 w-fit rounded-lg px-3.5 teks-tombol-kecil" data-test="buka-tandai-keluar">Tandai Keluar</button>
                            </flux:modal.trigger>
                        @endif
                    </section>
                @else
                    <section class="flex flex-col gap-2.5 rounded-2xl border border-border bg-surface p-5" aria-labelledby="judul-status" data-test="kartu-status">
                        <h2 id="judul-status" class="m-0 teks-bagian">Sudah Keluar</h2>
                        <button type="button" wire:click="aktifkanKembali" wire:loading.attr="disabled" wire:target="aktifkanKembali" class="tombol-sekunder h-10 w-fit rounded-lg px-3.5 teks-tombol-kecil" data-test="aktifkan-kembali">Aktifkan</button>
                    </section>
                @endif
            @endif
        </div>
    </div>

    @if ($penghuni)
        <x-dialog-konfirmasi
            name="tandai-keluar"
            :judul="'Tandai '.$namaTampil.' Keluar?'"
            label-aksi="Tandai Keluar"
            wire:click="nonaktifkanPenghuni"
            data-test="konfirmasi-tandai-keluar"
        >
            <x-slot:ringkasan>
                <dt>Email</dt>
                <dd>{{ $penghuni->user?->email }}</dd>
                <dt>Telepon</dt>
                <dd class="num">{{ Format::telepon($penghuni->no_telepon) }}</dd>
            </x-slot:ringkasan>

            <x-slot:akibat>Penghuni ini akan ditandai sudah keluar tanpa menghapus riwayat dan akun loginnya. Anda bisa mengaktifkannya lagi.</x-slot:akibat>
        </x-dialog-konfirmasi>
    @endif
</section>
