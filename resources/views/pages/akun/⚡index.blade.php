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
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Akun Pengguna')] class extends Component {
    use WithPagination;

    #[Url(as: 'peran')]
    public string $filterPeran = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public string $nama = '';
    public string $email = '';
    public string $peran = '';
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
        if ($this->peran === PeranPengguna::Vendor->value && $this->nama_vendor === '') {
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
        $this->email = Str::lower(trim($this->email));

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

        $this->reset('nama', 'email', 'peran', 'password', 'password_confirmation', 'nama_vendor', 'jenis_layanan', 'no_telepon');
        $this->resetValidation();
        $this->resetPage();

        Flux::modal('tambah-akun')->close();
        Flux::toast(variant: 'success', text: 'Akun berhasil dibuat.');
    }

    public function nonaktifkan(int $id): void
    {
        // ID dari browser tidak dipercaya: cari akunnya, lalu authorize sebelum mengubah.
        $akun = User::query()->findOrFail($id);

        $this->authorize('nonaktifkan', $akun);

        $akun->nonaktifkan();

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
    <x-judul-halaman
        judul="Akun pengguna"
        deskripsi="Akun penghuni dan vendor. Akun yang dinonaktifkan tidak bisa masuk, datanya tetap tersimpan."
    >
        <flux:modal.trigger name="tambah-akun">
            <flux:button variant="primary" class="h-11! w-full sm:w-auto" data-test="tambah-akun-button">
                Tambah akun
            </flux:button>
        </flux:modal.trigger>
    </x-judul-halaman>

    <div class="flex flex-col gap-3">
        <div role="group" aria-label="Saring peran" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
            <x-chip-filter :aktif="$filterPeran === ''" wire:click="$set('filterPeran', '')">Semua peran</x-chip-filter>
            <x-chip-filter :aktif="$filterPeran === 'penghuni'" :jumlah="$this->hitungan['penghuni']" wire:click="$set('filterPeran', 'penghuni')">Penghuni</x-chip-filter>
            <x-chip-filter :aktif="$filterPeran === 'vendor'" :jumlah="$this->hitungan['vendor']" wire:click="$set('filterPeran', 'vendor')">Vendor</x-chip-filter>
        </div>

        <div role="group" aria-label="Saring status" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-0.5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0">
            <x-chip-filter :aktif="$filterStatus === ''" wire:click="$set('filterStatus', '')">Semua status</x-chip-filter>
            <x-chip-filter :aktif="$filterStatus === 'aktif'" :jumlah="$this->hitungan['aktif']" wire:click="$set('filterStatus', 'aktif')">Aktif</x-chip-filter>
            <x-chip-filter :aktif="$filterStatus === 'nonaktif'" :jumlah="$this->hitungan['nonaktif']" wire:click="$set('filterStatus', 'nonaktif')">Nonaktif</x-chip-filter>
        </div>
    </div>

    @if ($this->daftarAkun->isEmpty())
        <p class="m-0 rounded-2xl border border-border bg-surface py-10 text-center text-ink-muted" data-test="pesan-kosong">
            {{ $this->adaFilter ? 'Tidak ada akun yang sesuai filter.' : 'Belum ada akun.' }}
        </p>
    @else
        {{-- Desktop: tabel dalam kartu --}}
        <div class="hidden overflow-x-auto rounded-2xl border border-border bg-surface md:block">
            <table class="w-full min-w-[720px] border-collapse">
                <thead>
                    <tr>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Nama</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Email</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Peran</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-start text-[13px] font-[550] whitespace-nowrap text-ink-muted">Status</th>
                        <th scope="col" class="border-b border-border px-5 py-3.5 text-end text-[13px] font-[550] whitespace-nowrap text-ink-muted"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarAkun as $akun)
                        <tr wire:key="akun-tabel-{{ $akun->id }}" class="h-[60px] border-t border-row-divider first:border-t-0">
                            <td class="px-5 font-semibold">{{ $akun->nama }}</td>
                            <td class="px-5 break-all text-ink-muted">{{ $akun->email }}</td>
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
                                    <button
                                        type="button"
                                        class="min-h-10 cursor-pointer px-2 text-sm font-semibold text-danger"
                                        wire:click="nonaktifkan({{ $akun->id }})"
                                        wire:confirm="Nonaktifkan akun {{ $akun->nama }}? Pengguna ini tidak bisa masuk sampai diaktifkan kembali."
                                    >
                                        Nonaktifkan
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        class="min-h-10 cursor-pointer px-2 text-sm font-semibold text-primary"
                                        wire:click="aktifkan({{ $akun->id }})"
                                    >
                                        Aktifkan
                                    </button>
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
                        <span class="min-w-0 font-[650] break-words">{{ $akun->nama }}</span>
                        <span class="shrink-0 text-sm">{{ $akun->peran->label() }}</span>
                    </div>
                    <span class="text-sm break-all text-ink-muted">{{ $akun->email }}</span>
                    <div class="flex items-center justify-between gap-3">
                        @if ($akun->isAktif())
                            <x-status jenis="ok">{{ $akun->status_akun->label() }}</x-status>
                            <button
                                type="button"
                                class="min-h-10 cursor-pointer px-1 text-sm font-semibold text-danger"
                                wire:click="nonaktifkan({{ $akun->id }})"
                                wire:confirm="Nonaktifkan akun {{ $akun->nama }}? Pengguna ini tidak bisa masuk sampai diaktifkan kembali."
                            >
                                Nonaktifkan
                            </button>
                        @else
                            <x-status jenis="belum">{{ $akun->status_akun->label() }}</x-status>
                            <button
                                type="button"
                                class="min-h-10 cursor-pointer px-1 text-sm font-semibold text-primary"
                                wire:click="aktifkan({{ $akun->id }})"
                            >
                                Aktifkan
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <flux:pagination :paginator="$this->daftarAkun" />
    @endif

    <flux:modal name="tambah-akun" class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">Tambah akun</flux:heading>
                <flux:subheading>Akun baru langsung aktif dan dapat dipakai masuk.</flux:subheading>
            </div>

            <flux:input wire:model.blur="nama" label="Nama" required maxlength="100" autocomplete="off" />

            <flux:input wire:model="email" label="Email" type="email" required autocomplete="off" />

            <flux:select wire:model.live="peran" label="Peran" placeholder="Pilih peran">
                <flux:select.option value="penghuni">Penghuni</flux:select.option>
                <flux:select.option value="vendor">Vendor</flux:select.option>
            </flux:select>

            @if ($peran === 'vendor')
                <flux:input wire:model="nama_vendor" label="Nama vendor" required maxlength="120" />
                <flux:input wire:model="jenis_layanan" label="Jenis layanan" required maxlength="80" />
                <flux:input wire:model="no_telepon" label="Nomor telepon (opsional)" maxlength="20" />
            @endif

            <flux:input wire:model="password" label="Password awal" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" label="Konfirmasi password" type="password" required autocomplete="new-password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="simpan" data-test="simpan-akun-button">
                    Simpan
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
