<?php

use App\Enums\LokasiKeluhan;
use App\Enums\StatusKontrak;
use App\Enums\StatusTiket;
use App\Exceptions\LokasiKeluhanTidakValid;
use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\TiketKeluhan;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

/**
 * Penghuni berkontrak aktif di kamar $nomor, lengkap dengan dua fasilitas kamarnya.
 *
 * @return array{user: User, penghuni: Penghuni, kamar: Kamar, ac: Fasilitas, kasur: Fasilitas}
 */
function penghuniKeluhan(string $nomor = '05', string $nama = 'Maya Sari'): array
{
    $kontrak = buatKontrakUji($nomor, $nama);

    return [
        'user' => $kontrak->penghuni->user,
        'penghuni' => $kontrak->penghuni,
        'kamar' => $kontrak->kamar,
        'ac' => Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $kontrak->kamar_id]),
        'kasur' => Fasilitas::factory()->create(['nama_fasilitas' => 'Kasur', 'kamar_id' => $kontrak->kamar_id]),
    ];
}

function halamanAjukan(User $user)
{
    return Livewire::actingAs($user)->test('pages::keluhan-saya.ajukan');
}

it('F-16: penghuni berkontrak mengajukan keluhan kamar dan mendapat nomor tiket', function () {
    $p = penghuniKeluhan();
    $lain = penghuniKeluhan('06', 'Rina Maharani');

    halamanAjukan($p['user'])
        ->assertSet('lokasi', 'kamar')
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', '  AC tidak dingin sejak kemarin malam  ')
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertRedirect(route('keluhan-saya.index'));

    $tiket = TiketKeluhan::query()->sole();

    expect($tiket->nomor_tiket)->toBe('KLH-0001')
        ->and($tiket->penghuni_id)->toBe($p['penghuni']->id)
        ->and($tiket->penghuni_id)->not->toBe($lain['penghuni']->id)
        ->and($tiket->fasilitas_id)->toBe($p['ac']->id)
        ->and($tiket->lokasi)->toBe(LokasiKeluhan::Kamar)
        ->and($tiket->deskripsi)->toBe('AC tidak dingin sejak kemarin malam')
        ->and($tiket->status_tiket)->toBe(StatusTiket::Diajukan)
        ->and($tiket->vendor_id)->toBeNull()
        ->and($tiket->jenis_kerusakan)->toBeNull()
        ->and($tiket->catatan_instruksi)->toBeNull()
        ->and($tiket->biaya_perbaikan)->toBeNull()
        ->and($tiket->tanggal_lapor->equalTo(now()))->toBeTrue()
        ->and(session('keluhan_baru'))->toBe('KLH-0001');

    $riwayat = RiwayatStatusTiket::query()->sole();

    expect($riwayat->tiket_id)->toBe($tiket->id)
        ->and($riwayat->status_sebelum)->toBeNull()
        ->and($riwayat->status_sesudah)->toBe(StatusTiket::Diajukan)
        ->and($riwayat->diubah_oleh)->toBe($p['user']->id)
        ->and($riwayat->waktu_perubahan->equalTo($tiket->tanggal_lapor))->toBeTrue();
});

it('F-16: keluhan area bersama dengan fasilitas tanpa kamar berhasil', function () {
    $p = penghuniKeluhan();
    $pompa = Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Pompa air']);

    halamanAjukan($p['user'])
        ->set('lokasi', 'area_bersama')
        ->set('fasilitas_id', (string) $pompa->id)
        ->set('deskripsi', 'Pompa berbunyi keras.')
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertRedirect(route('keluhan-saya.index'));

    expect(TiketKeluhan::query()->sole())
        ->lokasi->toBe(LokasiKeluhan::AreaBersama)
        ->fasilitas_id->toBe($pompa->id);
});

it('F-16: mengganti lokasi mengosongkan pilihan fasilitas', function () {
    $p = penghuniKeluhan();

    halamanAjukan($p['user'])
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('lokasi', 'area_bersama')
        ->assertSet('fasilitas_id', '');
});

