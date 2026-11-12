<?php

use App\Enums\StatusKontrak;
use App\Exceptions\KontrakSudahBerakhir;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function formPerpanjangSebagai(User $user, Kontrak $kontrak)
{
    return Livewire::actingAs($user)->test('pages::kontrak.form', ['kontrak' => $kontrak]);
}

function kontrakAkanBerakhir(): Kontrak
{
    $kontrak = buatKontrakUji('03', 'Salsa Nabila', [
        'tanggal_mulai' => '2026-05-21',
        'tanggal_berakhir' => '2026-11-20',
        'tarif_bulanan' => 1250000,
    ]);

    // Tarif kamar sudah berubah sejak kontrak lama dibuat.
    $kontrak->kamar->update(['tarif_bulanan' => 1400000]);

    return $kontrak;
}

it('F-07 butir 6: perpanjangan menutup kontrak lama tanpa mengubah tanggalnya dan membuat kontrak baru', function () {
    $lama = kontrakAkanBerakhir();

    formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tanggal_berakhir', '2027-11-20')
        ->set('tarif_bulanan', '1.350.000')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('kontrak.index'));

    $lama->refresh();
    $baru = Kontrak::query()->aktif()->firstOrFail();

    expect($lama->status_kontrak)->toBe(StatusKontrak::Berakhir)
        ->and($lama->tanggal_mulai->toDateString())->toBe('2026-05-21')
        ->and($lama->tanggal_berakhir->toDateString())->toBe('2026-11-20')
        ->and((int) $lama->tarif_bulanan)->toBe(1250000)
        ->and($baru->id)->not->toBe($lama->id)
        ->and($baru->penghuni_id)->toBe($lama->penghuni_id)
        ->and($baru->kamar_id)->toBe($lama->kamar_id)
        ->and($baru->tanggal_mulai->toDateString())->toBe('2026-11-21')
        ->and($baru->tanggal_berakhir->toDateString())->toBe('2027-11-20')
        ->and((int) $baru->tarif_bulanan)->toBe(1350000)
        ->and($baru->status_kontrak)->toBe(StatusKontrak::Aktif)
        ->and(session('toast'))->toBe('Kontrak Kamar 03 diperpanjang sampai 20 November 2027.');
});

it('F-07 butir 6: riwayat penghuni punya dua kontrak dan kamar tetap terisi', function () {
    $lama = kontrakAkanBerakhir();

    formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tanggal_berakhir', '2027-11-20')
        ->call('simpan')
        ->assertHasNoErrors();

    $penghuni = $lama->penghuni->fresh();

    expect($penghuni->kontrak)->toHaveCount(2)
        ->and($penghuni->kontrak->where('status_kontrak', StatusKontrak::Aktif))->toHaveCount(1)
        ->and($penghuni->kontrak->where('status_kontrak', StatusKontrak::Berakhir))->toHaveCount(1)
        ->and($penghuni->kontrakAktif->id)->not->toBe($lama->id)
        ->and($lama->kamar->fresh()->status_hunian)->toBe(Kamar::HUNIAN_TERISI)
        ->and(Kontrak::query()->where('kamar_id', $lama->kamar_id)->count())->toBe(2);
});

it('F-07: formulir perpanjang menampilkan ringkasan, catatan, dan tarif kamar sebagai bawaan', function () {
    $lama = kontrakAkanBerakhir();

    formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->assertSee('Perpanjang Kontrak')
        ->assertSeeInOrder(['Penghuni', 'Salsa Nabila', 'Kamar', '03', 'Mulai', '21 November 2026'])
        ->assertSee('Kontrak lama (21 Mei 2026 sampai 20 November 2026) ditutup dan tersimpan di riwayat.')
        ->assertSet('tarif_bulanan', '1.400.000')
        ->assertSee('Tarif lama Rp1.250.000.')
        ->assertSee('Simpan');
});

