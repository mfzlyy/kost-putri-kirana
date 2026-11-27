<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Models\User;
use App\Models\Vendor;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Akun')] class extends Component {
    use WithPagination;

    #[Url(as: 'peran')]
    public string $filterPeran = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    /** Akun yang sedang dikonfirmasi untuk dinonaktifkan (dialog). Hanya diisi lewat konfirmasiNonaktifkan. */
    #[Locked]
    public ?int $idNonaktif = null;

    public string $nama = '';
    public string $email = '';
    public string $peran = 'penghuni';

    /** Nama vendor terisi dari Nama selama pengguna belum mengubahnya sendiri. */
    public bool $namaVendorDiubah = false;
    public string $password = '';
    public string $password_confirmation = '';
    public string $nama_vendor = '';
    public string $jenis_layanan = '';
    public string $no_telepon = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedFilterPeran(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Nama vendor awalnya mengikuti nama akun.
     */
    public function updatedPeran(): void
    {
        $this->isiNamaVendorDariNama();
    }

    public function updatedNama(): void
    {
        $this->isiNamaVendorDariNama();
    }

    public function updatedNamaVendor(string $nilai): void
    {
        $this->namaVendorDiubah = trim($nilai) !== '';
    }

    private function isiNamaVendorDariNama(): void
    {
        if ($this->peran === PeranPengguna::Vendor->value && ! $this->namaVendorDiubah) {
            $this->nama_vendor = $this->nama;
        }
    }

    /**
     * Hanya akun penghuni dan vendor yang dikelola di sini; pemilik dan pengelola lewat seeder.
     *
     * @return list<string>
     */
    private function peranDikelola(): array
    {
        return [PeranPengguna::Penghuni->value, PeranPengguna::Vendor->value];
    }

    #[Computed]
    public function daftarAkun()
    {
        return User::query()
            ->whereIn('peran', $this->peranDikelola())
            ->when(
                in_array($this->filterPeran, $this->peranDikelola(), true),
                fn ($query) => $query->where('peran', $this->filterPeran),
            )
            ->when(
                StatusAkun::tryFrom($this->filterStatus) !== null,
                fn ($query) => $query->where('status_akun', $this->filterStatus),
            )
            ->orderBy('nama')
            ->orderBy('id')
            ->paginate(15);
    }

    /**
     * Jumlah akun per peran dan per status untuk angka di chip filter (tidak terpengaruh filter).
     *
     * @return array{penghuni: int, vendor: int, aktif: int, nonaktif: int}
     */
    #[Computed]
    public function hitungan(): array
    {
        $baris = User::query()
            ->whereIn('peran', $this->peranDikelola())
            ->toBase()
            ->selectRaw('peran, status_akun, count(*) as total')
            ->groupBy('peran', 'status_akun')
            ->get();

        $jumlah = fn (string $kolom, string $nilai): int => (int) $baris->where($kolom, $nilai)->sum('total');

        return [
            'penghuni' => $jumlah('peran', PeranPengguna::Penghuni->value),
            'vendor' => $jumlah('peran', PeranPengguna::Vendor->value),
            'aktif' => $jumlah('status_akun', StatusAkun::Aktif->value),
            'nonaktif' => $jumlah('status_akun', StatusAkun::Nonaktif->value),
        ];
    }

    #[Computed]
    public function adaFilter(): bool
    {
        return $this->filterPeran !== '' || $this->filterStatus !== '';
    }

    public function simpan(): void
    {
        $this->authorize('create', User::class);

        // Email disimpan huruf kecil; cek unik dilakukan setelah diubah.
        $emailBersih = Str::lower(trim($this->email));

        if ($emailBersih !== $this->email) {
            $this->email = $emailBersih;
        }

        $aturan = [
            'nama' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                fn (string $atribut, mixed $nilai, Closure $gagal) => User::query()
                    ->whereRaw('lower(email) = ?', [Str::lower((string) $nilai)])
                    ->exists() ? $gagal(trans('validation.unique', ['attribute' => 'email'])) : null,
            ],
            'peran' => ['required', Rule::in($this->peranDikelola())],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];

        $adalahVendor = $this->peran === PeranPengguna::Vendor->value;

        if ($adalahVendor) {
            $aturan += [
                'nama_vendor' => ['required', 'string', 'max:120'],
                'jenis_layanan' => ['required', 'string', 'max:80'],
                'no_telepon' => ['nullable', 'string', 'max:20'],
            ];
        }

        $data = $this->validate($aturan);

        DB::transaction(function () use ($data, $adalahVendor) {
            $user = User::query()->create([
                'nama' => $data['nama'],
                'email' => $data['email'],
                'password_hash' => $data['password'],
                'peran' => $data['peran'],
                'status_akun' => StatusAkun::Aktif,
            ]);

            if ($adalahVendor) {
                Vendor::query()->create([
                    'user_id' => $user->id,
                    'nama_vendor' => $data['nama_vendor'],
                    'jenis_layanan' => $data['jenis_layanan'],
                    'no_telepon' => $data['no_telepon'] ?: null,
                ]);
            }
        });

        $this->reset('nama', 'email', 'peran', 'password', 'password_confirmation', 'nama_vendor', 'jenis_layanan', 'no_telepon', 'namaVendorDiubah');
        $this->resetValidation();
        $this->resetPage();

        Flux::modal('tambah-akun')->close();
        Flux::toast(variant: 'success', text: 'Akun berhasil dibuat.');
    }

    /**
     * Akun untuk dialog Nonaktifkan, dimuat ulang dari ID tersimpan.
     */
    #[Computed]
    public function akunNonaktif(): ?User
    {
        return $this->idNonaktif === null ? null : User::query()->find($this->idNonaktif);
    }

    public function konfirmasiNonaktifkan(int $id): void
    {
        $akun = User::query()->findOrFail($id);

        $this->authorize('nonaktifkan', $akun);

        $this->idNonaktif = $akun->id;
        unset($this->akunNonaktif);

        Flux::modal('nonaktifkan-akun')->show();
    }

    public function nonaktifkan(int $id): void
    {
        // ID dari browser tidak dipercaya: cari akunnya, lalu authorize sebelum mengubah.
        $akun = User::query()->findOrFail($id);

        $this->authorize('nonaktifkan', $akun);

        $akun->nonaktifkan();

        $this->reset('idNonaktif');
        unset($this->akunNonaktif);

        Flux::modal('nonaktifkan-akun')->close();
        Flux::toast(variant: 'success', text: 'Akun dinonaktifkan.');
    }

    public function aktifkan(int $id): void
    {
        $akun = User::query()->findOrFail($id);

        $this->authorize('aktifkan', $akun);

        $akun->aktifkan();

        Flux::toast(variant: 'success', text: 'Akun diaktifkan.');
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <x-judul-halaman judul="Akun">
        <flux:modal.trigger name="tambah-akun">
            <x-tombol data-test="tambah-akun-button">
                Tambah
            </x-tombol>
        </flux:modal.trigger>
    </x-judul-halaman>

    <div class="baris-filter flex flex-col gap-3 lg:-m-1 lg:flex-row lg:flex-wrap lg:gap-x-4 lg:gap-y-3 lg:overflow-hidden lg:p-1">
        <x-chip-filter.baris label="Saring peran">
            <x-chip-filter :aktif="$filterPeran === ''" :jumlah="$this->hitungan['penghuni'] + $this->hitungan['vendor']" wire:click="$set('filterPeran', '')">Semua</x-chip-filter>
            <x-chip-filter :aktif="$filterPeran === 'penghuni'" :jumlah="$this->hitungan['penghuni']" wire:click="$set('filterPeran', 'penghuni')">Penghuni</x-chip-filter>
            <x-chip-filter :aktif="$filterPeran === 'vendor'" :jumlah="$this->hitungan['vendor']" wire:click="$set('filterPeran', 'vendor')">Vendor</x-chip-filter>
        </x-chip-filter.baris>

        <x-chip-filter.baris label="Saring status" class="lg:relative lg:before:absolute lg:before:-start-2 lg:before:top-1.5 lg:before:h-6 lg:before:w-px lg:before:bg-border lg:before:content-['']">
            <x-chip-filter :aktif="$filterStatus === ''" :jumlah="$this->hitungan['aktif'] + $this->hitungan['nonaktif']" wire:click="$set('filterStatus', '')">Semua</x-chip-filter>
            <x-chip-filter :aktif="$filterStatus === 'aktif'" :jumlah="$this->hitungan['aktif']" wire:click="$set('filterStatus', 'aktif')">Aktif</x-chip-filter>
            <x-chip-filter :aktif="$filterStatus === 'nonaktif'" :jumlah="$this->hitungan['nonaktif']" wire:click="$set('filterStatus', 'nonaktif')">Nonaktif</x-chip-filter>
        </x-chip-filter.baris>
    </div>

    <div wire:loading.class="opacity-60" class="transisi-opasitas flex flex-col gap-4">
    @if ($this->daftarAkun->isEmpty())
        <p class="m-0 rounded-2xl border border-border bg-surface py-10 text-center text-ink-muted" data-test="pesan-kosong">
            {{ $this->adaFilter ? 'Tidak ada akun yang sesuai filter.' : 'Belum ada akun.' }}
        </p>
    @else
        {{-- Desktop: tabel dalam kartu --}}
        <div class="relative hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
            <table class="w-full min-w-[720px] border-collapse">
                <thead>
                    <tr>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Nama</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Email</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Peran</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start whitespace-nowrap teks-kolom">Status</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-end whitespace-nowrap teks-kolom"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarAkun as $akun)
                        <tr wire:key="akun-tabel-{{ $akun->id }}" class="h-[60px] border-t border-row-divider first:border-t-0">
                            <td class="px-5 teks-badan-kuat">{{ $akun->nama }}</td>
                            <td class="px-5 text-ink-muted"><span class="block min-w-40 [overflow-wrap:anywhere] lg:min-w-56">{{ $akun->email }}</span></td>
                            <td class="px-5">{{ $akun->peran->label() }}</td>
                            <td class="px-5">
                                @if ($akun->isAktif())
                                    <x-status jenis="ok">{{ $akun->status_akun->label() }}</x-status>
                                @else
                                    <x-status jenis="belum">{{ $akun->status_akun->label() }}</x-status>
                                @endif
                            </td>
                            <td class="px-5 text-end whitespace-nowrap">
                                @if ($akun->isAktif())
                                    <x-tombol varian="bahaya" ukuran="kecil" class="min-w-30" wire:click="konfirmasiNonaktifkan({{ $akun->id }})">Nonaktifkan</x-tombol>
                                @else
                                    <x-tombol varian="positif" ukuran="kecil" class="min-w-30" wire:click="aktifkan({{ $akun->id }})">Aktifkan</x-tombol>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Ponsel: daftar kartu --}}
        <ul class="m-0 list-none overflow-hidden rounded-2xl border border-border bg-surface p-0 md:hidden">
            @foreach ($this->daftarAkun as $akun)
                <li wire:key="akun-kartu-{{ $akun->id }}" class="flex flex-col gap-1 border-t border-row-divider px-4 py-3.5 first:border-t-0">
                    <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0 break-words teks-badan-kuat">{{ $akun->nama }}</span>
                        <span class="shrink-0 teks-badan">{{ $akun->peran->label() }}</span>
                    </div>
                    <span class="break-all teks-bantuan">{{ $akun->email }}</span>
                    @if ($akun->isAktif())
                        <x-status jenis="ok">{{ $akun->status_akun->label() }}</x-status>
                        <x-tombol varian="bahaya" ukuran="kecil" class="mt-1.5 w-full" wire:click="konfirmasiNonaktifkan({{ $akun->id }})">Nonaktifkan</x-tombol>
                    @else
                        <x-status jenis="belum">{{ $akun->status_akun->label() }}</x-status>
                        <x-tombol varian="positif" ukuran="kecil" class="mt-1.5 w-full" wire:click="aktifkan({{ $akun->id }})">Aktifkan</x-tombol>
                    @endif
                </li>
            @endforeach
        </ul>

        <flux:pagination :paginator="$this->daftarAkun" />
    @endif
    </div>

    @php($akunNonaktif = $this->akunNonaktif)
    <x-dialog-konfirmasi
        name="nonaktifkan-akun"
        :judul="'Nonaktifkan Akun '.$akunNonaktif?->nama.'?'"
        label-aksi="Nonaktifkan"
        wire:click="nonaktifkan({{ (int) $idNonaktif }})"
        data-test="konfirmasi-nonaktifkan-akun"
    >
        <x-slot:ringkasan>
            <dt>Email</dt>
            <dd>{{ $akunNonaktif?->email }}</dd>
            <dt>Peran</dt>
            <dd>{{ $akunNonaktif?->peran->label() }}</dd>
        </x-slot:ringkasan>

        <x-slot:akibat>Pemilik akun ini langsung keluar dan tidak bisa masuk. Anda bisa mengaktifkannya lagi.</x-slot:akibat>
    </x-dialog-konfirmasi>

    <flux:modal
        name="tambah-akun"
        variant="bare"
        :closable="false"
        class="m-auto w-full max-w-[560px] max-h-[calc(100dvh-48px)] flex-col overflow-hidden rounded-2xl bg-surface! p-0 text-ink shadow-[0_24px_60px_rgba(12,53,51,.28)] open:flex max-sm:m-0 max-sm:h-dvh max-sm:max-h-none max-sm:max-w-none max-sm:rounded-none"
    >
        <form wire:submit="simpan" class="flex min-h-0 flex-1 flex-col" aria-labelledby="judul-tambah-akun">
            {{-- Header desktop --}}
            <div class="flex shrink-0 items-start justify-between gap-4 px-7 pt-7 pb-4 max-sm:hidden">
                <div>
                    <h2 id="judul-tambah-akun" class="teks-judul-dialog m-0">Tambah Akun</h2>
                </div>
                <flux:modal.close>
                    <button type="button" aria-label="Tutup" class="tombol-ikon -me-2 -mt-2 flex size-10 shrink-0 items-center justify-center text-ink-muted">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </flux:modal.close>
            </div>

            {{-- Header ponsel --}}
            <div class="flex h-14 shrink-0 items-center gap-1 border-b border-divider bg-surface px-2 sm:hidden">
                <flux:modal.close>
                    <button type="button" aria-label="Tutup" class="tombol-ikon flex size-11 items-center justify-center text-ink">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </flux:modal.close>
                <span class="teks-judul-dialog">Tambah Akun</span>
            </div>

            {{-- Isi: hanya bagian ini yang bergulir --}}
            <div class="flex min-h-0 flex-1 flex-col gap-[18px] overflow-y-auto px-7 py-2 max-sm:px-4 max-sm:py-5">
                <fieldset class="m-0 border-0 p-0">
                    <legend class="mb-1.5 p-0 teks-label">Peran</legend>
                    <div class="grid grid-cols-2 overflow-hidden rounded-lg border border-border-strong">
                        @foreach (['penghuni' => 'Penghuni', 'vendor' => 'Vendor'] as $nilai => $label)
                            <div @class(['relative', 'border-s border-border-strong' => ! $loop->first])>
                                <input
                                    type="radio"
                                    id="peran-{{ $nilai }}"
                                    name="peran"
                                    value="{{ $nilai }}"
                                    wire:model.live="peran"
                                    @checked($peran === $nilai)
                                    class="peer sr-only"
                                />
                                <label
                                    for="peran-{{ $nilai }}"
                                    class="pilihan flex h-11 cursor-pointer items-center justify-center peer-checked:bg-chip-active peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-primary teks-chip"
                                >{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('peran')
                        <p class="mt-1.5 mb-0 teks-galat" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Nama</flux:label>
                        <flux:input wire:model.blur="nama" required maxlength="100" autocomplete="off" autofocus />
                        <flux:error name="nama" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model.blur="email" type="email" required autocomplete="off" />
                        <flux:error name="email" />
                    </flux:field>
                </div>

                @if ($peran === 'vendor')
                    <div class="flex flex-col gap-4 rounded-xl bg-background p-4" data-test="panel-vendor">
                        <p class="m-0 teks-badan-kuat">Data Vendor</p>

                        <flux:field>
                            <flux:label>Nama Vendor</flux:label>
                            <flux:input wire:model.blur="nama_vendor" required maxlength="120" />
                            <flux:description>Awalnya diisi dari nama akun.</flux:description>
                            <flux:error name="nama_vendor" />
                        </flux:field>

                        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Jenis Layanan</flux:label>
                                <flux:input wire:model="jenis_layanan" required maxlength="80" />
                                <flux:error name="jenis_layanan" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Telepon</flux:label>
                                <flux:input wire:model="no_telepon" maxlength="20" inputmode="tel" />
                                <flux:error name="no_telepon" />
                            </flux:field>
                        </div>
                    </div>
                @endif

                <div class="flex flex-col gap-1.5">
                    <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Password Awal</flux:label>
                            <flux:input wire:model="password" type="password" required autocomplete="new-password" viewable />
                            <flux:error name="password" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Konfirmasi Password</flux:label>
                            <flux:input wire:model="password_confirmation" type="password" required autocomplete="new-password" viewable />
                            <flux:error name="password_confirmation" />
                        </flux:field>
                    </div>
                    <p class="m-0 teks-bantuan">Minimal 8 karakter, huruf dan angka.</p>
                </div>
            </div>

            {{-- Footer menempel --}}
            <div class="flex shrink-0 justify-end gap-2 border-t border-divider px-7 py-[18px] max-sm:px-4">
                <flux:modal.close>
                    <x-tombol varian="sekunder" class="max-sm:hidden">Batal</x-tombol>
                </flux:modal.close>

                <x-tombol type="submit" class="max-sm:w-full" wire:loading.attr="disabled" wire:target="simpan" data-test="simpan-akun-button">
                    Buat
                </x-tombol>
            </div>
        </form>
    </flux:modal>
</section>