it('F-16: daftar fasilitas hanya milik lokasi terpilih', function () {
    $p = penghuniKeluhan();
    $lain = penghuniKeluhan('06', 'Rina Maharani');
    Fasilitas::factory()->create(['nama_fasilitas' => 'Lemari Kamar Enam', 'kamar_id' => $lain['kamar']->id]);
    Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Dapur Bersama Uji']);

    halamanAjukan($p['user'])
        ->assertSee('Kamar 05')
        ->assertSee('Area Bersama')
        ->assertSee('AC')
        ->assertSee('Kasur')
        ->assertDontSee('Lemari Kamar Enam')
        ->assertDontSee('Dapur Bersama Uji')
        ->set('lokasi', 'area_bersama')
        ->assertSee('Dapur Bersama Uji')
        ->assertDontSee('Kasur');
});

it('F-16: fasilitas kamar lain, fasilitas area untuk lokasi kamar, atau id yang tidak ada ditolak tanpa tiket', function (string $jenis) {
    $p = penghuniKeluhan();
    $lain = penghuniKeluhan('06', 'Rina Maharani');
    $lainnya = Fasilitas::factory()->create(['nama_fasilitas' => 'Lemari', 'kamar_id' => $lain['kamar']->id]);
    $area = Fasilitas::factory()->areaBersama()->create();

    $id = match ($jenis) {
        'kamar lain' => $lainnya->id,
        'area untuk kamar' => $area->id,
        'tidak ada' => 987654,
    };

    halamanAjukan($p['user'])
        ->set('fasilitas_id', (string) $id)
        ->set('deskripsi', 'Rusak.')
        ->call('kirim')
        ->assertHasErrors('fasilitas_id')
        ->assertSee('Fasilitas tidak ada di lokasi ini.')
        ->assertNoRedirect();

    expect(TiketKeluhan::query()->count())->toBe(0);
})->with(['kamar lain', 'area untuk kamar', 'tidak ada']);

it('F-16: fasilitas kamar untuk lokasi area bersama ditolak tanpa tiket', function () {
    $p = penghuniKeluhan();

    halamanAjukan($p['user'])
        ->set('lokasi', 'area_bersama')
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', 'Rusak.')
        ->call('kirim')
        ->assertSee('Fasilitas tidak ada di lokasi ini.');

    expect(TiketKeluhan::query()->count())->toBe(0);
});

it('F-16: penghuni tanpa kontrak aktif memilih Area Bersama dan tidak bisa memilih Kamar', function () {
    $user = User::factory()->penghuni()->create();
    Penghuni::factory()->create(['user_id' => $user->id]);
    $area = Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Mesin cuci']);

    halamanAjukan($user)
        ->assertSet('lokasi', 'area_bersama')
        ->assertSee('Belum ada kontrak aktif.')
        ->assertSeeHtml('value="kamar"')
        ->set('lokasi', 'kamar')
        ->set('fasilitas_id', (string) $area->id)
        ->set('deskripsi', 'Rusak.')
        ->call('kirim')
        ->assertHasErrors('lokasi')
        ->assertSee('Belum ada kontrak aktif. Pilih Area Bersama.')
        ->assertNoRedirect();

    expect(TiketKeluhan::query()->count())->toBe(0);
});

it('F-16: pilihan Kamar dinonaktifkan bila belum ada kontrak aktif', function () {
    $user = User::factory()->penghuni()->create();
    Penghuni::factory()->create(['user_id' => $user->id]);

    $html = halamanAjukan($user)->html();

    expect($html)->toMatch('/<input[^>]*value="kamar"[^>]*disabled/s');
});

