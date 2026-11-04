<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Models\Penghuni;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function halamanAkunSebagai(User $user)
{
    return Livewire::actingAs($user)->test('pages::akun.index');
}

function isiFormAkun($komponen, array $nilai)
{
    foreach ($nilai as $kolom => $isi) {
        $komponen->set($kolom, $isi);
    }

    return $komponen;
}

function formPenghuniValid(array $timpa = []): array
{
    return array_merge([
        'nama' => 'Sari Wulandari',
        'email' => 'sari.wulandari@contoh.test',
        'peran' => 'penghuni',
        'password' => 'SandiAwal123',
        'password_confirmation' => 'SandiAwal123',
    ], $timpa);
}

function formVendorValid(array $timpa = []): array
{
    return formPenghuniValid(array_merge([
        'nama' => 'Budi Listrik',
        'email' => 'budi.listrik@contoh.test',
        'peran' => 'vendor',
        'nama_vendor' => 'CV Budi Teknik',
        'jenis_layanan' => 'Listrik dan air',
        'no_telepon' => '081234567890',
    ], $timpa));
}

it('F-02: pengelola membuat akun penghuni tanpa baris vendor atau profil penghuni', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formPenghuniValid())
        ->call('simpan')
        ->assertHasNoErrors();

    $akun = User::query()->where('email', 'sari.wulandari@contoh.test')->firstOrFail();

    expect($akun->peran)->toBe(PeranPengguna::Penghuni)
        ->and($akun->status_akun)->toBe(StatusAkun::Aktif)
        ->and($akun->nama)->toBe('Sari Wulandari')
        ->and(Hash::check('SandiAwal123', $akun->password_hash))->toBeTrue()
        ->and(Vendor::query()->count())->toBe(0)
        ->and(Penghuni::query()->count())->toBe(0);
});

it('F-02: pengelola membuat akun vendor dan baris vendor ikut terbuat', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formVendorValid())
        ->call('simpan')
        ->assertHasNoErrors();

    $akun = User::query()->where('email', 'budi.listrik@contoh.test')->firstOrFail();

    expect($akun->peran)->toBe(PeranPengguna::Vendor)
        ->and($akun->vendor)->not->toBeNull()
        ->and($akun->vendor->nama_vendor)->toBe('CV Budi Teknik')
        ->and($akun->vendor->jenis_layanan)->toBe('Listrik dan air')
        ->and($akun->vendor->no_telepon)->toBe('081234567890')
        ->and($akun->vendor->odoo_partner_id)->toBeNull();
});

it('F-02: nomor telepon vendor boleh kosong', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formVendorValid(['no_telepon' => '']))
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Vendor::query()->firstOrFail()->no_telepon)->toBeNull();
});

it('F-02: akun dan baris vendor dibuat dalam satu transaksi (gagal bersama)', function () {
    $pengelola = User::factory()->pengelola()->create();

    // Jenis layanan melebihi batas 80 karakter pada validasi -> tidak ada akun yang terbuat.
    isiFormAkun(halamanAkunSebagai($pengelola), formVendorValid(['jenis_layanan' => str_repeat('x', 81)]))
        ->call('simpan')
        ->assertHasErrors(['jenis_layanan']);

    expect(User::query()->where('email', 'budi.listrik@contoh.test')->exists())->toBeFalse()
        ->and(Vendor::query()->count())->toBe(0);
});

it('F-02: email disimpan dalam huruf kecil', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formPenghuniValid(['email' => '  Sari.Wulandari@Contoh.Test ']))
        ->call('simpan')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'sari.wulandari@contoh.test')->exists())->toBeTrue();
});

it('F-02: email ganda ditolak, termasuk bila hanya beda huruf besar-kecil', function (string $emailBaru) {
    $pengelola = User::factory()->pengelola()->create();
    User::factory()->penghuni()->create(['email' => 'ayu@contoh.test']);

    $komponen = isiFormAkun(halamanAkunSebagai($pengelola), formPenghuniValid(['email' => $emailBaru]))
        ->call('simpan')
        ->assertHasErrors(['email']);

    expect($komponen->errors()->get('email'))->toBe(['Email sudah terdaftar.'])
        ->and(User::query()->count())->toBe(2);
})->with(['sama persis' => 'ayu@contoh.test', 'beda huruf besar' => 'AYU@Contoh.Test']);

it('F-02: peran pemilik atau pengelola ditolak walaupun nilainya dimanipulasi', function (string $peranTerlarang) {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formPenghuniValid(['peran' => $peranTerlarang]))
        ->call('simpan')
        ->assertHasErrors(['peran']);

    expect(User::query()->where('email', 'sari.wulandari@contoh.test')->exists())->toBeFalse();
})->with(['pemilik', 'pengelola', 'admin']);

