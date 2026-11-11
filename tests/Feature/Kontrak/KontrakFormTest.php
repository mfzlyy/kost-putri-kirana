<?php

use App\Enums\StatusAkun;
use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use App\Exceptions\KontrakBentrok;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function formKontrakSebagai(User $user)
{
    return Livewire::actingAs($user)->test('pages::kontrak.form');
}

function penghuniTanpaKontrak(string $nama = 'Nadia Ramadhani'): Penghuni
{
    return Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => $nama]),
    ]);
}

function isiFormKontrak($komponen, Penghuni $penghuni, Kamar $kamar, array $ganti = [])
{
    $isi = array_merge([
        'penghuni_id' => (string) $penghuni->id,
        'kamar_id' => (string) $kamar->id,
        'tanggal_mulai' => '2026-11-15',
        'tanggal_berakhir' => '2027-11-14',
        'tarif_bulanan' => '1.400.000',
    ], $ganti);

    foreach ($isi as $kolom => $nilai) {
        $komponen->set($kolom, $nilai);
    }

    return $komponen;
}

it('F-07: pengelola membuat kontrak; kamar menjadi terisi dan daftar kamar kosong berkurang', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '07', 'tarif_bulanan' => 1400000]);
    Kamar::factory()->create(['nomor_kamar' => '08']);
    $penghuni = penghuniTanpaKontrak();

    expect(Kamar::kosong()->count())->toBe(2)
        ->and($kamar->status_hunian)->toBe(Kamar::HUNIAN_KOSONG);

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), $penghuni, $kamar)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('kontrak.index'));

    $kontrak = Kontrak::query()->firstOrFail();

    expect($kontrak->penghuni_id)->toBe($penghuni->id)
        ->and($kontrak->kamar_id)->toBe($kamar->id)
        ->and($kontrak->tanggal_mulai->toDateString())->toBe('2026-11-15')
        ->and($kontrak->tanggal_berakhir->toDateString())->toBe('2027-11-14')
        ->and($kontrak->status_kontrak)->toBe(StatusKontrak::Aktif)
        ->and($kamar->fresh()->status_hunian)->toBe(Kamar::HUNIAN_TERISI)
        ->and(Kamar::kosong()->count())->toBe(1)
        ->and(session('toast'))->toBe('Kontrak Kamar 07 untuk Nadia Ramadhani disimpan.');
});

it('F-07: kontrak boleh dimulai pada tanggal lampau untuk mencatat kontrak yang sudah berjalan', function () {
    $kamar = Kamar::factory()->create();
    $penghuni = penghuniTanpaKontrak();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), $penghuni, $kamar, ['tanggal_mulai' => '2026-03-01'])
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Kontrak::query()->value('tanggal_mulai')->toDateString())->toBe('2026-03-01');
});

it('F-07: bawaan tanggal mulai adalah hari ini', function () {
    formKontrakSebagai(User::factory()->pengelola()->create())->assertSet('tanggal_mulai', '2026-11-11');
});

it('F-07 butir 1: pilihan penghuni hanya penghuni aktif, akun aktif, tanpa kontrak aktif', function () {
    $tersedia = penghuniTanpaKontrak('Tersedia');
    buatKontrakUji('01', 'Sudah Berkontrak');
    Penghuni::factory()->nonaktif()->create(['user_id' => User::factory()->penghuni()->create(['nama' => 'Sudah Keluar'])]);
    Penghuni::factory()->create(['user_id' => User::factory()->penghuni()->nonaktif()->create(['nama' => 'Akun Nonaktif'])]);
    // Penghuni yang kontraknya sudah berakhir boleh dipilih lagi.
    buatKontrakUji('02', 'Kontrak Lama', ['status_kontrak' => StatusKontrak::Berakhir]);

    $komponen = formKontrakSebagai(User::factory()->pengelola()->create());

    expect($komponen->instance()->penghuniTersedia->map(fn ($penghuni) => $penghuni->user->nama)->all())
        ->toBe(['Kontrak Lama', 'Tersedia'])
        ->and($komponen->instance()->penghuniTersedia->pluck('id'))->toContain($tersedia->id);

    $komponen->assertSee('Hanya penghuni tanpa kontrak aktif.')
        ->assertDontSee('Sudah Berkontrak')
        ->assertDontSee('Sudah Keluar')
        ->assertDontSee('Akun Nonaktif');
});