it('F-16 butir 1: setiap isian wajib dan deskripsi paling banyak 1.000 karakter', function () {
    $p = penghuniKeluhan();

    halamanAjukan($p['user'])
        ->set('lokasi', '')
        ->set('fasilitas_id', '')
        ->set('deskripsi', '   ')
        ->call('kirim')
        ->assertHasErrors(['lokasi', 'fasilitas_id', 'deskripsi'])
        ->assertSee('Pilih lokasi.')
        ->assertSee('Pilih fasilitas yang rusak.')
        ->assertSee('Tulis deskripsi kerusakan.');

    halamanAjukan($p['user'])
        ->set('lokasi', 'ruang_tamu')
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', 'Rusak.')
        ->call('kirim')
        ->assertHasErrors('lokasi')
        ->assertSee('Pilih lokasi.');

    halamanAjukan($p['user'])
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', str_repeat('a', 1001))
        ->call('kirim')
        ->assertHasErrors('deskripsi')
        ->assertSee('Deskripsi paling banyak 1.000 karakter.');

    halamanAjukan($p['user'])
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', str_repeat('a', 1000))
        ->call('kirim')
        ->assertHasNoErrors();

    expect(TiketKeluhan::query()->count())->toBe(1);
});

it('F-16 butir 3: kirim dua kali dengan data sama tetap satu tiket, deskripsi berbeda menjadi dua tiket', function () {
    $p = penghuniKeluhan();

    $halaman = halamanAjukan($p['user'])
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', 'AC bocor.')
        ->call('kirim')
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertRedirect(route('keluhan-saya.index'));

    expect(TiketKeluhan::query()->count())->toBe(1)
        ->and(RiwayatStatusTiket::query()->count())->toBe(1)
        ->and(session('keluhan_baru'))->toBe('KLH-0001');

    $halaman->set('deskripsi', 'AC bocor parah.')->call('kirim');

    expect(TiketKeluhan::query()->count())->toBe(2);
});

it('F-16 butir 3: keluhan yang sama setelah lewat 60 detik dibuat sebagai tiket baru', function () {
    $p = penghuniKeluhan();
    $kirim = fn () => halamanAjukan($p['user'])
        ->set('fasilitas_id', (string) $p['ac']->id)
        ->set('deskripsi', 'AC bocor.')
        ->call('kirim');

    $kirim();
    Carbon::setTestNow(now()->addSeconds(30));
    $kirim();

    expect(TiketKeluhan::query()->count())->toBe(1);

    Carbon::setTestNow(now()->addSeconds(61));
    $kirim();

    expect(TiketKeluhan::query()->count())->toBe(2);
});

it('F-16: tiket kembar milik penghuni lain tidak dianggap ganda', function () {
    $a = penghuniKeluhan('05', 'Maya Sari');
    $b = penghuniKeluhan('06', 'Rina Maharani');

    foreach ([$a, $b] as $p) {
        halamanAjukan($p['user'])
            ->set('fasilitas_id', (string) $p['ac']->id)
            ->set('deskripsi', 'AC bocor.')
            ->call('kirim');
    }

    expect(TiketKeluhan::query()->count())->toBe(2);
});

it('F-16: model menolak fasilitas yang tidak cocok dengan lokasi', function () {
    $p = penghuniKeluhan();
    $lain = penghuniKeluhan('06', 'Rina Maharani');

    expect(fn () => TiketKeluhan::ajukanKeluhan($p['penghuni'], LokasiKeluhan::Kamar, $lain['ac'], 'Rusak.', $p['user']))
        ->toThrow(LokasiKeluhanTidakValid::class, 'Fasilitas tidak ada di lokasi ini.')
        ->and(fn () => TiketKeluhan::ajukanKeluhan($p['penghuni'], LokasiKeluhan::AreaBersama, $p['ac'], 'Rusak.', $p['user']))
        ->toThrow(LokasiKeluhanTidakValid::class, 'Fasilitas tidak ada di lokasi ini.');

    expect(TiketKeluhan::query()->count())->toBe(0)
        ->and(RiwayatStatusTiket::query()->count())->toBe(0);
});

