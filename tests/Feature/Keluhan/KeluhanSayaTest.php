<?php

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

function halamanKeluhanSaya(User $user)
{
    return Livewire::actingAs($user)->test('pages::keluhan-saya.index');
}

/**
 * Tiket $penghuni atas fasilitas $fasilitas, dengan baris riwayat pertamanya.
 */
function tiketUji(Penghuni $penghuni, Fasilitas $fasilitas, string $nomor, string $lapor, array $atribut = []): TiketKeluhan
{
    $waktu = Carbon::parse($lapor, 'Asia/Jakarta');

    $tiket = TiketKeluhan::factory()->create(array_merge([
        'nomor_tiket' => $nomor,
        'penghuni_id' => $penghuni->id,
        'fasilitas_id' => $fasilitas->id,
        'lokasi' => $fasilitas->kamar_id === null ? LokasiKeluhan::AreaBersama : LokasiKeluhan::Kamar,
        'tanggal_lapor' => $waktu,
    ], $atribut));

    RiwayatStatusTiket::factory()->perubahan(null, StatusTiket::Diajukan)->create([
        'tiket_id' => $tiket->id,
        'diubah_oleh' => $penghuni->user_id,
        'waktu_perubahan' => $waktu,
    ]);

    return $tiket;
}

it('F-18 butir 1 dan 3: penghuni hanya melihat tiketnya sendiri', function () {
    $a = buatKontrakUji('05', 'Maya Sari');
    $b = buatKontrakUji('06', 'Rina Maharani');
    $acA = Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $a->kamar_id]);
    $kranB = Fasilitas::factory()->create(['nama_fasilitas' => 'Kran wastafel', 'kamar_id' => $b->kamar_id]);

    tiketUji($a->penghuni, $acA, 'KLH-0001', '2026-11-03 07:10');
    tiketUji($b->penghuni, $kranB, 'KLH-0002', '2026-11-04 08:00');

    halamanKeluhanSaya($a->penghuni->user)
        ->assertSee('KLH-0001')
        ->assertSee('AC, Kamar 05')
        ->assertDontSee('KLH-0002')
        ->assertDontSee('Kran wastafel');

    halamanKeluhanSaya($b->penghuni->user)
        ->assertSee('KLH-0002')
        ->assertDontSee('KLH-0001');
});

it('F-18: tiket terbaru di atas dengan label lokasi kamar dan area bersama', function () {
    $a = buatKontrakUji('05', 'Maya Sari');
    $ac = Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $a->kamar_id]);
    $pompa = Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Pompa air']);

    tiketUji($a->penghuni, $ac, 'KLH-0001', '2026-10-20 07:10');
    tiketUji($a->penghuni, $pompa, 'KLH-0002', '2026-11-03 09:00');

    halamanKeluhanSaya($a->penghuni->user)
        ->assertSeeInOrder(['KLH-0002', 'Pompa air, Area Bersama', 'KLH-0001', 'AC, Kamar 05'])
        ->assertSee('3 Nov')
        ->assertSee('20 Okt');
});

it('F-18 butir 1: tiket selesai menampilkan riwayat berurutan dengan waktu, tanpa biaya dan vendor', function () {
    $a = buatKontrakUji('05', 'Maya Sari');
    $kran = Fasilitas::factory()->create(['nama_fasilitas' => 'Kran wastafel', 'kamar_id' => $a->kamar_id]);
    $vendor = Vendor::factory()->create(['nama_vendor' => 'CV Teknik Sentosa']);

    $tiket = tiketUji($a->penghuni, $kran, 'KLH-0008', '2026-10-20 07:10', [
        'vendor_id' => $vendor->id,
        'status_tiket' => StatusTiket::Selesai,
        'biaya_perbaikan' => 350000,
        'jenis_kerusakan' => 'Pipa bocor',
        'catatan_instruksi' => 'Bawa seal baru',
    ]);

    foreach ([
        [StatusTiket::Diajukan, StatusTiket::Ditugaskan, '2026-10-20 09:00'],
        [StatusTiket::Ditugaskan, StatusTiket::Dikerjakan, '2026-10-21 10:30'],
        [StatusTiket::Dikerjakan, StatusTiket::Selesai, '2026-10-21 12:05'],
    ] as [$sebelum, $sesudah, $waktu]) {
        RiwayatStatusTiket::factory()->perubahan($sebelum, $sesudah)->create([
            'tiket_id' => $tiket->id,
            'diubah_oleh' => $vendor->user_id,
            'waktu_perubahan' => Carbon::parse($waktu, 'Asia/Jakarta'),
        ]);
    }

    $halaman = halamanKeluhanSaya($a->penghuni->user)
        ->assertSee('Riwayat Status')
        ->assertSeeInOrder([
            'Diajukan', '20 Okt, 07.10',
            'Ditugaskan', '20 Okt, 09.00',
            'Dikerjakan', '21 Okt, 10.30',
            'Selesai', '21 Okt, 12.05',
        ])
        ->assertDontSee('CV Teknik Sentosa')
        ->assertDontSee('350.000')
        ->assertDontSee('Rp')
        ->assertDontSee('Pipa bocor')
        ->assertDontSee('Bawa seal baru');

    expect($halaman->html())->toContain('data-terisi="4"');
});

