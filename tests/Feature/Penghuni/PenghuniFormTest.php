<?php

use App\Enums\StatusAkun;
use App\Enums\StatusPenghuni;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

const NIK_UJI_FORM = '3216012345678901';

function formPenghuniSebagai(User $user, ?Penghuni $penghuni = null)
{
    return Livewire::actingAs($user)->test('pages::penghuni.form', $penghuni ? ['penghuni' => $penghuni] : []);
}

function isiFormPenghuni($komponen, int|string $idAkun, array $ganti = [])
{
    $isi = array_merge([
        'user_id' => (string) $idAkun,
        'nama' => 'Nadia Ramadhani',
        'nik' => NIK_UJI_FORM,
        'no_telepon' => '0812 0000 0000',
    ], $ganti);

    foreach ($isi as $kolom => $nilai) {
        $komponen->set($kolom, $nilai);
    }

    return $komponen;
}

it('F-06: pengelola menambah penghuni; nama akun ikut berubah dan status aktif', function () {
    $akun = User::factory()->penghuni()->create(['nama' => 'Nama Lama']);

    isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $akun->id)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('penghuni.index'));

    $penghuni = Penghuni::query()->where('user_id', $akun->id)->firstOrFail();

    expect($akun->fresh()->nama)->toBe('Nadia Ramadhani')
        ->and($penghuni->status_penghuni)->toBe(StatusPenghuni::Aktif)
        ->and(session('toast'))->toBe('Data Nadia Ramadhani disimpan.');
});

it('NFR-02: nilai mentah NIK di basis data terenkripsi dan kembali utuh lewat model', function () {
    $akun = User::factory()->penghuni()->create();

    isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $akun->id)->call('simpan');

    $mentah = DB::table('penghuni')->where('user_id', $akun->id)->value('no_identitas_enc');

    expect($mentah)->not->toBe(NIK_UJI_FORM)
        ->and($mentah)->not->toContain(NIK_UJI_FORM)
        ->and(Penghuni::query()->where('user_id', $akun->id)->first()->no_identitas_enc)->toBe(NIK_UJI_FORM);
});

it('F-06: telepon dinormalisasi sebelum disimpan', function () {
    $akun = User::factory()->penghuni()->create();

    isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $akun->id, ['no_telepon' => '+62 812-3456-7801'])
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Penghuni::query()->where('user_id', $akun->id)->value('no_telepon'))->toBe('081234567801');
});

it('F-06: memilih akun mengisi nama lengkap, kecuali pengguna sudah mengetik sendiri', function () {
    $akunA = User::factory()->penghuni()->create(['nama' => 'Akun Satu']);
    $akunB = User::factory()->penghuni()->create(['nama' => 'Akun Dua']);

    formPenghuniSebagai(User::factory()->pengelola()->create())
        ->set('user_id', (string) $akunA->id)
        ->assertSet('nama', 'Akun Satu')
        ->set('user_id', (string) $akunB->id)
        ->assertSet('nama', 'Akun Dua')
        ->set('nama', 'Ketikan Sendiri')
        ->set('user_id', (string) $akunA->id)
        ->assertSet('nama', 'Ketikan Sendiri');
});

it('F-06: validasi menampilkan pesan per field dan ringkasan jumlah galat', function (array $ganti, array $galat, string $ringkasan) {
    $akun = User::factory()->penghuni()->create();

    $komponen = isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $akun->id, $ganti)
        ->call('simpan')
        ->assertHasErrors(array_keys($galat))
        ->assertSee($ringkasan);

    foreach ($galat as $kolom => $pesan) {
        $komponen->assertHasErrors([$kolom => [$pesan]]);
    }

    expect(Penghuni::query()->count())->toBe(0);
})->with([
    'NIK kosong' => [['nik' => ''], ['nik' => 'Nomor identitas wajib diisi.'], 'Satu isian perlu diperbaiki sebelum disimpan.'],
    'NIK 8 angka dan telepon kosong' => [
        ['nik' => '32160123', 'no_telepon' => ''],
        ['nik' => 'NIK harus 16 angka. Baru 8 angka.', 'no_telepon' => 'Nomor telepon wajib diisi.'],
        'Dua isian perlu diperbaiki sebelum disimpan.',
    ],
    'NIK berhuruf' => [['nik' => '32160123abc45678'], ['nik' => 'NIK hanya berisi angka.'], 'Satu isian perlu diperbaiki sebelum disimpan.'],
    'telepon salah format' => [['no_telepon' => '12345'], ['no_telepon' => 'Isi nomor yang diawali 0 atau +62, 10 sampai 14 angka.'], 'Satu isian perlu diperbaiki sebelum disimpan.'],
    'nama kosong' => [['nama' => '   '], ['nama' => 'Nama lengkap wajib diisi.'], 'Satu isian perlu diperbaiki sebelum disimpan.'],
]);