it('F-16: model menolak lokasi kamar untuk penghuni tanpa kontrak aktif', function () {
    $user = User::factory()->penghuni()->create();
    $penghuni = Penghuni::factory()->create(['user_id' => $user->id]);
    $fasilitas = Fasilitas::factory()->create();

    expect(fn () => TiketKeluhan::ajukanKeluhan($penghuni, LokasiKeluhan::Kamar, $fasilitas, 'Rusak.', $user))
        ->toThrow(LokasiKeluhanTidakValid::class, 'Belum ada kontrak aktif. Pilih Area Bersama.');
});

it('F-16: kontrak yang sudah berakhir tidak membuka lokasi kamar', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $kontrak->forceFill(['status_kontrak' => StatusKontrak::Berakhir])->save();
    Fasilitas::factory()->create(['nama_fasilitas' => 'Kipas', 'kamar_id' => $kontrak->kamar_id]);

    halamanAjukan($kontrak->penghuni->user)
        ->assertSet('lokasi', 'area_bersama')
        ->assertDontSee('Kipas');
});

it('F-16: fasilitas kosong menampilkan pesan dan tombol Kirim nonaktif', function () {
    $user = User::factory()->penghuni()->create();
    Penghuni::factory()->create(['user_id' => $user->id]);

    $halaman = halamanAjukan($user)
        ->assertSee('Belum ada fasilitas di lokasi ini. Hubungi pengelola kos.');

    expect($halaman->html())->toMatch('/<button[^>]*type="submit"[^>]*disabled/s');
});

it('penghuni tanpa baris penghuni melihat pesan belum dilengkapi tanpa form', function () {
    $user = User::factory()->penghuni()->create();

    halamanAjukan($user)
        ->assertOk()
        ->assertSee('Ajukan Keluhan')
        ->assertSee('Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.')
        ->assertDontSee('Deskripsi');
});

it('penghuni berstatus nonaktif tidak boleh mengajukan keluhan', function () {
    $user = User::factory()->penghuni()->create();
    Penghuni::factory()->nonaktif()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('keluhan-saya.ajukan'))->assertForbidden();
    expect($user->can('create', TiketKeluhan::class))->toBeFalse();
});

it('TB-04: pengelola, pemilik, dan vendor mendapat 403 di halaman keluhan penghuni', function (string $peran, string $nama) {
    $user = User::factory()->{$peran}()->create();

    $this->actingAs($user)->get(route($nama))->assertForbidden();
})->with(function () {
    foreach (['pengelola', 'pemilik', 'vendor'] as $peran) {
        foreach (['keluhan-saya.index', 'keluhan-saya.ajukan'] as $nama) {
            yield "{$peran} {$nama}" => [$peran, $nama];
        }
    }
});

it('tamu diarahkan ke login dari halaman keluhan', function () {
    $this->get(route('keluhan-saya.index'))->assertRedirect(route('login'));
    $this->get(route('keluhan-saya.ajukan'))->assertRedirect(route('login'));
});

it('TiketKeluhanPolicy::create hanya untuk penghuni aktif', function () {
    $aktif = User::factory()->penghuni()->create();
    Penghuni::factory()->create(['user_id' => $aktif->id]);

    expect($aktif->can('create', TiketKeluhan::class))->toBeTrue()
        ->and(User::factory()->penghuni()->create()->can('create', TiketKeluhan::class))->toBeFalse()
        ->and(User::factory()->pengelola()->create()->can('create', TiketKeluhan::class))->toBeFalse()
        ->and(User::factory()->pemilik()->create()->can('create', TiketKeluhan::class))->toBeFalse()
        ->and(User::factory()->vendor()->create()->can('create', TiketKeluhan::class))->toBeFalse();
});

it('halaman ajukan di ponsel menyembunyikan tab bawah dan punya tombol kembali', function () {
    $p = penghuniKeluhan();

    $this->actingAs($p['user'])->get(route('keluhan-saya.ajukan'))
        ->assertOk()
        ->assertSee('Ajukan Keluhan')
        ->assertSee('aria-label="Kembali ke keluhan"', false)
        ->assertDontSee('aria-label="Menu penghuni"', false);

    $this->actingAs($p['user'])->get(route('keluhan-saya.index'))
        ->assertSee('aria-label="Menu penghuni"', false);
});