it('F-18: meter tiket mengikuti status dengan label Diajukan, Ditugaskan, Dikerjakan, dan Selesai', function (StatusTiket $status, int $terisi) {
    $a = buatKontrakUji('05', 'Maya Sari');
    $ac = Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $a->kamar_id]);
    tiketUji($a->penghuni, $ac, 'KLH-0001', '2026-11-03 07:10', [
        'status_tiket' => $status,
        'vendor_id' => $status === StatusTiket::Diajukan ? null : Vendor::factory()->create()->id,
        'biaya_perbaikan' => $status === StatusTiket::Selesai ? 100000 : null,
    ]);

    halamanKeluhanSaya($a->penghuni->user)
        ->assertSee($status->label())
        ->assertSeeHtml('data-terisi="'.$terisi.'"');
})->with([
    'diajukan' => [StatusTiket::Diajukan, 1],
    'ditugaskan' => [StatusTiket::Ditugaskan, 2],
    'dikerjakan' => [StatusTiket::Dikerjakan, 3],
    'selesai' => [StatusTiket::Selesai, 4],
]);

it('F-16: flash nomor tiket tampil sekali dan kartunya bergaris Primary', function () {
    $a = buatKontrakUji('05', 'Maya Sari');
    $ac = Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $a->kamar_id]);
    tiketUji($a->penghuni, $ac, 'KLH-0013', '2026-11-05 08:59');
    tiketUji($a->penghuni, $ac, 'KLH-0012', '2026-11-01 08:00');
    $user = $a->penghuni->user;

    $this->actingAs($user)
        ->withSession(['_flash' => ['old' => ['keluhan_baru'], 'new' => []], 'keluhan_baru' => 'KLH-0013'])
        ->get(route('keluhan-saya.index'))
        ->assertOk()
        ->assertSee('Keluhan terkirim dengan nomor')
        ->assertSee('<strong class="teks-badan-kuat">KLH-0013</strong>', false)
        ->assertSee('border-2 border-primary', false);

    $this->actingAs($user)->get(route('keluhan-saya.index'))
        ->assertOk()
        ->assertDontSee('Keluhan terkirim dengan nomor')
        ->assertDontSee('border-2 border-primary', false);
});

it('F-18: keadaan kosong menampilkan Belum ada keluhan dan tombol Ajukan', function () {
    $a = buatKontrakUji('05', 'Maya Sari');

    halamanKeluhanSaya($a->penghuni->user)
        ->assertSee('Belum ada keluhan.')
        ->assertSee('Ajukan')
        ->assertDontSee('Riwayat Status');
});

it('halaman keluhan memuat judul, tombol Ajukan, dan tanpa deskripsi di bawah judul', function () {
    $a = buatKontrakUji('05', 'Maya Sari');

    $this->actingAs($a->penghuni->user)->get(route('keluhan-saya.index'))
        ->assertOk()
        ->assertSee('<h1 class="teks-judul m-0 min-w-0">Keluhan</h1>', false)
        ->assertSee(route('keluhan-saya.ajukan'), false)
        ->assertDontSee('Laporkan kerusakan');
});

it('penghuni tanpa baris penghuni melihat pesan belum dilengkapi tanpa tombol Ajukan', function () {
    $user = User::factory()->penghuni()->create();

    $this->actingAs($user)->get(route('keluhan-saya.index'))
        ->assertOk()
        ->assertSee('Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.')
        ->assertDontSee(route('keluhan-saya.ajukan'), false);
});

it('F-18: daftar dipaginasi 10 tiket per halaman', function () {
    $a = buatKontrakUji('05', 'Maya Sari');
    $ac = Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $a->kamar_id]);

    foreach (range(1, 12) as $n) {
        tiketUji($a->penghuni, $ac, sprintf('KLH-%04d', $n), Carbon::parse('2026-10-01 08:00', 'Asia/Jakarta')->addDays($n)->toDateTimeString());
    }

    halamanKeluhanSaya($a->penghuni->user)
        ->assertSee('KLH-0012')
        ->assertSee('KLH-0003')
        ->assertDontSee('KLH-0002')
        ->assertDontSee('KLH-0001');
});

it('F-18: jumlah query tetap untuk 1 dan 10 tiket (tanpa N+1)', function () {
    $a = buatKontrakUji('05', 'Maya Sari');
    // Tiket pertama pada fasilitas kamar, supaya relasi kamar ikut dimuat sejak hitungan awal.
    $pompa = Fasilitas::factory()->create(['nama_fasilitas' => 'Pompa air', 'kamar_id' => $a->kamar_id]);
    $user = $a->penghuni->user;

    $hitung = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        halamanKeluhanSaya($user);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    // Pemanasan agar cache di dalam proses (misalnya skema) tidak ikut terhitung.
    tiketUji($a->penghuni, $pompa, 'KLH-0001', '2026-11-01 08:00');
    $hitung();
    $satu = $hitung();

    foreach (range(2, 10) as $n) {
        $fasilitas = Fasilitas::factory()->create(['nama_fasilitas' => "Fasilitas {$n}", 'kamar_id' => $a->kamar_id]);
        $tiket = tiketUji($a->penghuni, $fasilitas, sprintf('KLH-%04d', $n), '2026-11-02 08:00');

        foreach ([StatusTiket::Ditugaskan, StatusTiket::Dikerjakan] as $status) {
            RiwayatStatusTiket::factory()->perubahan(null, $status)->create(['tiket_id' => $tiket->id, 'diubah_oleh' => $user->id]);
        }
    }

    expect($hitung())->toBe($satu);
});

it('Format::tanggalJam memakai zona Asia/Jakarta', function () {
    expect(Format::tanggalJam(Carbon::parse('2026-10-20 00:10:00', 'UTC')))->toBe('20 Okt, 07.10')
        ->and(Format::tanggalJam(Carbon::parse('2026-10-20 17:30:00', 'UTC')))->toBe('21 Okt, 00.30');
});
