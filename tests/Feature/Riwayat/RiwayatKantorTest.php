<?php

use App\Enums\StatusTagihan;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    // Baris sinkronisasi menunggu tidak boleh dikirim ke Odoo saat tes.
    Queue::fake();
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function halamanRiwayatKantor(string $peran = 'pengelola', array $query = [])
{
    return Livewire::withQueryParams($query)
        ->actingAs(User::factory()->{$peran}()->create())
        ->test('pages::riwayat-transaksi.index');
}

/**
 * Data campuran: Anisa (kamar 01) dan Rina (kamar 02) membayar Okt dan Nov; Dina (kamar 03) sudah keluar.
 *
 * @return array<string, Pembayaran>
 */
function dataRiwayatKantor(): array
{
    $anisa = buatKontrakUji('01', 'Anisa Putri');
    $rina = buatKontrakUji('02', 'Rina Maharani');
    $dina = buatKontrakUji('03', 'Dina Pratiwi');
    $dina->penghuni->forceFill(['status_penghuni' => 'nonaktif'])->save();
    $budi = User::factory()->pengelola()->create(['nama' => 'Budi Pengelola']);

    return [
        'anisa_nov' => buatPembayaranDiterima($anisa, '2026-11-01', '2026-10-31', '2026-11-01 09:10', 1250000, $budi, 'gagal'),
        'anisa_okt' => buatPembayaranDiterima($anisa, '2026-10-01', '2026-10-08', '2026-10-08 20:05', 1250000, $budi, 'terkirim'),
        'rina_nov' => buatPembayaranDiterima($rina, '2026-11-01', '2026-11-02', '2026-11-02 07:48', 1300000, $budi, 'menunggu'),
        'rina_okt' => buatPembayaranDiterima($rina, '2026-10-01', '2026-10-09', '2026-10-09 07:48', 1300000, $budi, 'terkirim'),
        'dina_okt' => buatPembayaranDiterima($dina, '2026-10-01', '2026-10-05', '2026-10-06 10:00', 1400000, $budi, null),
    ];
}

it('F-14: pengelola dan pemilik melihat semua pembayaran diterima dengan nama, kamar, dan oleh pengelola', function (string $peran) {
    dataRiwayatKantor();
    $menunggu = Tagihan::factory()->create(['nominal' => 7777000, 'status_tagihan' => StatusTagihan::MenungguVerifikasi]);
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $menunggu->id]);
    $ditolak = Tagihan::factory()->create(['nominal' => 8888000]);
    Pembayaran::factory()->ditolak()->create(['tagihan_id' => $ditolak->id]);

    $komponen = halamanRiwayatKantor($peran);

    expect($komponen->instance()->halamanKantor->total())->toBe(5);

    $komponen->assertSee('Riwayat')
        ->assertDontSee('Pembayaran yang sudah lunas, termasuk milik penghuni yang sudah keluar.')
        ->assertSee('Anisa Putri')
        ->assertSee('Rina Maharani')
        ->assertSee('Dina Pratiwi')
        ->assertSee('oleh Budi Pengelola')
        ->assertSee('1 Nov, 09.10')
        ->assertSee('31 Okt')
        ->assertRingkasan('jumlah', '5')
        ->assertSee('Rp6.500.000')
        ->assertDontSee('Rp7.777.000')
        ->assertDontSee('Rp8.888.000');
})->with(['pengelola', 'pemilik']);

it('F-14: urutan periode menurun lalu nomor kamar naik', function () {
    dataRiwayatKantor();

    $urutan = halamanRiwayatKantor()->instance()->halamanKantor
        ->map(fn ($b) => $b->tagihan->periode->format('Y-m').'/'.$b->tagihan->kontrak->kamar->nomor_kamar)
        ->all();

    expect($urutan)->toBe(['2026-11/01', '2026-11/02', '2026-10/01', '2026-10/02', '2026-10/03']);
});

it('F-14 butir 2: filter penghuni lewat query string; ringkasan mengikuti filter', function () {
    $data = dataRiwayatKantor();
    $idRina = $data['rina_nov']->tagihan->kontrak->penghuni_id;

    $komponen = halamanRiwayatKantor(query: ['penghuni' => (string) $idRina])
        ->assertRingkasan('jumlah', '2')
        ->assertSee('Rp2.600.000');

    expect($komponen->instance()->halamanKantor->map(fn ($b) => $b->tagihan->kontrak->penghuni_id)->unique()->all())->toBe([$idRina])
        ->and($komponen->instance()->halamanKantor->total())->toBe(2);
});

it('F-14 butir 2: filter periode lewat query string', function () {
    dataRiwayatKantor();

    $november = halamanRiwayatKantor(query: ['periode' => '2026-11'])
        ->assertRingkasan('jumlah', '2')
        ->assertSee('Rp2.550.000');
    expect($november->instance()->halamanKantor->map(fn ($b) => $b->tagihan->periode->format('Y-m'))->unique()->all())->toBe(['2026-11']);

    $oktober = halamanRiwayatKantor(query: ['periode' => '2026-10'])
        ->assertRingkasan('jumlah', '3')
        ->assertSee('Rp3.950.000');
    expect($oktober->instance()->halamanKantor->map(fn ($b) => $b->tagihan->kontrak->kamar->nomor_kamar)->all())->toBe(['01', '02', '03']);
});

