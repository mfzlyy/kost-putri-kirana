<?php

use App\Enums\StatusKontrak;
use App\Models\PengaturanOperasional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function daftarKontrakSebagai(User $user, array $query = [])
{
    return Livewire::withQueryParams($query)->actingAs($user)->test('pages::kontrak.index');
}

function namaDalamDaftarKontrak(User $user, array $query = []): array
{
    return daftarKontrakSebagai($user, $query)->instance()->daftarKontrak
        ->getCollection()->map(fn ($kontrak) => $kontrak->penghuni->user->nama)->all();
}

it('F-07: pengelola melihat kamar, nama, tanggal berformat, tarif rupiah, dan status', function () {
    buatKontrakUji('01', 'Anisa Putri', [
        'tanggal_mulai' => '2026-01-01',
        'tanggal_berakhir' => '2026-12-31',
        'tarif_bulanan' => 1350000,
    ]);
    buatKontrakUji('02', 'Rina Maharani', [
        'tanggal_mulai' => '2025-01-01',
        'tanggal_berakhir' => '2025-12-31',
        'status_kontrak' => StatusKontrak::Berakhir,
    ]);

    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/kontrak?status=semua')
        ->assertOk()
        ->assertSeeInOrder(['Kamar', 'Penghuni', 'Mulai', 'Berakhir', 'Tarif', 'Status'])
        ->assertSee('Anisa Putri')
        ->assertSee('Rina Maharani')
        ->assertSee('1 Jan 2026')
        ->assertSee('31 Des 2026')
        ->assertSee('Rp1.350.000')
        ->assertSee('1 Jan 2025 sampai 31 Des 2025')
        ->assertSee('Aktif')
        ->assertSee('Buat')
        ->assertDontSee('Perpanjangan dicatat sebagai kontrak baru agar riwayat lama tetap ada.');
});

it('F-07: filter bawaan aktif; berakhir dan semua dipilih lewat query; nilai lain dianggap aktif', function () {
    $pengelola = User::factory()->pengelola()->create();

    buatKontrakUji('02', 'Aktif Dua', ['tanggal_berakhir' => '2027-06-30']);
    buatKontrakUji('01', 'Aktif Satu', ['tanggal_berakhir' => '2027-06-30']);
    buatKontrakUji('03', 'Lama Lebih Awal', ['status_kontrak' => StatusKontrak::Berakhir, 'tanggal_mulai' => '2024-01-01', 'tanggal_berakhir' => '2024-12-31']);
    buatKontrakUji('04', 'Lama Lebih Baru', ['status_kontrak' => StatusKontrak::Berakhir, 'tanggal_mulai' => '2025-01-01', 'tanggal_berakhir' => '2025-12-31']);

    // Aktif menurut nomor kamar; berakhir menurut tanggal berakhir menurun; semua = aktif dulu lalu berakhir.
    expect(namaDalamDaftarKontrak($pengelola))->toBe(['Aktif Satu', 'Aktif Dua'])
        ->and(namaDalamDaftarKontrak($pengelola, ['status' => 'berakhir']))->toBe(['Lama Lebih Baru', 'Lama Lebih Awal'])
        ->and(namaDalamDaftarKontrak($pengelola, ['status' => 'semua']))->toBe(['Aktif Satu', 'Aktif Dua', 'Lama Lebih Baru', 'Lama Lebih Awal'])
        ->and(namaDalamDaftarKontrak($pengelola, ['status' => 'sembarang']))->toBe(['Aktif Satu', 'Aktif Dua']);

    daftarKontrakSebagai($pengelola)
        ->assertSee('Aktif')
        ->assertSeeHtml('aria-label="Saring status"')
        ->assertSet('filterStatus', 'aktif');

    expect(daftarKontrakSebagai($pengelola)->instance()->hitungan)->toBe(['aktif' => 2, 'berakhir' => 2, 'semua' => 4]);
});