it('F-06: spasi pada NIK dibuang sebelum validasi', function () {
    $akun = User::factory()->penghuni()->create();

    isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $akun->id, ['nik' => '3216 0123 4567 8901'])
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Penghuni::query()->where('user_id', $akun->id)->first()->no_identitas_enc)->toBe(NIK_UJI_FORM);
});

it('F-06: akun yang tidak memenuhi syarat ditolak walau dikirim langsung', function (string $jenis) {
    $idAkun = match ($jenis) {
        'vendor' => User::factory()->vendor()->create()->id,
        'nonaktif' => User::factory()->penghuni()->nonaktif()->create()->id,
        'sudah punya data' => Penghuni::factory()->create()->user_id,
        'tidak ada' => 999999,
    };

    isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $idAkun)
        ->call('simpan')
        ->assertHasErrors('user_id');

    expect(Penghuni::query()->where('user_id', $idAkun)->count())->toBe($jenis === 'sudah punya data' ? 1 : 0);
})->with(['vendor', 'nonaktif', 'sudah punya data', 'tidak ada']);

it('F-06: pesan galat akun berbeda untuk belum dipilih dan sudah punya data', function () {
    $pengelola = User::factory()->pengelola()->create();

    isiFormPenghuni(formPenghuniSebagai($pengelola), '')
        ->call('simpan')
        ->assertHasErrors(['user_id' => ['Pilih akun penghuni.']]);

    isiFormPenghuni(formPenghuniSebagai($pengelola), Penghuni::factory()->create()->user_id)
        ->call('simpan')
        ->assertHasErrors(['user_id' => ['Akun ini sudah punya data penghuni.']]);
});

it('F-06: pilihan akun hanya memuat akun penghuni aktif yang belum punya data', function () {
    $layak = User::factory()->penghuni()->create(['nama' => 'Akun Layak', 'email' => 'layak@contoh.test']);
    User::factory()->penghuni()->nonaktif()->create(['nama' => 'Akun Mati']);
    User::factory()->vendor()->create(['nama' => 'Akun Vendor']);
    Penghuni::factory()->create(['user_id' => User::factory()->penghuni()->create(['nama' => 'Akun Terpakai'])]);

    $komponen = formPenghuniSebagai(User::factory()->pengelola()->create());

    expect($komponen->instance()->akunTersedia->pluck('id')->all())->toBe([$layak->id]);

    $komponen->assertSee('Akun Layak, layak@contoh.test')
        ->assertDontSee('Akun Mati')
        ->assertDontSee('Akun Vendor')
        ->assertDontSee('Akun Terpakai')
        ->assertDontSee('Semua akun penghuni sudah punya data.');
});

it('F-06: callout muncul dan tombol simpan nonaktif bila tidak ada akun yang memenuhi syarat', function () {
    formPenghuniSebagai(User::factory()->pengelola()->create())
        ->assertSee('Semua akun penghuni sudah punya data.')
        ->assertSee('Akun pengguna')
        ->assertSeeHtml(route('akun.index'))
        ->assertSeeHtml('data-test="simpan-penghuni"')
        ->assertSeeHtml('disabled');
});

it('F-06: ubah dengan NIK kosong mempertahankan NIK lama dan ubah dengan NIK baru menggantinya', function () {
    $penghuni = Penghuni::factory()->create(['no_identitas_enc' => NIK_UJI_FORM, 'no_telepon' => '081111111111']);
    $pengelola = User::factory()->pengelola()->create();

    formPenghuniSebagai($pengelola, $penghuni)
        ->assertSet('nik', '')
        ->assertSet('no_telepon', '081111111111')
        ->set('nama', 'Nama Baru')
        ->set('no_telepon', '+62 813 2222 3333')
        ->call('simpan')
        ->assertHasNoErrors();

    $penghuni->refresh();

    expect($penghuni->no_identitas_enc)->toBe(NIK_UJI_FORM)
        ->and($penghuni->no_telepon)->toBe('081322223333')
        ->and($penghuni->user->fresh()->nama)->toBe('Nama Baru');

    formPenghuniSebagai($pengelola, $penghuni)
        ->set('nik', '3201987654321098')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($penghuni->fresh()->no_identitas_enc)->toBe('3201987654321098');

    formPenghuniSebagai($pengelola, $penghuni)
        ->set('nik', '123')
        ->call('simpan')
        ->assertHasErrors(['nik' => ['NIK harus 16 angka. Baru 3 angka.']]);
});

it('F-06: form ubah menampilkan email akun hanya-baca dan NIK tersamar tanpa select akun', function () {
    $penghuni = Penghuni::factory()->create(['no_identitas_enc' => NIK_UJI_FORM]);

    formPenghuniSebagai(User::factory()->pengelola()->create(), $penghuni)
        ->assertSee($penghuni->user->email)
        ->assertSee('Akun tidak bisa diganti.')
        ->assertSee('Tersimpan: 32**********8901.')
        ->assertSee('Kosongkan bila tidak berubah.')
        ->assertDontSee(NIK_UJI_FORM)
        ->assertDontSeeHtml('data-test="pilih-akun"');
});

