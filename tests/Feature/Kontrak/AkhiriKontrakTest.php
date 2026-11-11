<?php

use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use App\Enums\StatusTagihan;
use App\Exceptions\KontrakSudahBerakhir;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Pencocok event toast Flux berdasarkan teksnya.
 */
function adalahToast(string $teks): Closure
{
    return fn (string $nama, array $parameter): bool => ($parameter['slots']['text'] ?? null) === $teks;
}

function halamanKontrakSebagai(User $user)
{
    return Livewire::actingAs($user)->test('pages::kontrak.index');
}

function kontrakBerjalan(string $nomor = '07', string $nama = 'Nadia Ramadhani'): Kontrak
{
    return buatKontrakUji($nomor, $nama, ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-06-30']);
}

it('F-07 butir 5: kontrak berjalan diakhiri dengan tanggal keluar hari ini; kamar kembali kosong', function () {
    $kontrak = kontrakBerjalan();

    expect($kontrak->kamar->status_hunian)->toBe(Kamar::HUNIAN_TERISI);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->assertSet('tanggal_keluar', '2026-11-11')
        ->assertSee('Akhiri kontrak Kamar 07?')
        ->assertSee('Nadia Ramadhani. Kamar menjadi kosong dan kontrak pindah ke riwayat.')
        ->assertSee('Tanggal keluar')
        ->assertSee('Tanggal berakhir kontrak diganti dengan tanggal ini.')
        ->call('akhiri')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', adalahToast('Kontrak Kamar 07 diakhiri.'));

    $kontrak->refresh();

    expect($kontrak->status_kontrak)->toBe(StatusKontrak::Berakhir)
        ->and($kontrak->tanggal_berakhir->toDateString())->toBe('2026-11-11')
        ->and($kontrak->kamar->fresh()->status_hunian)->toBe(Kamar::HUNIAN_KOSONG);
});

it('F-07: tanggal keluar boleh lebih awal dari hari ini', function () {
    $kontrak = kontrakBerjalan();

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->set('tanggal_keluar', '2026-11-05')
        ->call('akhiri')
        ->assertHasNoErrors();

    expect($kontrak->fresh()->tanggal_berakhir->toDateString())->toBe('2026-11-05');
});

it('F-07: tanggal keluar divalidasi terhadap tanggal mulai, hari ini, dan tanggal berakhir', function (string $tanggal, string $pesan) {
    $kontrak = buatKontrakUji('07', 'Nadia Ramadhani', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-30']);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->set('tanggal_keluar', $tanggal)
        ->call('akhiri')
        ->assertHasErrors(['tanggal_keluar' => [$pesan]]);

    expect($kontrak->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif)
        ->and($kontrak->fresh()->tanggal_berakhir->toDateString())->toBe('2026-11-30');
})->with([
    'sama dengan mulai' => ['2026-01-01', 'Tanggal keluar harus setelah tanggal mulai kontrak.'],
    'sebelum mulai' => ['2025-12-01', 'Tanggal keluar harus setelah tanggal mulai kontrak.'],
    'setelah hari ini' => ['2026-11-12', 'Tanggal keluar tidak boleh setelah hari ini.'],
    'kosong' => ['', 'Isi tanggal keluar.'],
]);

it('F-07: tanggal keluar tidak boleh setelah tanggal berakhir kontrak', function () {
    // Kontrak sudah lewat tanggal berakhirnya tetapi belum diakhiri command.
    $kontrak = buatKontrakUji('07', 'Nadia Ramadhani', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-07']);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->assertSet('tanggal_keluar', '2026-11-07')
        ->set('tanggal_keluar', '2026-11-10')
        ->call('akhiri')
        ->assertHasErrors(['tanggal_keluar' => ['Tanggal keluar tidak boleh setelah tanggal berakhir kontrak.']]);

    expect($kontrak->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif);
});

it('F-07: kontrak yang belum dimulai diakhiri tanpa field tanggal dan tanggalnya tidak berubah', function () {
    $kontrak = buatKontrakUji('07', 'Nadia Ramadhani', ['tanggal_mulai' => '2026-11-11', 'tanggal_berakhir' => '2027-11-10']);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->assertSee('Kontrak ini belum dimulai. Tanggalnya disimpan apa adanya.')
        ->assertDontSeeHtml('data-test="input-tanggal-keluar"')
        ->call('akhiri')
        ->assertHasNoErrors();

    $kontrak->refresh();

    expect($kontrak->status_kontrak)->toBe(StatusKontrak::Berakhir)
        ->and($kontrak->tanggal_mulai->toDateString())->toBe('2026-11-11')
        ->and($kontrak->tanggal_berakhir->toDateString())->toBe('2027-11-10');
});

it('F-07: mengakhiri tidak mengubah tagihan, pembayaran, dan status penghuni', function () {
    $kontrak = kontrakBerjalan();
    $tagihan = Tagihan::factory()->belumDibayar()->create(['kontrak_id' => $kontrak->id]);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->call('akhiri')
        ->assertHasNoErrors();

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and($tagihan->fresh()->nominal)->toBe($tagihan->nominal)
        ->and(Tagihan::query()->count())->toBe(1)
        ->and($kontrak->penghuni->fresh()->status_penghuni)->toBe(StatusPenghuni::Aktif);
});

it('F-07: mengakhiri kontrak yang sudah berakhir ditolak dengan pesan dan tanpa mengubah data', function () {
    $kontrak = buatKontrakUji('07', 'Nadia Ramadhani', [
        'tanggal_mulai' => '2025-01-01',
        'tanggal_berakhir' => '2025-12-31',
        'status_kontrak' => StatusKontrak::Berakhir,
    ]);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->assertDispatched('toast-show', adalahToast('Kontrak ini sudah berakhir.'))
        ->assertSet('idAkhiri', null);

    expect($kontrak->fresh()->tanggal_berakhir->toDateString())->toBe('2025-12-31');

    // Model juga menolak, sebagai pagar terakhir di dalam transaksi.
    expect(fn () => $kontrak->akhiri())->toThrow(KontrakSudahBerakhir::class, 'Kontrak ini sudah berakhir.');
});

it('F-07: kontrak yang diakhiri di tab lain lebih dulu menghasilkan pesan sudah berakhir saat dikonfirmasi', function () {
    $kontrak = kontrakBerjalan();

    $komponen = halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiAkhiri', $kontrak->id);

    Kontrak::query()->whereKey($kontrak->id)->first()->akhiri();

    $komponen->call('akhiri')
        ->assertDispatched('toast-show', adalahToast('Kontrak ini sudah berakhir.'));
});

it('F-07: model akhiri tanpa tanggal tidak mengubah tanggal; dengan tanggal menggantinya', function () {
    $a = kontrakBerjalan('01', 'Satu');
    $b = kontrakBerjalan('02', 'Dua');

    $a->akhiri();
    $b->akhiri(Carbon::parse('2026-10-01'));

    expect($a->fresh()->status_kontrak)->toBe(StatusKontrak::Berakhir)
        ->and($a->fresh()->tanggal_berakhir->toDateString())->toBe('2027-06-30')
        ->and($b->fresh()->tanggal_berakhir->toDateString())->toBe('2026-10-01');
});

it('F-07: pemilik, penghuni, dan vendor yang memanggil aksi akhiri langsung mendapat 403', function (string $peran) {
    $kontrak = kontrakBerjalan();

    // Pemilik boleh membuka halaman daftar, tetapi aksi akhiri tetap ditolak Policy.
    if ($peran === 'pemilik') {
        halamanKontrakSebagai(User::factory()->pemilik()->create())
            ->call('konfirmasiAkhiri', $kontrak->id)
            ->assertForbidden();

        halamanKontrakSebagai(User::factory()->pemilik()->create())
            ->call('akhiri')
            ->assertForbidden();
    } else {
        halamanKontrakSebagai(User::factory()->{$peran}()->create())->assertForbidden();
    }

    expect($kontrak->fresh()->status_kontrak)->toBe(StatusKontrak::Aktif);
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-07: Policy akhiri dan perpanjang hanya mengizinkan pengelola pada kontrak aktif', function () {
    $aktif = kontrakBerjalan('01', 'Aktif');
    $berakhir = buatKontrakUji('02', 'Berakhir', ['tanggal_mulai' => '2025-01-01', 'tanggal_berakhir' => '2025-12-31', 'status_kontrak' => StatusKontrak::Berakhir]);
    $pengelola = User::factory()->pengelola()->create();
    $pemilik = User::factory()->pemilik()->create();

    foreach (['akhiri', 'perpanjang'] as $aksi) {
        expect($pengelola->can($aksi, $aktif))->toBeTrue()
            ->and($pengelola->can($aksi, $berakhir))->toBeFalse()
            ->and(Gate::forUser($pengelola)->inspect($aksi, $berakhir)->message())->toBe('Kontrak ini sudah berakhir.')
            ->and($pemilik->can($aksi, $aktif))->toBeFalse()
            ->and(Gate::forUser($pemilik)->inspect($aksi, $aktif)->message())->toBe('Anda tidak memiliki akses ke halaman ini.');
    }

    expect($pengelola->can('create', Kontrak::class))->toBeTrue()
        ->and($pemilik->can('create', Kontrak::class))->toBeFalse()
        ->and($pemilik->can('viewAny', Kontrak::class))->toBeTrue();
});

it('F-07: bagian akan berakhir dan daftar diperbarui setelah kontrak diakhiri', function () {
    $kontrak = buatKontrakUji('03', 'Salsa Nabila', ['tanggal_mulai' => '2026-05-21', 'tanggal_berakhir' => '2026-11-20']);

    halamanKontrakSebagai(User::factory()->pengelola()->create())
        ->assertSee('Salsa Nabila')
        ->assertSee('9 hari lagi')
        ->call('konfirmasiAkhiri', $kontrak->id)
        ->call('akhiri')
        ->assertDontSee('9 hari lagi')
        ->assertSee('Tidak ada kontrak yang akan berakhir.')
        ->assertSee('Tidak ada kontrak yang sesuai filter.');
});