it('F-07: bila semua penghuni aktif sudah berkontrak tampil callout dan tombol simpan nonaktif', function () {
    buatKontrakUji('01', 'Sudah Berkontrak');

    formKontrakSebagai(User::factory()->pengelola()->create())
        ->assertSee('Semua penghuni aktif sudah punya kontrak.')
        ->assertSee('Tambah data penghuni')
        ->assertSeeHtml(route('penghuni.tambah'))
        ->assertSeeHtml('data-test="simpan-kontrak"')
        ->assertSeeHtml('disabled');
});

it('F-07 butir 1: tarif terisi dari kamar saat kamar dipilih dan tarif manual tidak tertimpa', function () {
    $kamarA = Kamar::factory()->create(['nomor_kamar' => '07', 'tarif_bulanan' => 1400000]);
    $kamarB = Kamar::factory()->create(['nomor_kamar' => '08', 'tarif_bulanan' => 1250000]);

    $komponen = formKontrakSebagai(User::factory()->pengelola()->create())
        ->assertSee('Pilih kamar untuk mengisi tarif.')
        ->set('kamar_id', (string) $kamarA->id)
        ->assertSet('tarif_bulanan', '1.400.000')
        ->assertSee('Diisi dari tarif Kamar 07. Bisa disesuaikan.')
        // Ganti kamar selama tarif belum diubah: tarif mengikuti kamar baru.
        ->set('kamar_id', (string) $kamarB->id)
        ->assertSet('tarif_bulanan', '1.250.000')
        // Tarif diubah manual: ganti kamar tidak menimpanya.
        ->set('tarif_bulanan', '1.300.000')
        ->set('kamar_id', (string) $kamarA->id)
        ->assertSet('tarif_bulanan', '1.300.000');
});

it('F-07: tarif 1.350.000 tersimpan 1350000', function () {
    $kamar = Kamar::factory()->create();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), penghuniTanpaKontrak(), $kamar, ['tarif_bulanan' => '1.350.000'])
        ->call('simpan')
        ->assertHasNoErrors();

    expect((int) Kontrak::query()->value('tarif_bulanan'))->toBe(1350000);
});

it('F-07: tarif 0 atau kosong ditolak', function (string $tarif) {
    $kamar = Kamar::factory()->create();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), penghuniTanpaKontrak(), $kamar, ['tarif_bulanan' => $tarif])
        ->call('simpan')
        ->assertHasErrors(['tarif_bulanan' => ['Isi tarif dalam rupiah, lebih dari 0.']]);

    expect(Kontrak::query()->count())->toBe(0);
})->with(['0', '', 'abc']);

it('F-07 butir 2: tanggal berakhir sama dengan atau sebelum mulai ditolak', function (string $berakhir) {
    $kamar = Kamar::factory()->create();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), penghuniTanpaKontrak(), $kamar, ['tanggal_berakhir' => $berakhir])
        ->call('simpan')
        ->assertHasErrors(['tanggal_berakhir' => ['Harus setelah tanggal mulai.']]);

    expect(Kontrak::query()->count())->toBe(0);
})->with(['sama dengan mulai' => '2026-11-15', 'sebelum mulai' => '2026-11-01']);

it('F-07: tanggal berakhir sebelum hari ini ditolak dengan pesan migrasi data', function () {
    $kamar = Kamar::factory()->create();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), penghuniTanpaKontrak(), $kamar, [
        'tanggal_mulai' => '2026-01-01',
        'tanggal_berakhir' => '2026-11-10',
    ])
        ->call('simpan')
        ->assertHasErrors(['tanggal_berakhir' => ['Tanggal berakhir sudah lewat. Kontrak yang sudah selesai dicatat lewat migrasi data.']]);
});

it('F-07: tanggal berakhir hari ini masih boleh', function () {
    $kamar = Kamar::factory()->create();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), penghuniTanpaKontrak(), $kamar, [
        'tanggal_mulai' => '2026-01-01',
        'tanggal_berakhir' => '2026-11-11',
    ])
        ->call('simpan')
        ->assertHasNoErrors();
});