it('F-06: tandai keluar berhasil tanpa kontrak aktif dan tidak mengubah status akun login', function () {
    $penghuni = Penghuni::factory()->create();
    $pengelola = User::factory()->pengelola()->create();

    formPenghuniSebagai($pengelola, $penghuni)
        ->call('nonaktifkanPenghuni')
        ->assertSee('Penghuni sudah keluar');

    expect($penghuni->fresh()->status_penghuni)->toBe(StatusPenghuni::Nonaktif)
        ->and($penghuni->user->fresh()->status_akun)->toBe(StatusAkun::Aktif);

    formPenghuniSebagai($pengelola, $penghuni->fresh())
        ->call('aktifkanKembali')
        ->assertSee('Penghuni keluar');

    expect($penghuni->fresh()->status_penghuni)->toBe(StatusPenghuni::Aktif)
        ->and($penghuni->user->fresh()->status_akun)->toBe(StatusAkun::Aktif);
});

it('F-06: tandai keluar ditolak dengan pesan bila penghuni masih punya kontrak aktif', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '05']);
    $penghuni = Penghuni::factory()->create(['user_id' => User::factory()->penghuni()->create(['nama' => 'Maya Sari'])]);
    Kontrak::factory()->aktif()->create(['kamar_id' => $kamar->id, 'penghuni_id' => $penghuni->id]);
    $pengelola = User::factory()->pengelola()->create();

    formPenghuniSebagai($pengelola, $penghuni)
        ->assertSee('Maya Sari masih punya kontrak aktif di Kamar 05. Akhiri kontraknya dulu sebelum menandai keluar.')
        ->assertDontSeeHtml('data-test="buka-tandai-keluar"')
        ->call('nonaktifkanPenghuni')
        ->assertForbidden();

    expect($penghuni->fresh()->status_penghuni)->toBe(StatusPenghuni::Aktif);
});

it('F-06: pemilik, penghuni, dan vendor mendapat 403 di form tambah dan ubah', function (string $peran) {
    $penghuni = Penghuni::factory()->create();
    $user = User::factory()->{$peran}()->create();

    $this->actingAs($user)->get('/penghuni/tambah')->assertForbidden()->assertSee('Akses ditolak');
    $this->actingAs($user)->get(route('penghuni.ubah', $penghuni))->assertForbidden()->assertSee('Akses ditolak');
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-06: pengelola dapat membuka form tambah dan ubah', function () {
    $pengelola = User::factory()->pengelola()->create();

    $this->actingAs($pengelola)->get('/penghuni/tambah')->assertOk();
    $this->actingAs($pengelola)->get(route('penghuni.ubah', Penghuni::factory()->create()))->assertOk();
});

it('F-06: aksi simpan, nonaktifkan, dan aktifkan yang dipanggil langsung oleh pemilik ditolak', function () {
    $penghuni = Penghuni::factory()->create();
    $pengelola = User::factory()->pengelola()->create();
    $pemilik = User::factory()->pemilik()->create();
    $namaAsli = $penghuni->user->nama;

    // Komponen dibuka sebagai pengelola, lalu dipanggil sebagai pemilik; satu komponen per aksi karena respons 403 mengakhiri sesinya.
    $komponenSimpan = formPenghuniSebagai($pengelola, $penghuni);
    $komponenNonaktif = formPenghuniSebagai($pengelola, $penghuni);

    $this->actingAs($pemilik);

    $komponenSimpan->set('nama', 'Diubah Pemilik')->call('simpan')->assertForbidden();
    $komponenNonaktif->call('nonaktifkanPenghuni')->assertForbidden();

    $penghuni->nonaktifkan();
    $this->actingAs($pengelola);
    $komponenAktif = formPenghuniSebagai($pengelola, $penghuni->fresh());
    $this->actingAs($pemilik);
    $komponenAktif->call('aktifkanKembali')->assertForbidden();

    $penghuni->aktifkan();

    expect($penghuni->user->fresh()->nama)->toBe($namaAsli)
        ->and($penghuni->fresh()->status_penghuni)->toBe(StatusPenghuni::Aktif);
});

it('F-06: aksi tambah yang dipanggil langsung oleh peran lain ditolak', function () {
    $akun = User::factory()->penghuni()->create();
    $komponen = isiFormPenghuni(formPenghuniSebagai(User::factory()->pengelola()->create()), $akun->id);

    $this->actingAs(User::factory()->vendor()->create());

    $komponen->call('simpan')->assertForbidden();

    expect(Penghuni::query()->count())->toBe(0);
});
