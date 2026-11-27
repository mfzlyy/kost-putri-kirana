<?php

use App\Enums\StatusTiket;
use App\Exceptions\TiketSudahDitugaskan;
use App\Exceptions\VendorTidakAktif;
use App\Models\Fasilitas;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Format;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

function tiketDiajukanUji(string $nomor = 'KLH-0001', string $lapor = '2026-11-03 07:10', array $atribut = []): TiketKeluhan
{
    $penghuni = Penghuni::factory()->create();
    $fasilitas = Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Pompa air']);

    $tiket = TiketKeluhan::factory()->create(array_merge([
        'nomor_tiket' => $nomor,
        'penghuni_id' => $penghuni->id,
        'fasilitas_id' => $fasilitas->id,
        'lokasi' => 'area_bersama',
        'tanggal_lapor' => Carbon::parse($lapor, 'Asia/Jakarta'),
        'jenis_kerusakan' => null,
    ], $atribut));

    RiwayatStatusTiket::factory()->perubahan(null, StatusTiket::Diajukan)->create([
        'tiket_id' => $tiket->id,
        'diubah_oleh' => $penghuni->user_id,
        'waktu_perubahan' => $tiket->tanggal_lapor,
    ]);

    return $tiket;
}

function halamanTugaskan(User $user, ?TiketKeluhan $tiket = null)
{
    return Livewire::actingAs($user)->test('pages::tiket-keluhan.index', ['tiket' => (string) $tiket?->id]);
}

it('F-17: tugaskanVendor mengubah tiket menjadi ditugaskan dan mencatat riwayat', function () {
    $tiket = tiketDiajukanUji();
    $vendor = Vendor::factory()->create();
    $pengelola = User::factory()->pengelola()->create();

    $tiket->tugaskanVendor($vendor, '  Freon habis ', "  Cek freon\n", $pengelola);

    $tiket->refresh();
    expect($tiket->status_tiket)->toBe(StatusTiket::Ditugaskan)
        ->and($tiket->vendor_id)->toBe($vendor->id)
        ->and($tiket->jenis_kerusakan)->toBe('Freon habis')
        ->and($tiket->catatan_instruksi)->toBe('Cek freon');

    expect($tiket->riwayatStatus()->count())->toBe(2);

    $riwayat = $tiket->riwayatStatus()->get()->last();
    expect($riwayat->status_sebelum)->toBe(StatusTiket::Diajukan)
        ->and($riwayat->status_sesudah)->toBe(StatusTiket::Ditugaskan)
        ->and($riwayat->diubah_oleh)->toBe($pengelola->id)
        ->and($riwayat->waktu_perubahan->equalTo(now()))->toBeTrue();
});

it('F-17: tiket yang bukan diajukan ditolak dengan TiketSudahDitugaskan dan tidak berubah', function (StatusTiket $status) {
    $tiket = tiketDiajukanUji(atribut: ['status_tiket' => $status, 'vendor_id' => Vendor::factory()->create()->id, 'biaya_perbaikan' => $status === StatusTiket::Selesai ? 100000 : null]);
    $vendorBaru = Vendor::factory()->create();
    $vendorLama = $tiket->vendor_id;

    expect(fn () => $tiket->tugaskanVendor($vendorBaru, 'Bocor', 'Perbaiki', User::factory()->pengelola()->create()))
        ->toThrow(TiketSudahDitugaskan::class, 'Tiket ini sudah ditugaskan. Daftar sudah diperbarui.');

    expect($tiket->fresh()->vendor_id)->toBe($vendorLama)
        ->and($tiket->riwayatStatus()->count())->toBe(1);
})->with([StatusTiket::Ditugaskan, StatusTiket::Dikerjakan, StatusTiket::Selesai]);