it('F-07: penghuni dan kamar wajib dipilih; ringkasan galat menyebut jumlah isian', function () {
    Kamar::factory()->create();
    penghuniTanpaKontrak();

    formKontrakSebagai(User::factory()->pengelola()->create())
        ->set('tanggal_berakhir', '')
        ->call('simpan')
        ->assertHasErrors([
            'penghuni_id' => ['Pilih penghuni.'],
            'kamar_id' => ['Pilih kamar.'],
            'tanggal_berakhir' => ['Isi tanggal berakhir.'],
            'tarif_bulanan' => ['Isi tarif dalam rupiah, lebih dari 0.'],
        ])
        ->assertSee('Empat isian perlu diperbaiki sebelum disimpan.');
});

it('F-07 butir 3: kamar yang sudah punya kontrak aktif ditolak, juga bila kamar_id dikirim langsung', function () {
    $terisi = buatKontrakUji('03', 'Salsa Nabila')->kamar;

    $komponen = formKontrakSebagai(User::factory()->pengelola()->create());

    isiFormKontrak($komponen, penghuniTanpaKontrak(), $terisi)
        ->call('simpan')
        ->assertHasErrors(['kamar_id' => ['Kamar 03 sudah terisi.']])
        ->assertNoRedirect();

    expect(Kontrak::query()->count())->toBe(1);
});

it('F-07 butir 4: penghuni yang sudah punya kontrak aktif ditolak', function () {
    $penghuni = buatKontrakUji('01', 'Anisa Putri')->penghuni;
    $kosong = Kamar::factory()->create();

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), $penghuni, $kosong)
        ->call('simpan')
        ->assertHasErrors(['penghuni_id' => ['Penghuni ini sudah punya kontrak aktif.']]);

    expect(Kontrak::query()->count())->toBe(1);
});

it('F-07: penghuni nonaktif atau berakun nonaktif ditolak walau ID dikirim langsung', function () {
    $kamar = Kamar::factory()->create();
    $keluar = Penghuni::factory()->nonaktif()->create();
    $akunMati = Penghuni::factory()->create(['user_id' => User::factory()->penghuni()->nonaktif()->create()]);

    foreach ([$keluar, $akunMati] as $penghuni) {
        isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), $penghuni, $kamar)
            ->call('simpan')
            ->assertHasErrors(['penghuni_id' => ['Pilih penghuni.']]);
    }

    expect(Kontrak::query()->count())->toBe(0)
        ->and($keluar->fresh()->status_penghuni)->toBe(StatusPenghuni::Nonaktif)
        ->and($akunMati->user->fresh()->status_akun)->toBe(StatusAkun::Nonaktif);
});

it('F-07: kisi kamar menandai kamar terisi, kamar yang segera kosong, dan kamar kosong', function () {
    buatKontrakUji('01', 'Terisi Lama', ['tanggal_berakhir' => '2027-06-30']);
    buatKontrakUji('03', 'Segera Pergi', ['tanggal_berakhir' => '2026-11-20']);
    Kamar::factory()->create(['nomor_kamar' => '07']);

    $html = formKontrakSebagai(User::factory()->pengelola()->create())->html();

    expect($html)->toContain('role="radiogroup"')
        ->and($html)->toContain('s.d. 20 Nov')
        ->and($html)->toContain('Terisi')
        ->and($html)->toContain('Kosong');

    // Kamar terisi tidak bisa dipilih; kamar kosong bisa.
    expect(preg_match('/<input[^>]*data-test="kamar-01"[^>]*>/s', $html, $terisi))->toBe(1)
        ->and($terisi[0])->toContain('disabled')
        ->and($terisi[0])->toContain('aria-disabled="true"');
    expect(preg_match('/<input[^>]*data-test="kamar-07"[^>]*>/s', $html, $kosong))->toBe(1)
        ->and($kosong[0])->not->toContain('disabled');
});

it('ISU-02: tanpa batas peringatan kisi kamar hanya menulis Terisi, tanpa tanggal', function () {
    PengaturanOperasional::aktif()->update(['batas_peringatan_kontrak_hari' => null]);
    buatKontrakUji('03', 'Segera Pergi', ['tanggal_berakhir' => '2026-11-20']);

    formKontrakSebagai(User::factory()->pengelola()->create())
        ->assertDontSee('s.d.');
});