it('F-07: tanggal berakhir baru harus setelah tanggal mulai baru', function (string $tanggal) {
    $lama = kontrakAkanBerakhir();

    formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tanggal_berakhir', $tanggal)
        ->call('simpan')
        ->assertHasErrors(['tanggal_berakhir' => ['Harus setelah tanggal mulai.']]);

    expect($lama->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif)
        ->and(Kontrak::query()->count())->toBe(1);
})->with([
    'sama dengan mulai baru' => '2026-11-21',
    'sebelum mulai baru' => '2026-11-10',
]);

it('F-07: tarif perpanjangan wajib lebih dari 0 dan tanggal berakhir wajib diisi', function () {
    $lama = kontrakAkanBerakhir();

    formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tarif_bulanan', '0')
        ->call('simpan')
        ->assertHasErrors([
            'tanggal_berakhir' => ['Isi tanggal berakhir.'],
            'tarif_bulanan' => ['Isi tarif dalam rupiah, lebih dari 0.'],
        ])
        ->assertSee('Dua isian perlu diperbaiki sebelum disimpan.');

    expect(Kontrak::query()->count())->toBe(1);
});

it('F-07: perpanjang kontrak yang sudah berakhir mendapat 403 dengan pesan Policy', function () {
    $berakhir = buatKontrakUji('03', 'Salsa Nabila', [
        'tanggal_mulai' => '2025-01-01',
        'tanggal_berakhir' => '2025-12-31',
        'status_kontrak' => StatusKontrak::Berakhir,
    ]);

    $this->actingAs(User::factory()->pengelola()->create())
        ->get(route('kontrak.perpanjang', $berakhir))
        ->assertForbidden();

    Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::kontrak.form', ['kontrak' => $berakhir])
        ->assertForbidden();
});

it('F-07: kontrak yang berakhir di tengah pengisian tidak bisa diperpanjang saat disimpan', function () {
    $lama = kontrakAkanBerakhir();

    $komponen = formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tanggal_berakhir', '2027-11-20');

    Kontrak::query()->find($lama->id)->akhiri();

    $komponen->call('simpan')->assertForbidden();

    expect(Kontrak::query()->aktif()->count())->toBe(0);
});

it('F-07: perpanjangan kontrak yang sudah lewat tanggalnya tetap mulai sehari setelah tanggal berakhir lama', function () {
    $lama = buatKontrakUji('06', 'Laila Kusuma', ['tanggal_mulai' => '2026-05-01', 'tanggal_berakhir' => '2026-11-07']);

    formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tanggal_berakhir', '2027-05-07')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Kontrak::query()->aktif()->firstOrFail()->tanggal_mulai->toDateString())->toBe('2026-11-08');
});

it('F-07: model perpanjang menolak kontrak berakhir dan tanggal berakhir yang tidak setelah mulai baru', function () {
    $lama = kontrakAkanBerakhir();

    expect(fn () => $lama->perpanjang(Carbon::parse('2026-11-21'), 1400000))->toThrow(InvalidArgumentException::class);

    $lama->akhiri();

    expect(fn () => $lama->perpanjang(Carbon::parse('2027-11-20'), 1400000))
        ->toThrow(KontrakSudahBerakhir::class, 'Kontrak ini sudah berakhir.');
});

it('F-07: pemilik, penghuni, dan vendor mendapat 403 di /kontrak/{id}/perpanjang', function (string $peran) {
    $lama = kontrakAkanBerakhir();
    $user = User::factory()->{$peran}()->create();

    $this->actingAs($user)->get(route('kontrak.perpanjang', $lama))->assertForbidden()->assertSee('Akses ditolak');

    expect(Kontrak::query()->count())->toBe(1)
        ->and($lama->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif);
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-07: komponen perpanjang yang sudah terbuka tidak bisa menyimpan bila peran berubah', function () {
    $lama = kontrakAkanBerakhir();

    $komponen = formPerpanjangSebagai(User::factory()->pengelola()->create(), $lama)
        ->set('tanggal_berakhir', '2027-11-20');

    $this->actingAs(User::factory()->pemilik()->create());

    $komponen->call('simpan')->assertForbidden();

    expect($lama->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif);
});