it('F-02: field wajib kosong menghasilkan galat per field', function () {
    $pengelola = User::factory()->pengelola()->create();

    halamanAkunSebagai($pengelola)
        ->call('simpan')
        ->assertHasErrors(['nama', 'email', 'peran', 'password']);

    isiFormAkun(halamanAkunSebagai($pengelola), ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'peran' => 'vendor',
        'password' => 'SandiAwal123', 'password_confirmation' => 'SandiAwal123', 'nama_vendor' => '', 'jenis_layanan' => ''])
        ->call('simpan')
        ->assertHasErrors(['nama_vendor', 'jenis_layanan'])
        ->assertHasNoErrors(['nama', 'email', 'peran', 'password']);
});

it('F-02: password awal wajib sesuai aturan dan konfirmasinya cocok', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formPenghuniValid(['password' => 'pendek1', 'password_confirmation' => 'pendek1']))
        ->call('simpan')
        ->assertHasErrors(['password']);

    isiFormAkun(halamanAkunSebagai($pengelola), formPenghuniValid(['password_confirmation' => 'BedaSandi456']))
        ->call('simpan')
        ->assertHasErrors(['password']);
});

it('F-02: nama dibatasi 100 karakter dan nama vendor 120 karakter', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formVendorValid(['nama' => str_repeat('n', 101), 'nama_vendor' => str_repeat('v', 121)]))
        ->call('simpan')
        ->assertHasErrors(['nama', 'nama_vendor']);
});

it('F-02: form dikosongkan setelah akun berhasil dibuat', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormAkun(halamanAkunSebagai($pengelola), formVendorValid())
        ->call('simpan')
        ->assertSet('nama', '')
        ->assertSet('email', '')
        ->assertSet('password', '')
        ->assertSet('nama_vendor', '');
});

it('F-02: nonaktifkan lalu aktifkan akun berhasil, dan nonaktifkan menghapus session akun itu', function () {
    $pengelola = User::factory()->pengelola()->create();
    $penghuni = User::factory()->penghuni()->create();
    $lain = User::factory()->penghuni()->create();

    foreach ([[$penghuni, 'sesi-penghuni'], [$lain, 'sesi-lain']] as [$pemilikSesi, $id]) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $pemilikSesi->id, 'payload' => 'x', 'last_activity' => time(),
        ]);
    }

    $komponen = halamanAkunSebagai($pengelola)->call('nonaktifkan', $penghuni->id)->assertHasNoErrors();

    expect($penghuni->fresh()->status_akun)->toBe(StatusAkun::Nonaktif)
        ->and(DB::table('sessions')->where('id', 'sesi-penghuni')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sesi-lain')->exists())->toBeTrue()
        ->and($lain->fresh()->status_akun)->toBe(StatusAkun::Aktif);

    $komponen->call('aktifkan', $penghuni->id)->assertHasNoErrors();

    expect($penghuni->fresh()->status_akun)->toBe(StatusAkun::Aktif);
});

it('F-02: pengelola tidak bisa menonaktifkan diri sendiri, pemilik, atau pengelola lain', function () {
    $pengelola = User::factory()->pengelola()->create();
    $target = [
        'diri sendiri' => $pengelola,
        'pemilik' => User::factory()->pemilik()->create(),
        'pengelola lain' => User::factory()->pengelola()->create(),
    ];

    foreach ($target as $nama => $akun) {
        halamanAkunSebagai($pengelola)->call('nonaktifkan', $akun->id)->assertForbidden();
        halamanAkunSebagai($pengelola)->call('aktifkan', $akun->id)->assertForbidden();

        expect($akun->fresh()->status_akun)->toBe(StatusAkun::Aktif, "akun {$nama} harus tetap aktif");
    }
});

it('F-02: ID akun yang tidak ada menghasilkan 404 dan tidak mengubah apa pun', function () {
    $pengelola = User::factory()->pengelola()->create();
    $jumlahAktif = User::query()->where('status_akun', 'aktif')->count();

    halamanAkunSebagai($pengelola)->call('nonaktifkan', 999999)->assertNotFound();
    halamanAkunSebagai($pengelola)->call('aktifkan', 999999)->assertNotFound();

    expect(User::query()->where('status_akun', 'aktif')->count())->toBe($jumlahAktif);
});

it('F-02: daftar hanya memuat akun penghuni dan vendor', function () {
    $pengelola = User::factory()->pengelola()->create(['nama' => 'Pengelola Rahasia']);
    User::factory()->pemilik()->create(['nama' => 'Pemilik Rahasia']);
    User::factory()->penghuni()->create(['nama' => 'Sari Penghuni']);
    User::factory()->vendor()->create(['nama' => 'Budi Vendor']);

    halamanAkunSebagai($pengelola)
        ->assertSee('Sari Penghuni')
        ->assertSee('Budi Vendor')
        ->assertDontSee('Pengelola Rahasia')
        ->assertDontSee('Pemilik Rahasia');
});