it('F-17: penugasan dari salinan tiket yang usang tetap ditolak (dibaca ulang dengan kunci)', function () {
    $tiket = tiketDiajukanUji();
    $salinanUsang = TiketKeluhan::find($tiket->id);
    $pengelola = User::factory()->pengelola()->create();

    $tiket->tugaskanVendor(Vendor::factory()->create(), 'Bocor', 'Perbaiki', $pengelola);

    expect(fn () => $salinanUsang->tugaskanVendor(Vendor::factory()->create(), 'Lain', 'Lain', $pengelola))
        ->toThrow(TiketSudahDitugaskan::class);
});

it('F-17: akun vendor nonaktif ditolak dengan VendorTidakAktif tanpa mengubah tiket', function () {
    $tiket = tiketDiajukanUji();
    $vendor = Vendor::factory()->create(['user_id' => User::factory()->vendor()->nonaktif()]);

    expect(fn () => $tiket->tugaskanVendor($vendor, 'Bocor', 'Perbaiki', User::factory()->pengelola()->create()))
        ->toThrow(VendorTidakAktif::class, 'Akun vendor ini nonaktif. Pilih vendor lain.');

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Diajukan)
        ->and($tiket->fresh()->vendor_id)->toBeNull()
        ->and($tiket->riwayatStatus()->count())->toBe(1);
});

it('F-17: Vendor::aktif hanya akun vendor aktif, urut nama', function () {
    Vendor::factory()->create(['nama_vendor' => 'Toko Perabot Jaya']);
    Vendor::factory()->create(['nama_vendor' => 'CV Listrik Mandiri']);
    Vendor::factory()->create(['nama_vendor' => 'PT Nonaktif', 'user_id' => User::factory()->vendor()->nonaktif()]);

    expect(Vendor::aktif()->pluck('nama_vendor')->all())->toBe(['CV Listrik Mandiri', 'Toko Perabot Jaya']);
});

it('F-17: pengelola menugaskan lewat halaman (layar sempit): toast tampil, form kosong, tiket tetap terpilih dengan data penugasan', function () {
    $tiket = tiketDiajukanUji('KLH-0012');
    $vendor = Vendor::factory()->create(['nama_vendor' => 'CV Sejuk Abadi', 'jenis_layanan' => 'Pendingin ruangan']);
    $pengelola = User::factory()->pengelola()->create();

    halamanTugaskan($pengelola, $tiket)
        ->assertSee('Tugaskan Vendor')
        ->set('vendorId', (string) $vendor->id)
        ->set('jenisKerusakan', 'Freon habis')
        ->set('instruksi', "Cek freon\ndan bersihkan filter")
        ->call('tugaskan', $tiket->id)
        ->assertHasNoErrors()
        ->assertSet('vendorId', '')
        ->assertSet('jenisKerusakan', '')
        ->assertSet('instruksi', '')
        ->assertSet('tiket', (string) $tiket->id)
        ->assertSee('Freon habis')
        ->assertSee('CV Sejuk Abadi, Pendingin ruangan')
        ->assertDontSee('Tugaskan Vendor')
        ->assertDispatched('antrean-keluhan', jumlah: 0);

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Ditugaskan);
});

/** Mengisi form lalu menugaskan $tiket dari halaman desktop dengan filter $status. */
function tugaskanDiDesktop(TiketKeluhan $tiket, string $status)
{
    return Livewire::withQueryParams(['status' => $status])
        ->actingAs(User::factory()->pengelola()->create())
        ->test('pages::tiket-keluhan.index', ['tiket' => (string) $tiket->id])
        ->set('layarLebar', true)
        ->set('vendorId', (string) Vendor::factory()->create()->id)
        ->set('jenisKerusakan', 'Bocor')
        ->set('instruksi', 'Perbaiki')
        ->call('tugaskan', $tiket->id)
        ->assertHasNoErrors();
}

