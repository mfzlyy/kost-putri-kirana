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

<section class="w-full">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">Akun Pengguna</flux:heading>
            <flux:subheading>Kelola akun penghuni dan vendor.</flux:subheading>
        </div>

        <flux:modal.trigger name="tambah-akun">
            <flux:button variant="primary" icon="plus" class="w-full sm:w-auto" data-test="tambah-akun-button">
                Tambah Akun
            </flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-3 sm:max-w-md sm:grid-cols-2">
        <flux:select wire:model.live="filterPeran" label="Peran">
            <flux:select.option value="">Semua</flux:select.option>
            <flux:select.option value="penghuni">Penghuni</flux:select.option>
            <flux:select.option value="vendor">Vendor</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterStatus" label="Status">
            <flux:select.option value="">Semua</flux:select.option>
            <flux:select.option value="aktif">Aktif</flux:select.option>
            <flux:select.option value="nonaktif">Nonaktif</flux:select.option>
        </flux:select>
    </div>

    <div class="mt-6 max-w-full overflow-x-auto">
        @if ($this->daftarAkun->isEmpty())
            <flux:text class="py-8 text-center" data-test="pesan-kosong">
                {{ $this->adaFilter ? 'Tidak ada akun yang sesuai filter.' : 'Belum ada akun.' }}
            </flux:text>
        @else
            <flux:table :paginate="$this->daftarAkun">
                <flux:table.columns>
                    <flux:table.column>Nama</flux:table.column>
                    <flux:table.column>Email</flux:table.column>
                    <flux:table.column>Peran</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->daftarAkun as $akun)
                        <flux:table.row :key="$akun->id">
                            <flux:table.cell class="whitespace-normal">{{ $akun->nama }}</flux:table.cell>
                            <flux:table.cell class="break-all whitespace-normal">{{ $akun->email }}</flux:table.cell>
                            <flux:table.cell>{{ $akun->peran->label() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$akun->isAktif() ? 'green' : 'zinc'" inset="top bottom">
                                    {{ $akun->status_akun->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($akun->isAktif())
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="nonaktifkan({{ $akun->id }})"
                                        wire:confirm="Nonaktifkan akun {{ $akun->nama }}? Pengguna ini tidak bisa masuk sampai diaktifkan kembali."
                                    >
                                        Nonaktifkan
                                    </flux:button>
                                @else
                                    <flux:button size="sm" wire:click="aktifkan({{ $akun->id }})">
                                        Aktifkan
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="tambah-akun" class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">Tambah Akun</flux:heading>
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