it('F-02: filter peran dan status menampilkan baris yang benar', function () {
    $pengelola = User::factory()->pengelola()->create();
    User::factory()->penghuni()->create(['nama' => 'Sari Penghuni Aktif']);
    User::factory()->penghuni()->nonaktif()->create(['nama' => 'Tini Penghuni Nonaktif']);
    User::factory()->vendor()->create(['nama' => 'Budi Vendor Aktif']);
    User::factory()->vendor()->nonaktif()->create(['nama' => 'Edo Vendor Nonaktif']);

    halamanAkunSebagai($pengelola)
        ->set('filterPeran', 'vendor')
        ->assertSee('Budi Vendor Aktif')->assertSee('Edo Vendor Nonaktif')
        ->assertDontSee('Sari Penghuni Aktif')->assertDontSee('Tini Penghuni Nonaktif')
        ->set('filterPeran', '')
        ->set('filterStatus', 'nonaktif')
        ->assertSee('Tini Penghuni Nonaktif')->assertSee('Edo Vendor Nonaktif')
        ->assertDontSee('Sari Penghuni Aktif')->assertDontSee('Budi Vendor Aktif')
        ->set('filterPeran', 'penghuni')
        ->assertSee('Tini Penghuni Nonaktif')
        ->assertDontSee('Edo Vendor Nonaktif')->assertDontSee('Sari Penghuni Aktif');
});

it('F-02: nilai filter tersimpan di query string', function () {
    $pengelola = User::factory()->pengelola()->create();

    Livewire::withQueryParams(['peran' => 'vendor', 'status' => 'aktif'])
        ->actingAs($pengelola)
        ->test('pages::akun.index')
        ->assertSet('filterPeran', 'vendor')
        ->assertSet('filterStatus', 'aktif');
});

it('F-02: daftar diurutkan menurut nama dan dipaginasi 15 baris', function () {
    $pengelola = User::factory()->pengelola()->create();

    foreach (range(1, 16) as $nomor) {
        User::factory()->penghuni()->create(['nama' => sprintf('Akun %02d', $nomor)]);
    }

    halamanAkunSebagai($pengelola)
        ->assertSeeInOrder(['Akun 01', 'Akun 02', 'Akun 15'])
        ->assertDontSee('Akun 16');
});

it('F-02: pesan kosong tampil sesuai kondisinya', function () {
    $pengelola = User::factory()->pengelola()->create();

    halamanAkunSebagai($pengelola)
        ->assertSee('Belum ada akun.')
        ->assertDontSee('Tidak ada akun yang sesuai filter.');

    User::factory()->penghuni()->create();

    halamanAkunSebagai($pengelola)
        ->set('filterPeran', 'vendor')
        ->assertSee('Tidak ada akun yang sesuai filter.')
        ->assertDontSee('Belum ada akun.');
});

it('F-02: tidak ada tombol hapus akun', function () {
    $pengelola = User::factory()->pengelola()->create();
    User::factory()->penghuni()->create();

    halamanAkunSebagai($pengelola)->assertDontSee('Hapus');
});

it('TB-04: pemilik, penghuni, dan vendor mendapat 403 saat membuka /akun', function (string $peran) {
    $this->actingAs(User::factory()->{$peran}()->create())->get('/akun')->assertForbidden();
})->with(['pemilik', 'penghuni', 'vendor']);

it('TB-04: pemilik, penghuni, dan vendor tidak bisa membuka komponen Akun pengguna', function (string $peran) {
    Livewire::actingAs(User::factory()->{$peran}()->create())
        ->test('pages::akun.index')
        ->assertForbidden();
})->with(['pemilik', 'penghuni', 'vendor']);

it('TB-04: pemilik, penghuni, dan vendor mendapat 403 saat memanggil aksi Livewire secara langsung', function (string $peran) {
    $pengelola = User::factory()->pengelola()->create();
    $korban = User::factory()->penghuni()->create();
    $penyusup = User::factory()->{$peran}()->create();

    // Komponen dimuat sebagai pengelola, lalu sesi berganti ke peran lain dan aksi dipanggil langsung.
    // Setiap panggilan memakai komponen baru karena respons 403 mengakhiri snapshot sebelumnya.
    $panggilan = [
        'simpan' => fn ($komponen) => isiFormAkun($komponen, formPenghuniValid())->call('simpan'),
        'nonaktifkan' => fn ($komponen) => $komponen->call('nonaktifkan', $korban->id),
        'aktifkan' => fn ($komponen) => $komponen->call('aktifkan', $korban->id),
    ];

    foreach ($panggilan as $aksi) {
        $komponen = halamanAkunSebagai($pengelola);
        $this->actingAs($penyusup);

        $aksi($komponen)->assertForbidden();
    }

    expect(User::query()->where('email', 'sari.wulandari@contoh.test')->exists())->toBeFalse()
        ->and($korban->fresh()->status_akun)->toBe(StatusAkun::Aktif);
})->with(['pemilik', 'penghuni', 'vendor']);

it('TB-04: pengelola yang dinonaktifkan tidak bisa menjalankan aksi Livewire', function () {
    $pengelola = User::factory()->pengelola()->create();
    $korban = User::factory()->penghuni()->create();
    $komponen = halamanAkunSebagai($pengelola);

    $pengelola->nonaktifkan();
    $this->actingAs($pengelola->fresh());

    $komponen->call('nonaktifkan', $korban->id)->assertForbidden();

    expect($korban->fresh()->status_akun)->toBe(StatusAkun::Aktif);
});