it('20D: setelah Tugaskan di desktop, panel menampilkan tiket teratas berikutnya di daftar yang disaring', function (string $status) {
    $pertama = tiketDiajukanUji('KLH-0001', '2026-11-01 07:00');
    $kedua = tiketDiajukanUji('KLH-0002', '2026-11-02 07:00');

    tugaskanDiDesktop($pertama, $status)
        ->assertSet('tiket', (string) $kedua->id)
        ->assertSeeHtml('wire:key="panel-'.$kedua->id.'"')
        ->assertSeeHtml('data-test="form-tugaskan"')
        ->assertSet('jenisKerusakan', '');
})->with(['perlu', 'diajukan', 'semua']);

it('20D: setelah tiket diajukan terakhir ditugaskan, panel tertutup dan daftar menampilkan keadaan kosong', function () {
    $tiket = tiketDiajukanUji('KLH-0001');

    tugaskanDiDesktop($tiket, 'diajukan')
        ->assertSet('tiket', '')
        ->assertDontSeeHtml('data-test="panel-tiket"')
        ->assertSee('Tidak ada tiket yang sesuai filter.');

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Ditugaskan);
});

it('20D: bila tidak ada tiket lain di daftar, panel tertutup dan tiket yang ditugaskan tetap di daftar', function () {
    $tiket = tiketDiajukanUji('KLH-0001');

    tugaskanDiDesktop($tiket, 'perlu')
        ->assertSet('tiket', '')
        ->assertDontSeeHtml('data-test="panel-tiket"')
        ->assertSeeHtml('data-test="item-tiket"');
});

it('20D: halaman daftar yang menjadi kosong setelah Tugaskan mundur ke halaman terakhir', function () {
    foreach (range(1, 21) as $n) {
        $tiket = tiketDiajukanUji(sprintf('KLH-%04d', $n), '2026-11-01 07:'.str_pad((string) $n, 2, '0', STR_PAD_LEFT));
    }

    $komponen = Livewire::withQueryParams(['status' => 'diajukan', 'page' => 2])
        ->actingAs(User::factory()->pengelola()->create())
        ->test('pages::tiket-keluhan.index', ['tiket' => (string) $tiket->id])
        ->set('layarLebar', true)
        ->set('vendorId', (string) Vendor::factory()->create()->id)
        ->set('jenisKerusakan', 'Bocor')
        ->set('instruksi', 'Perbaiki')
        ->call('tugaskan', $tiket->id);

    expect($komponen->instance()->halaman->currentPage())->toBe(1)
        ->and($komponen->instance()->dipilih->nomor_tiket)->toBe('KLH-0001');
});

it('F-17: validasi form memakai pesan Indonesia', function () {
    $tiket = tiketDiajukanUji();
    $pengelola = User::factory()->pengelola()->create();

    halamanTugaskan($pengelola, $tiket)
        ->call('tugaskan', $tiket->id)
        ->assertHasErrors(['vendorId' => 'required', 'jenisKerusakan' => 'required', 'instruksi' => 'required'])
        ->assertSee('Pilih vendor.')
        ->assertSee('Isi jenis kerusakan.')
        ->assertSee('Isi instruksi untuk vendor.');

    $vendor = Vendor::factory()->create();

    halamanTugaskan($pengelola, $tiket)
        ->set('vendorId', (string) $vendor->id)
        ->set('jenisKerusakan', str_repeat('a', 101))
        ->set('instruksi', str_repeat('b', 1001))
        ->call('tugaskan', $tiket->id)
        ->assertSee('Jenis kerusakan paling banyak 100 karakter.')
        ->assertSee('Instruksi paling banyak 1.000 karakter.');

    halamanTugaskan($pengelola, $tiket)
        ->set('vendorId', (string) $vendor->id)
        ->set('jenisKerusakan', str_repeat('a', 100))
        ->set('instruksi', str_repeat('b', 1000))
        ->call('tugaskan', $tiket->id)
        ->assertHasNoErrors();
});