it('F-07: daftar kosong menampilkan pesan dan tombol buat; filter tanpa hasil menampilkan pesan filter', function () {
    $pengelola = User::factory()->pengelola()->create();

    daftarKontrakSebagai($pengelola)
        ->assertSee('Belum ada kontrak.')
        ->assertSeeHtml('data-test="buat-kontrak"');

    buatKontrakUji('01', 'Anisa Putri');

    daftarKontrakSebagai($pengelola, ['status' => 'berakhir'])
        ->assertSee('Tidak ada kontrak yang sesuai filter.')
        ->assertDontSee('Belum ada kontrak.');
});

it('F-07: jumlah query daftar tidak bertambah saat jumlah kontrak bertambah', function () {
    $pengelola = User::factory()->pengelola()->create();

    $hitungQuery = function () use ($pengelola): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($pengelola)->get('/kontrak?status=semua')->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    buatKontrakUji('01', 'Anisa Putri', ['tanggal_berakhir' => '2026-11-20']);
    $sedikit = $hitungQuery();

    foreach (['02', '03', '04', '05'] as $nomor) {
        buatKontrakUji($nomor, "Penghuni {$nomor}", ['tanggal_berakhir' => '2026-11-25']);
    }
    buatKontrakUji('06', 'Penghuni Lama', ['status_kontrak' => StatusKontrak::Berakhir, 'tanggal_mulai' => '2025-01-01', 'tanggal_berakhir' => '2025-12-31']);

    expect($hitungQuery())->toBe($sedikit);
});

it('F-07: daftar dipaginasi 15 per halaman', function () {
    foreach (range(1, 17) as $nomor) {
        buatKontrakUji(sprintf('K%02d', $nomor), "Penghuni {$nomor}", ['tanggal_berakhir' => '2027-06-30']);
    }

    $daftar = daftarKontrakSebagai(User::factory()->pengelola()->create())->instance()->daftarKontrak;

    expect($daftar->perPage())->toBe(15)
        ->and($daftar->total())->toBe(17)
        ->and($daftar->count())->toBe(15);
});

it('F-07, F-08: pemilik melihat daftar dan bagian akan berakhir tanpa tombol buat, perpanjang, dan akhiri', function () {
    buatKontrakUji('01', 'Anisa Putri', ['tanggal_berakhir' => '2026-11-20']);

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/kontrak')
        ->assertOk()
        ->assertSee('Anisa Putri')
        ->assertSee('Segera Berakhir')
        ->assertSee('9 Hari Lagi')
        ->assertDontSee('data-test="buat-kontrak"', false)
        ->assertDontSeeHtml('>Perpanjang</a>')
        ->assertDontSeeHtml('>Akhiri</button>')
        ->assertDontSee('data-test="akhiri-kontrak"', false);
});

it('F-07: pengelola melihat aksi perpanjang dan akhiri pada kontrak aktif saja', function () {
    buatKontrakUji('01', 'Anisa Putri', ['tanggal_berakhir' => '2027-06-30']);
    buatKontrakUji('02', 'Penghuni Lama', ['status_kontrak' => StatusKontrak::Berakhir, 'tanggal_mulai' => '2025-01-01', 'tanggal_berakhir' => '2025-12-31']);

    $semua = daftarKontrakSebagai(User::factory()->pengelola()->create(), ['status' => 'semua']);

    // Satu baris aktif: tabel desktop dan kartu ponsel masing-masing memuat satu tombol Akhiri.
    expect(substr_count($semua->html(), 'wire:click="konfirmasiAkhiri('))->toBe(2);
});

it('F-07: penghuni dan vendor mendapat 403 di /kontrak', function (string $peran) {
    buatKontrakUji('01', 'Anisa Putri');

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/kontrak')
        ->assertForbidden()
        ->assertDontSee('Anisa Putri');

    Livewire::actingAs(User::factory()->{$peran}()->create())
        ->test('pages::kontrak.index')
        ->assertForbidden();
})->with(['penghuni', 'vendor']);