it('F-14 butir 2: filter penghuni dan periode digabung', function () {
    $data = dataRiwayatKantor();

    halamanRiwayatKantor(query: ['penghuni' => (string) $data['anisa_okt']->tagihan->kontrak->penghuni_id, 'periode' => '2026-10'])
        ->assertRingkasan('jumlah', '1')
        ->assertSee('Rp1.250.000');
});

it('F-14: nilai filter tidak dikenal dianggap Semua', function (string $penghuni, string $periode) {
    dataRiwayatKantor();

    $komponen = halamanRiwayatKantor(query: ['penghuni' => $penghuni, 'periode' => $periode])
        ->assertRingkasan('jumlah', '5')
        ->assertSet('filterPenghuni', '')
        ->assertSet('filterPeriode', '');

    expect($komponen->instance()->halamanKantor->total())->toBe(5);
})->with([
    'id tidak ada' => ['999999', '2026-12'],
    'bukan angka dan format salah' => ['abc', 'november'],
    'periode tanpa pembayaran' => ['-1', '2020-01'],
]);

it('F-14: penghuni yang punya tagihan tetapi tanpa pembayaran diterima tidak ada di pilihan filter', function () {
    dataRiwayatKantor();
    $baru = buatKontrakUji('04', 'Fitri Baru');
    Tagihan::factory()->create(['kontrak_id' => $baru->id]);

    $nama = halamanRiwayatKantor()->instance()->opsiPenghuni->map(fn ($p) => $p->user->nama)->all();

    expect($nama)->toBe(['Anisa Putri', 'Dina Pratiwi', 'Rina Maharani']);
});

it('F-14 butir 4: penghuni yang sudah keluar tetap tampil dengan (sudah keluar) dan ada di pilihan filter', function () {
    $data = dataRiwayatKantor();

    $komponen = halamanRiwayatKantor()
        ->assertSee('Dina Pratiwi')
        ->assertSee('(sudah keluar)')
        ->assertSee('Dina Pratiwi (sudah keluar)');

    expect(substr_count($komponen->html(), '(sudah keluar)'))->toBeGreaterThanOrEqual(3);

    halamanRiwayatKantor(query: ['penghuni' => (string) $data['dina_okt']->tagihan->kontrak->penghuni_id])
        ->assertRingkasan('jumlah', '1')
        ->assertSee('Rp1.400.000');
});

it('F-14: kolom kamar memakai kamar kontrak tagihan itu, walau penghuni sudah pindah kamar', function () {
    $kamarLama = buatKontrakUji('05', 'Maya Sari');
    $kamarLama->forceFill(['status_kontrak' => 'berakhir', 'tanggal_berakhir' => '2026-09-30'])->save();
    $baru = Kontrak::factory()->create([
        'penghuni_id' => $kamarLama->penghuni_id,
        'kamar_id' => Kamar::factory()->create(['nomor_kamar' => '06'])->id,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-09-30',
    ]);
    $lama = buatPembayaranDiterima($kamarLama, '2026-09-01', '2026-09-08', '2026-09-09 10:00');
    $sekarang = buatPembayaranDiterima($baru, '2026-10-01', '2026-10-08', '2026-10-09 10:00');

    $komponen = halamanRiwayatKantor();
    $kamar = $komponen->instance()->halamanKantor->mapWithKeys(fn ($b) => [$b->id => $b->tagihan->kontrak->kamar->nomor_kamar]);

    expect($kamar[$lama->id])->toBe('05')->and($kamar[$sekarang->id])->toBe('06');
    $komponen->assertSee('Sep 2026, Kamar 05,')->assertSee('Okt 2026, Kamar 06,');
});

it('F-14 butir 3: status Odoo terkirim, menunggu, gagal, dan data migrasi', function () {
    $data = dataRiwayatKantor();

    $komponen = halamanRiwayatKantor()
        ->assertSee('Terkirim')
        ->assertSee('Menunggu')
        ->assertSee('Gagal')
        ->assertSee('Data Migrasi')
        ->assertSee('title="Riwayat lama, tidak dikirim ke Odoo"', false)
        ->assertSee('Odoo')
        ->assertDontSee('Terkirim ke Odoo')
        ->assertDontSee('Odoo gagal');

    // F-25: tautan membawa pengelola ke baris yang gagal, di tabel desktop dan kartu ponsel.
    $tautan = 'href="'.e(route('sinkronisasi-erp.index', [
        'status' => 'gagal',
        'ref' => $data['anisa_nov']->sinkronisasi->referensi_unik,
    ])).'"';

    expect(substr_count($komponen->html(), $tautan))->toBe(2)
        ->and($tautan)->toContain('status=gagal&amp;ref=KPK-PAY-')
        ->and(substr_count($komponen->html(), 'data-test="tautan-gagal"'))->toBe(2);
});