it('F-17: isian spasi saja dianggap kosong', function () {
    $tiket = tiketDiajukanUji();

    halamanTugaskan(User::factory()->pengelola()->create(), $tiket)
        ->set('vendorId', (string) Vendor::factory()->create()->id)
        ->set('jenisKerusakan', '   ')
        ->set('instruksi', "  \n ")
        ->call('tugaskan', $tiket->id)
        ->assertHasErrors(['jenisKerusakan' => 'required', 'instruksi' => 'required']);

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Diajukan);
});

it('F-17: vendor nonaktif, bukan vendor, atau id ngawur ditolak di server sebagai tidak ditemukan', function (string $kasus) {
    $tiket = tiketDiajukanUji();

    $id = match ($kasus) {
        'nonaktif' => (string) Vendor::factory()->create(['user_id' => User::factory()->vendor()->nonaktif()])->id,
        'tidak ada' => '999999',
        'teks' => 'abc',
    };

    halamanTugaskan(User::factory()->pengelola()->create(), $tiket)
        ->set('vendorId', $id)
        ->set('jenisKerusakan', 'Bocor')
        ->set('instruksi', 'Perbaiki')
        ->call('tugaskan', $tiket->id)
        ->assertHasErrors('vendorId')
        ->assertSee('Vendor tidak ditemukan.');

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Diajukan);
})->with(['nonaktif', 'tidak ada', 'teks']);

it('F-17: tiket yang sudah ditugaskan lewat tab lain menghasilkan toast peringatan tanpa mengubah data', function () {
    $tiket = tiketDiajukanUji();
    $vendorA = Vendor::factory()->create();
    $vendorB = Vendor::factory()->create();
    $pengelola = User::factory()->pengelola()->create();

    $komponen = halamanTugaskan($pengelola, $tiket)
        ->set('vendorId', (string) $vendorB->id)
        ->set('jenisKerusakan', 'Bocor')
        ->set('instruksi', 'Perbaiki');

    $tiket->tugaskanVendor($vendorA, 'Lebih dulu', 'Oleh pengelola lain', $pengelola);

    $komponen->call('tugaskan', $tiket->id)->assertHasNoErrors();

    expect($tiket->fresh()->vendor_id)->toBe($vendorA->id)
        ->and($tiket->fresh()->jenis_kerusakan)->toBe('Lebih dulu')
        ->and($tiket->riwayatStatus()->count())->toBe(2);
});

it('F-17 butir 3: pemilik tidak mendapat form dan aksi tugaskan ditolak Policy', function () {
    $tiket = tiketDiajukanUji();
    $vendor = Vendor::factory()->create();
    $pemilik = User::factory()->pemilik()->create();

    $halaman = halamanTugaskan($pemilik, $tiket)
        ->assertSee('KLH-0001')
        ->assertDontSee('Tugaskan Vendor')
        ->assertDontSeeHtml('data-test="form-tugaskan"');

    $halaman->set('vendorId', (string) $vendor->id)
        ->set('jenisKerusakan', 'Bocor')
        ->set('instruksi', 'Perbaiki')
        ->call('tugaskan', $tiket->id)
        ->assertForbidden();

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Diajukan);
});

it('F-17: tanpa vendor aktif tampil teks kosong dengan tautan Akun dan tombol nonaktif', function () {
    $tiket = tiketDiajukanUji();

    $html = halamanTugaskan(User::factory()->pengelola()->create(), $tiket)
        ->assertSee('Belum ada vendor aktif.')
        ->assertSeeHtml('href="'.route('akun.index').'"')
        ->html();

    expect($html)->toMatch('/<button[^>]*\sdisabled[^>]*data-test="tombol-tugaskan"/s');
});

it('F-17: form tidak muncul untuk tiket yang sudah ditugaskan', function () {
    $tiket = tiketDiajukanUji(atribut: ['status_tiket' => StatusTiket::Ditugaskan, 'vendor_id' => Vendor::factory()->create(['nama_vendor' => 'CV Sejuk Abadi'])->id, 'jenis_kerusakan' => 'Freon habis', 'catatan_instruksi' => 'Cek freon']);

    halamanTugaskan(User::factory()->pengelola()->create(), $tiket)
        ->assertDontSee('Tugaskan Vendor')
        ->assertSee('CV Sejuk Abadi')
        ->assertSee('Freon habis')
        ->assertSee('Cek freon');
});