it('F-07: pelanggaran indeks unik kamar dari permintaan bersamaan dipetakan ke galat kamar', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '07']);
    $penghuni = penghuniTanpaKontrak();
    $penghuniLain = penghuniTanpaKontrak('Penghuni Lain');

    // Permintaan lain menyelipkan kontrak aktif untuk kamar yang sama setelah pengecekan aplikasi, sebelum insert.
    Kontrak::creating(function () use ($kamar, $penghuniLain) {
        DB::table('kontrak')->insert([
            'penghuni_id' => $penghuniLain->id,
            'kamar_id' => $kamar->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_berakhir' => '2027-10-31',
            'tarif_bulanan' => 1400000,
            'status_kontrak' => 'aktif',
        ]);
    });

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), $penghuni, $kamar)
        ->call('simpan')
        ->assertHasErrors(['kamar_id' => ['Kamar 07 sudah terisi.']])
        ->assertNoRedirect();

    // Penyisipan dari "permintaan lain" ikut dibatalkan bersama savepoint transaksi uji;
    // yang penting tidak ada kontrak untuk penghuni ini.
    expect(Kontrak::query()->where('penghuni_id', $penghuni->id)->count())->toBe(0);
});

it('F-07: pelanggaran indeks unik penghuni dari permintaan bersamaan dipetakan ke galat penghuni', function () {
    $kamar = Kamar::factory()->create();
    $kamarLain = Kamar::factory()->create();
    $penghuni = penghuniTanpaKontrak();

    Kontrak::creating(function () use ($kamarLain, $penghuni) {
        DB::table('kontrak')->insert([
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $kamarLain->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_berakhir' => '2027-10-31',
            'tarif_bulanan' => 1400000,
            'status_kontrak' => 'aktif',
        ]);
    });

    isiFormKontrak(formKontrakSebagai(User::factory()->pengelola()->create()), $penghuni, $kamar)
        ->call('simpan')
        ->assertHasErrors(['penghuni_id' => ['Penghuni ini sudah punya kontrak aktif.']])
        ->assertNoRedirect();
});

it('F-07: petakanPelanggaranUnik memetakan nama indeks ke field yang benar dan membiarkan indeks lain', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '07']);

    $buat = fn (?string $indeks) => (new UniqueConstraintViolationException('pgsql', 'insert', [], new Exception('x')))->setIndex($indeks);

    $kamarBentrok = Kontrak::petakanPelanggaranUnik($buat('uq_kontrak_aktif_per_kamar'), $kamar->id);
    $penghuniBentrok = Kontrak::petakanPelanggaranUnik($buat('uq_kontrak_aktif_per_penghuni'), $kamar->id);
    $lain = Kontrak::petakanPelanggaranUnik($buat('indeks_lain'), $kamar->id);

    expect($kamarBentrok)->toBeInstanceOf(KontrakBentrok::class)
        ->and($kamarBentrok->field)->toBe('kamar')
        ->and($kamarBentrok->getMessage())->toBe('Kamar 07 sudah terisi.')
        ->and($penghuniBentrok->field)->toBe('penghuni')
        ->and($penghuniBentrok->getMessage())->toBe('Penghuni ini sudah punya kontrak aktif.')
        ->and($lain)->toBeInstanceOf(UniqueConstraintViolationException::class);
});

it('F-07: pemilik, penghuni, dan vendor mendapat 403 di /kontrak/buat dan aksi simpan langsung ditolak', function (string $peran) {
    $user = User::factory()->{$peran}()->create();

    $this->actingAs($user)->get('/kontrak/buat')->assertForbidden()->assertSee('Akses ditolak');

    Livewire::actingAs($user)->test('pages::kontrak.form')->assertForbidden();
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-07: komponen yang sudah terbuka tidak bisa menyimpan bila peran berubah menjadi bukan pengelola', function () {
    $kamar = Kamar::factory()->create();
    $penghuni = penghuniTanpaKontrak();

    $komponen = formKontrakSebagai(User::factory()->pengelola()->create());

    // Sesi berganti ke pemilik di tengah jalan: simpan harus tetap menegakkan Policy di server.
    $this->actingAs(User::factory()->pemilik()->create());

    isiFormKontrak($komponen, $penghuni, $kamar)->call('simpan')->assertForbidden();

    expect(Kontrak::query()->count())->toBe(0);
});