it('F-14 butir 3: pemilik melihat Gagal tanpa tautan ke Sinkronisasi ERP', function () {
    dataRiwayatKantor();

    $html = halamanRiwayatKantor('pemilik')->assertSee('Gagal')->html();

    expect($html)->not->toContain(route('sinkronisasi-erp.index'));
});

it('F-14: tanpa data migrasi pembayaran dengan sinkronisasi tidak diberi label Data migrasi', function () {
    $kontrak = buatKontrakUji('01', 'Anisa Putri');
    buatPembayaranDiterima($kontrak, '2026-10-01', '2026-10-08', '2026-10-09 10:00', sinkron: 'terkirim');

    halamanRiwayatKantor()->assertDontSee('Data Migrasi');
});

it('F-14: paginasi 20 per halaman, ringkasan dari seluruh hasil filter, dan halaman kembali ke 1 saat filter berubah', function () {
    $kontrak = buatKontrakUji('01', 'Anisa Putri');
    foreach (range(1, 25) as $n) {
        $p = Carbon::parse('2026-11-01')->subMonthsNoOverflow($n);
        buatPembayaranDiterima($kontrak, $p->toDateString(), $p->copy()->day(8)->toDateString(), $p->copy()->day(9)->toDateTimeString(), 1000000);
    }

    $komponen = halamanRiwayatKantor();

    expect($komponen->instance()->halamanKantor)->toHaveCount(20)
        ->and($komponen->instance()->halamanKantor->total())->toBe(25);
    $komponen->assertRingkasan('jumlah', '25')->assertSee('Rp25.000.000');

    $komponen->call('gotoPage', 2);
    expect($komponen->instance()->getPage())->toBe(2)
        ->and($komponen->instance()->halamanKantor)->toHaveCount(5);
    $komponen->assertRingkasan('jumlah', '25');

    $periode = Carbon::parse('2026-11-01')->subMonthsNoOverflow(3)->format('Y-m');
    $komponen->set('filterPeriode', $periode);

    expect($komponen->instance()->getPage())->toBe(1);
    $komponen->assertRingkasan('jumlah', '1')->assertSee('Rp1.000.000');
});

it('F-14: kosong tanpa pembayaran diterima, dan kosong karena filter', function () {
    halamanRiwayatKantor()
        ->assertSee('Belum ada pembayaran yang diterima.')
        ->assertRingkasan('jumlah', '0')
        ->assertSee('Semua');

    $data = dataRiwayatKantor();

    // Penghuni dan periode valid, tetapi tidak berpotongan: Dina tidak membayar November.
    halamanRiwayatKantor(query: ['penghuni' => (string) $data['dina_okt']->tagihan->kontrak->penghuni_id, 'periode' => '2026-11'])
        ->assertSee('Tidak ada pembayaran untuk filter ini.')
        ->assertRingkasan('jumlah', '0')
        ->assertDontSee('Belum ada pembayaran yang diterima.');
});

it('F-14: halaman tidak menampilkan NIK atau telepon penghuni', function () {
    $data = dataRiwayatKantor();
    $penghuni = $data['anisa_nov']->tagihan->kontrak->penghuni;

    $html = halamanRiwayatKantor()->html();

    expect($html)->not->toContain($penghuni->no_telepon)->not->toContain($penghuni->no_identitas_enc);
});

it('F-14: jumlah query tidak bertambah menurut jumlah pembayaran (tanpa N+1)', function () {
    $pengelola = User::factory()->pengelola()->create();
    $hitung = function () use ($pengelola) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        Livewire::actingAs($pengelola)->test('pages::riwayat-transaksi.index');
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    $tambah = function (int $dari, int $sampai) {
        foreach (range($dari, $sampai) as $n) {
            $kontrak = buatKontrakUji(sprintf('%02d', $n), 'Penghuni '.$n);
            buatPembayaranDiterima($kontrak, '2026-10-01', '2026-10-08', '2026-10-09 10:00', sinkron: ['terkirim', 'gagal', 'menunggu', null][$n % 4]);
        }
    };

    $tambah(1, 2);
    $hitung();
    $sedikit = $hitung();
    $tambah(3, 14);

    expect($hitung())->toBe($sedikit);
});

it('F-14: vendor mendapat 403 dan tamu diarahkan ke login', function () {
    $this->actingAs(User::factory()->vendor()->create())->get('/riwayat-transaksi')->assertForbidden();
    Livewire::actingAs(User::factory()->vendor()->create())->test('pages::riwayat-transaksi.index')->assertForbidden();

    auth()->logout();
    $this->get('/riwayat-transaksi')->assertRedirect(route('login'));
});

it('F-14: halaman kantor tidak memuat aksi ubah', function () {
    dataRiwayatKantor();

    $html = halamanRiwayatKantor()->html();

    expect($html)->not->toContain('wire:click="terima')->not->toContain('wire:click="tolak');
});