it('F-17: durasiPenanganan dihitung dari riwayat diajukan sampai selesai, null bila belum selesai', function () {
    $tiket = tiketDiajukanUji(lapor: '2026-11-01 07:00');

    expect($tiket->durasiPenanganan())->toBeNull();

    $pengelola = User::factory()->pengelola()->create();
    RiwayatStatusTiket::factory()->perubahan(StatusTiket::Dikerjakan, StatusTiket::Selesai)->create([
        'tiket_id' => $tiket->id,
        'diubah_oleh' => $pengelola->id,
        'waktu_perubahan' => Carbon::parse('2026-11-02 12:00', 'Asia/Jakarta'),
    ]);

    $durasi = $tiket->fresh()->durasiPenanganan();

    expect($durasi)->not->toBeNull()
        ->and(Format::durasi($durasi))->toBe('1 hari 5 jam');
});

it('format durasi: jam saja, hari saja, kurang dari satu jam', function () {
    $mulai = CarbonImmutable::parse('2026-11-01 07:00');

    expect(Format::durasi($mulai->diff($mulai->addHours(5))))->toBe('5 jam')
        ->and(Format::durasi($mulai->diff($mulai->addDays(3))))->toBe('3 hari')
        ->and(Format::durasi($mulai->diff($mulai->addMinutes(20))))->toBe('Kurang dari 1 jam');
});

it('penugasan tercatat atomik: kegagalan riwayat membatalkan perubahan tiket', function () {
    $tiket = tiketDiajukanUji();
    $vendor = Vendor::factory()->create();

    DB::statement('ALTER TABLE riwayat_status_tiket ADD CONSTRAINT paksa_gagal CHECK (status_sesudah <> \'ditugaskan\')');

    try {
        expect(fn () => $tiket->tugaskanVendor($vendor, 'Bocor', 'Perbaiki', User::factory()->pengelola()->create()))
            ->toThrow(QueryException::class);
    } finally {
        DB::statement('ALTER TABLE riwayat_status_tiket DROP CONSTRAINT IF EXISTS paksa_gagal');
    }

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Diajukan)
        ->and($tiket->fresh()->vendor_id)->toBeNull();
});

it('F-17: penghuni dan vendor mendapat 403 di /tiket-keluhan, tamu diarahkan ke login', function () {
    foreach (['penghuni', 'vendor'] as $peran) {
        $this->actingAs(User::factory()->{$peran}()->create())->get('/tiket-keluhan')->assertForbidden();
    }

    auth()->logout();
    $this->get('/tiket-keluhan')->assertRedirect(route('login'));
});

it('F-17: Policy tugaskan hanya mengizinkan pengelola', function () {
    $tiket = tiketDiajukanUji();

    expect(User::factory()->pengelola()->create()->can('tugaskan', $tiket))->toBeTrue()
        ->and(User::factory()->pemilik()->create()->can('tugaskan', $tiket))->toBeFalse()
        ->and(User::factory()->penghuni()->create()->can('tugaskan', $tiket))->toBeFalse()
        ->and(User::factory()->vendor()->create()->can('tugaskan', $tiket))->toBeFalse();
});

it('Bagian D: setelah penugasan angka antrean di menu diperbarui lewat event dengan jumlah tiket diajukan tersisa', function () {
    $tiket = tiketDiajukanUji('KLH-0001');
    tiketDiajukanUji('KLH-0002');

    halamanTugaskan(User::factory()->pengelola()->create(), $tiket)
        ->set('vendorId', (string) Vendor::factory()->create()->id)
        ->set('jenisKerusakan', 'Bocor')
        ->set('instruksi', 'Perbaiki')
        ->call('tugaskan', $tiket->id)
        ->assertDispatched('antrean-keluhan', jumlah: 1);
});
