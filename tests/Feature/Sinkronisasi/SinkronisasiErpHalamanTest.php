<?php

use App\Enums\JenisTransaksiErp;
use App\Models\Fasilitas;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:40:00', 'Asia/Jakarta'));
    Queue::fake();
    PengaturanOperasional::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

function halamanSinkronisasi(array $query = [], ?User $user = null): Testable
{
    return Livewire::withQueryParams($query)
        ->actingAs($user ?? User::factory()->pengelola()->create())
        ->test('pages::sinkronisasi-erp.index');
}

/**
 * Baris sinkronisasi pembayaran milik penghuni baru di kamar $kamar. $dicoba null berarti belum pernah dicoba.
 * Dibuat tanpa event supaya tidak ada job pengirim.
 */
function barisHalamanBayar(string $kamar, string $status = 'gagal', ?string $dicoba = '2026-11-05 08:00', string $nama = 'Rina Maharani', array $isi = []): SinkronisasiErp
{
    return SinkronisasiErp::withoutEvents(function () use ($kamar, $status, $dicoba, $nama, $isi) {
        $kontrak = buatKontrakUji($kamar, $nama);
        $baris = buatPembayaranDiterima($kontrak, '2026-11-01', '2026-11-03', '2026-11-04 09:00', 1250000, sinkron: $status)->sinkronisasi;
        $baris->forceFill(array_merge([
            'waktu_kirim' => $dicoba === null ? null : Carbon::parse($dicoba, 'Asia/Jakarta'),
        ], $isi))->save();

        return $baris;
    });
}

/**
 * Baris sinkronisasi tiket selesai $nomor untuk fasilitas AC kamar $kamar (null berarti pompa air area bersama).
 */
function barisHalamanTiket(string $nomor, JenisTransaksiErp $jenis, string $status = 'gagal', ?string $kamar = '03'): SinkronisasiErp
{
    return SinkronisasiErp::withoutEvents(function () use ($nomor, $jenis, $status, $kamar) {
        $fasilitas = $kamar === null
            ? Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Pompa air'])
            : Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => buatKontrakUji($kamar, 'Maya Sari')->kamar_id]);
        $tiket = TiketKeluhan::factory()->selesai()->create([
            'nomor_tiket' => $nomor,
            'fasilitas_id' => $fasilitas->id,
            'biaya_perbaikan' => 150000,
        ]);
        $factory = SinkronisasiErp::factory()->untukTiket($tiket, $jenis);

        return match ($status) {
            'gagal' => $factory->gagal()->create(),
            'terkirim' => $factory->terkirim()->create(),
            default => $factory->create(),
        };
    });
}

/** @return list<string> referensi baris tabel desktop, sesuai urutan tampil */
function referensiTampil(Testable $komponen): array
{
    return $komponen->instance()->halaman->getCollection()->pluck('referensi_unik')->all();
}

/**
 * Referensi baris tabel yang diberi sorotan.
 *
 * @return list<string>
 */
function referensiDisorot(string $html): array
{
    preg_match_all('#<tr\b.*?</tr>#s', $html, $baris);

    return collect($baris[0])
        ->filter(fn (string $tr) => str_contains($tr, 'aria-current="true"'))
        ->map(function (string $tr) {
            preg_match('#data-test="referensi">([^<]+)<#', $tr, $cocok);

            return trim($cocok[1] ?? '');
        })
        ->values()
        ->all();
}

/**
 * Teks chip filter berurutan, misalnya "Semua 3".
 *
 * @return list<string>
 */
function chipSinkronisasi(string $html): array
{
    preg_match_all('#<button[^>]*aria-pressed="(?:true|false)"[^>]*>(.*?)</button>#s', $html, $chip);

    return array_map(fn (string $isi) => trim(preg_replace('/\s+/', ' ', strip_tags($isi))), $chip[1]);
}

// ---- Akses ----

it('F-25: pengelola membuka halaman Sinkronisasi dengan judul tanpa deskripsi', function () {
    $html = $this->actingAs(User::factory()->pengelola()->create())
        ->get('/sinkronisasi-erp')
        ->assertOk()
        ->assertDontSee('sedang disiapkan')
        ->getContent();

    expect($html)->toMatch('#<h1[^>]*>\s*Sinkronisasi\s*</h1>#')
        ->and($html)->not->toContain('data-test="deskripsi-judul"')
        ->and($html)->not->toContain('Kirim Ulang Semua');
});

it('F-25: pemilik, penghuni, dan vendor ditolak 403 di route dan di komponen', function (string $peran) {
    $baris = barisHalamanBayar('02');
    $user = User::factory()->{$peran}()->create();

    $this->actingAs($user)->get('/sinkronisasi-erp')->assertForbidden()->assertDontSee($baris->referensi_unik);
    halamanSinkronisasi(user: $user)->assertForbidden();
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-25: Policy hanya mengizinkan pengelola berakun aktif', function () {
    $baris = barisHalamanBayar('02');
    $aktif = User::factory()->pengelola()->create();
    $nonaktif = User::factory()->pengelola()->nonaktif()->create();

    expect($aktif->can('viewAny', SinkronisasiErp::class))->toBeTrue()
        ->and($aktif->can('kirimUlang', $baris))->toBeTrue()
        ->and($nonaktif->can('viewAny', SinkronisasiErp::class))->toBeFalse()
        ->and($nonaktif->can('kirimUlang', $baris))->toBeFalse();

    foreach (['pemilik', 'penghuni', 'vendor'] as $peran) {
        $user = User::factory()->{$peran}()->create();

        expect($user->can('viewAny', SinkronisasiErp::class))->toBeFalse()
            ->and($user->can('kirimUlang', $baris))->toBeFalse();
    }

    halamanSinkronisasi(user: $nonaktif)->assertForbidden();
});

// ---- Chip ----

it('F-25: chip bawaan Gagal, Semua paling kiri, dan setiap chip berangka sesuai jumlah baris', function () {
    barisHalamanBayar('01', 'gagal');
    barisHalamanBayar('02', 'gagal');
    barisHalamanBayar('04', 'menunggu');
    barisHalamanBayar('05', 'terkirim');
    barisHalamanBayar('06', 'terkirim');
    barisHalamanBayar('07', 'terkirim');

    $komponen = halamanSinkronisasi()->assertSet('status', 'gagal');

    expect(chipSinkronisasi($komponen->html()))->toBe(['Semua 6', 'Gagal 2', 'Menunggu 1', 'Terkirim 3'])
        ->and($komponen->html())->toMatch('#aria-pressed="true"[^>]*>\s*Gagal#')
        ->and($komponen->instance()->halaman->total())->toBe(2);

    foreach (['menunggu' => 1, 'terkirim' => 3, 'semua' => 6, 'gagal' => 2] as $chip => $jumlah) {
        $komponen->call('pilihStatus', $chip)->assertSet('status', $chip);

        expect($komponen->instance()->halaman->total())->toBe($jumlah);
    }
});

it('F-25: status yang tidak dikenal kembali ke chip bawaan', function () {
    barisHalamanBayar('01', 'gagal');
    barisHalamanBayar('02', 'terkirim');

    $komponen = halamanSinkronisasi(['status' => 'ngawur'])->assertSet('status', 'gagal');
    expect($komponen->instance()->halaman->total())->toBe(1);

    $komponen->call('pilihStatus', 'terkirim')->call('pilihStatus', 'ngawur')->assertSet('status', 'gagal');

    // Diisi langsung dari browser, tanpa lewat pilihStatus.
    $komponen->set('status', "gagal' or 1=1")->assertOk();
    expect($komponen->instance()->halaman->total())->toBe(1);
});

it('F-25: angka chip dihitung dengan satu query agregat', function () {
    barisHalamanBayar('01', 'gagal');
    barisHalamanBayar('02', 'terkirim');
    $pengelola = User::factory()->pengelola()->create();

    DB::enableQueryLog();
    halamanSinkronisasi(user: $pengelola);
    $agregat = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'group by "status_sinkronisasi"'));
    DB::disableQueryLog();

    expect($agregat)->toHaveCount(1);
});

it('F-25: keadaan kosong tiap chip', function (string $chip, string $pesan) {
    halamanSinkronisasi(['status' => $chip])
        ->assertSee($pesan)
        ->assertDontSee('data-test="tabel-sinkronisasi"', false)
        ->assertDontSee('data-test="pemantau"', false);
})->with([
    ['gagal', 'Tidak ada transaksi yang gagal.'],
    ['menunggu', 'Tidak ada transaksi yang menunggu.'],
    ['terkirim', 'Belum ada transaksi yang terkirim.'],
    ['semua', 'Belum ada transaksi untuk Odoo.'],
]);

// ---- Urutan dan paginasi ----

it('F-25: percobaan terbaru di atas, yang belum pernah dicoba terakhir dengan ID terbaru dulu', function () {
    $lama = barisHalamanBayar('01', 'gagal', '2026-11-01 10:00');
    $belumSatu = barisHalamanBayar('02', 'gagal', null);
    $baru = barisHalamanBayar('04', 'gagal', '2026-11-04 12:00');
    $belumDua = barisHalamanBayar('05', 'gagal', null);

    expect(referensiTampil(halamanSinkronisasi()))->toBe([
        $baru->referensi_unik, $lama->referensi_unik, $belumDua->referensi_unik, $belumSatu->referensi_unik,
    ]);
});

it('F-25: chip Terkirim dan Semua juga berurutan menurut percobaan terbaru', function () {
    $lama = barisHalamanBayar('01', 'terkirim', '2026-11-01 10:00');
    $baru = barisHalamanBayar('02', 'terkirim', '2026-11-04 12:00');
    $gagal = barisHalamanBayar('04', 'gagal', '2026-11-02 12:00');

    expect(referensiTampil(halamanSinkronisasi(['status' => 'terkirim'])))->toBe([$baru->referensi_unik, $lama->referensi_unik])
        ->and(referensiTampil(halamanSinkronisasi(['status' => 'semua'])))
        ->toBe([$baru->referensi_unik, $gagal->referensi_unik, $lama->referensi_unik]);
});

it('F-25: 25 baris gagal menjadi 2 halaman, dan ganti chip kembali ke halaman 1', function () {
    foreach (range(1, 25) as $n) {
        barisHalamanBayar(sprintf('%02d', $n), 'gagal');
    }

    $komponen = halamanSinkronisasi();

    expect($komponen->instance()->halaman)->toHaveCount(20)
        ->and($komponen->instance()->halaman->lastPage())->toBe(2);

    $komponen->call('gotoPage', 2);
    expect($komponen->instance()->halaman)->toHaveCount(5);

    $komponen->call('pilihStatus', 'semua')->assertSet('paginators.page', 1);
    expect($komponen->instance()->halaman)->toHaveCount(20);
});

it('F-25: halaman di luar jangkauan dikembalikan ke halaman terakhir', function () {
    barisHalamanBayar('01', 'gagal');

    $komponen = halamanSinkronisasi(['page' => 9])->assertDontSee('Tidak ada transaksi yang gagal.');

    expect($komponen->instance()->halaman->currentPage())->toBe(1)
        ->and($komponen->instance()->halaman)->toHaveCount(1);
});

// ---- Isi baris ----

it('F-25: baris pembayaran menampilkan sumber, nominal, waktu, pesan galat, dan tautan ke Riwayat', function () {
    $baris = barisHalamanBayar('02', 'gagal', '2026-11-05 09:40');
    $kontrak = $baris->pembayaran->tagihan->kontrak;

    halamanSinkronisasi()
        ->assertSeeInOrder(['Referensi', 'Jenis', 'Sumber', 'Status', 'Waktu', 'Aksi'])
        ->assertSee($baris->referensi_unik)
        ->assertSee('Pembayaran')
        ->assertSee('Rina Maharani, Kamar 02, November 2026')
        ->assertSee('Rp1.250.000')
        ->assertSee('Gagal')
        ->assertSee('5 Nov, 09.40')
        ->assertSee('Odoo tidak dapat dihubungi.')
        ->assertSee('Kirim Ulang')
        ->assertSee('Mengirim…')
        ->assertSee('href="'.e(route('riwayat-transaksi.index', ['penghuni' => $kontrak->penghuni_id, 'periode' => '2026-11'])).'"', false);
});

it('F-25: tautan sumber pembayaran membuka Riwayat yang tersaring ke penghuni dan periode itu', function () {
    $baris = barisHalamanBayar('02');
    barisHalamanBayar('03', 'terkirim', nama: 'Anisa Putri');
    $kontrak = $baris->pembayaran->tagihan->kontrak;

    Livewire::withQueryParams(['penghuni' => (string) $kontrak->penghuni_id, 'periode' => '2026-11'])
        ->actingAs(User::factory()->pengelola()->create())
        ->test('pages::riwayat-transaksi.index')
        ->assertSet('filterPenghuni', (string) $kontrak->penghuni_id)
        ->assertSet('filterPeriode', '2026-11')
        ->assertRingkasan('jumlah', '1');
});

it('F-25: baris tiket menampilkan nomor, lokasi, fasilitas, dan tautan; nominal hanya untuk biaya perbaikan', function (JenisTransaksiErp $jenis, string $label, bool $bernominal) {
    $baris = barisHalamanTiket('KLH-0007', $jenis);

    $komponen = halamanSinkronisasi()
        ->assertSee($baris->referensi_unik)
        ->assertSee($label)
        ->assertSee('KLH-0007, Kamar 03, AC')
        ->assertSee('href="'.route('tiket-keluhan.index', ['tiket' => $baris->tiket_id]).'"', false);

    $bernominal ? $komponen->assertSee('Rp150.000') : $komponen->assertDontSee('Rp150.000');
})->with([
    'biaya perbaikan' => [JenisTransaksiErp::BiayaPerbaikan, 'Biaya Perbaikan', true],
    'pemeliharaan' => [JenisTransaksiErp::Pemeliharaan, 'Pemeliharaan', false],
]);

it('F-25: fasilitas area bersama ditulis Area Bersama', function () {
    barisHalamanTiket('KLH-0008', JenisTransaksiErp::BiayaPerbaikan, kamar: null);

    halamanSinkronisasi()->assertSee('KLH-0008, Area Bersama, Pompa air');
});

it('F-25: waktu yang belum ada ditulis strip, pesan galat dan tombol hanya untuk baris gagal', function () {
    barisHalamanBayar('01', 'menunggu', null, isi: ['pesan_error' => 'Sisa pesan lama.']);
    barisHalamanBayar('02', 'terkirim');

    $komponen = halamanSinkronisasi(['status' => 'semua'])
        ->assertSee('–')
        ->assertDontSee('Sisa pesan lama.')
        ->assertDontSee('Kirim Ulang')
        ->assertDontSee('data-test="kirim-ulang', false);

    barisHalamanBayar('04', 'gagal');
    $html = $komponen->call('muatUlang')->html();

    // Satu tombol di tabel desktop dan satu di kartu ponsel.
    expect(substr_count($html, 'data-test="kirim-ulang"'))->toBe(1)
        ->and(substr_count($html, 'data-test="kirim-ulang-ponsel"'))->toBe(1);
});

it('F-25: pesan galat berisi tag HTML tampil ter-escape', function () {
    $pesan = 'Odoo menolak: <script>alert("x")</script> <b>tebal</b>';
    barisHalamanBayar('01', 'gagal', isi: ['pesan_error' => $pesan]);

    halamanSinkronisasi()
        ->assertSee($pesan)
        ->assertDontSee('<script>alert', false)
        ->assertDontSee('<b>tebal</b>', false);
});

it('F-25: halaman tidak menampilkan nomor identitas atau telepon penghuni', function () {
    $penghuni = barisHalamanBayar('01')->pembayaran->tagihan->kontrak->penghuni;

    $html = halamanSinkronisasi()->html();

    expect($html)->not->toContain($penghuni->no_telepon)->not->toContain($penghuni->no_identitas_enc);
});

// ---- Sorotan dari Riwayat ----

it('F-25: ref menyorot baris yang dimaksud saja', function () {
    $dituju = barisHalamanBayar('01', 'gagal');
    barisHalamanBayar('02', 'gagal');

    $komponen = halamanSinkronisasi(['status' => 'gagal', 'ref' => $dituju->referensi_unik])
        ->assertSet('sorot', $dituju->referensi_unik)
        ->assertSee('scrollIntoView', false);

    expect(referensiDisorot($komponen->html()))->toBe([$dituju->referensi_unik])
        ->and($komponen->html())->toContain('bg-primary-container!');
});

it('F-25: ref lewat URL langsung ikut menyorot', function () {
    $dituju = barisHalamanBayar('01', 'gagal');

    $html = $this->actingAs(User::factory()->pengelola()->create())
        ->get(route('sinkronisasi-erp.index', ['status' => 'gagal', 'ref' => $dituju->referensi_unik]))
        ->assertOk()
        ->getContent();

    expect(referensiDisorot($html))->toBe([$dituju->referensi_unik]);
});

it('F-25: ref di chip lain memindahkan chip aktif', function (string $status) {
    barisHalamanBayar('01', 'gagal');
    $dituju = barisHalamanBayar('02', $status);

    $komponen = halamanSinkronisasi(['status' => 'gagal', 'ref' => $dituju->referensi_unik])->assertSet('status', $status);

    expect(referensiDisorot($komponen->html()))->toBe([$dituju->referensi_unik]);
})->with(['menunggu', 'terkirim']);

it('F-25: chip Semua tetap dipakai karena sudah memuat ref', function () {
    $dituju = barisHalamanBayar('01', 'terkirim');

    halamanSinkronisasi(['status' => 'semua', 'ref' => $dituju->referensi_unik])->assertSet('status', 'semua');
});

it('F-25: ref di halaman berikutnya membuka halaman yang benar dan paginasi tetap jalan', function (string $chip) {
    $dituju = barisHalamanBayar('01', 'gagal', '2026-10-01 08:00');
    foreach (range(2, 25) as $n) {
        barisHalamanBayar(sprintf('%02d', $n), 'gagal');
    }

    $komponen = halamanSinkronisasi(['status' => $chip, 'ref' => $dituju->referensi_unik])
        ->assertSet('paginators.page', 2)
        ->assertSet('sorot', $dituju->referensi_unik);

    expect(referensiDisorot($komponen->html()))->toBe([$dituju->referensi_unik]);

    $komponen->call('gotoPage', 1)->assertSet('paginators.page', 1)->assertSet('sorot', '');
    expect($komponen->instance()->halaman->currentPage())->toBe(1);
})->with(['gagal', 'semua']);

it('F-25: ref tidak dikenal diabaikan tanpa galat', function (string $ref) {
    barisHalamanBayar('01', 'gagal');

    $komponen = halamanSinkronisasi(['ref' => $ref])
        ->assertOk()
        ->assertSet('sorot', '')
        ->assertSet('status', 'gagal');

    expect(referensiDisorot($komponen->html()))->toBe([]);
})->with(['KPK-PAY-999999', "' or 1=1 --"]);

it('F-25: ganti chip melepas sorotan', function () {
    $dituju = barisHalamanBayar('01', 'gagal');

    $komponen = halamanSinkronisasi(['ref' => $dituju->referensi_unik])->call('pilihStatus', 'semua')->assertSet('sorot', '');

    expect(referensiDisorot($komponen->html()))->toBe([]);
});

// ---- Pembaruan otomatis ----

it('F-25: wire:poll ada di chip mana pun selama ada baris menunggu', function (string $chip) {
    barisHalamanBayar('02', 'menunggu', null);

    halamanSinkronisasi(['status' => $chip])->assertSee('wire:poll.5s.visible="muatUlang"', false);
})->with(['semua', 'gagal', 'menunggu', 'terkirim']);

it('F-25: tanpa baris menunggu tidak ada wire:poll di chip mana pun', function (string $chip) {
    barisHalamanBayar('01', 'gagal');
    barisHalamanBayar('02', 'terkirim');

    halamanSinkronisasi(['status' => $chip])->assertDontSee('wire:poll', false);
})->with(['semua', 'gagal', 'menunggu', 'terkirim']);

it('F-25: chip Gagal yang kosong tetap memantau baris menunggu', function () {
    barisHalamanBayar('01', 'menunggu', null);

    halamanSinkronisasi()
        ->assertSet('status', 'gagal')
        ->assertSee('Tidak ada transaksi yang gagal.')
        ->assertSee('wire:poll.5s.visible="muatUlang"', false);
});

it('F-25: baris menunggu di halaman lain tetap menyalakan polling', function () {
    barisHalamanBayar('21', 'menunggu', null);
    foreach (range(1, 20) as $n) {
        barisHalamanBayar(sprintf('%02d', $n), 'gagal');
    }

    $komponen = halamanSinkronisasi(['status' => 'semua'])->assertSee('wire:poll', false);

    expect($komponen->instance()->halaman->pluck('status_sinkronisasi.value'))->not->toContain('menunggu');
});

it('F-25: baris yang kembali gagal muncul lagi di chip Gagal tanpa muat ulang halaman', function () {
    $baris = barisHalamanBayar('01', 'gagal');

    $komponen = halamanSinkronisasi()
        ->call('kirimUlang', $baris->id)
        ->assertSee('Tidak ada transaksi yang gagal.')
        ->assertSee('wire:poll.5s.visible="muatUlang"', false);

    // Worker gagal lagi di luar halaman.
    $baris->refresh()->forceFill(['status_sinkronisasi' => 'gagal', 'pesan_error' => 'Odoo tidak dapat dihubungi.'])->save();

    $komponen->call('muatUlang')
        ->assertSet('status', 'gagal')
        ->assertSee($baris->referensi_unik)
        ->assertSee('data-test="kirim-ulang"', false)
        ->assertDontSee('wire:poll', false)
        ->assertDispatched('antrean-sinkronisasi', jumlah: 1);

    expect(chipSinkronisasi($komponen->html()))->toBe(['Semua 1', 'Gagal 1', 'Menunggu 0', 'Terkirim 0']);
});

it('F-25: halaman dengan lebih dari 20 baris memakai teks paginasi berbahasa Indonesia', function () {
    foreach (range(1, 21) as $n) {
        barisHalamanBayar(sprintf('%02d', $n), 'gagal');
    }

    $html = $this->actingAs(User::factory()->pengelola()->create())
        ->get('/sinkronisasi-erp')
        ->assertOk()
        ->assertSeeTextInOrder(['Menampilkan', '1', '–', '20', 'dari', '21'])
        ->assertSee('aria-label="Sebelumnya"', false)
        ->assertSee('aria-label="Berikutnya"', false)
        ->assertDontSee('Showing')
        ->assertDontSee('results')
        ->assertDontSee('laquo')
        ->getContent();

    expect($html)->toContain('data-flux-pagination');
});

it('F-25: muat ulang saat polling menampilkan status baru, memperbarui angka, dan menghentikan polling', function (string $akhir, int $angkaGagal) {
    $baris = barisHalamanBayar('01', 'menunggu', null);

    $komponen = halamanSinkronisasi(['status' => 'semua'])->assertSee('wire:poll', false);

    // Worker menyelesaikan pengiriman di luar halaman.
    $baris->forceFill(['status_sinkronisasi' => $akhir, 'waktu_kirim' => now()])->save();

    $komponen->call('muatUlang')
        ->assertDontSee('wire:poll', false)
        ->assertSee('5 Nov, 09.40')
        ->assertDispatched('antrean-sinkronisasi', jumlah: $angkaGagal)
        ->assertNotDispatched('toast-show');

    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([
    'terkirim' => ['terkirim', 0],
    'gagal' => ['gagal', 1],
]);

it('F-25: aksi muat ulang ditolak untuk peran lain', function (string $peran) {
    $komponen = halamanSinkronisasi();

    $this->actingAs(User::factory()->{$peran}()->create());

    $komponen->call('muatUlang')->assertForbidden();
})->with(['pemilik', 'penghuni', 'vendor']);

// ---- Query ----

it('F-25: jumlah query tidak bertambah menurut jumlah baris (tanpa N+1)', function () {
    $pengelola = User::factory()->pengelola()->create();
    $hitung = function () use ($pengelola) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        halamanSinkronisasi(['status' => 'semua'], $pengelola);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };
    $campuran = function (int $n): void {
        $nomor = sprintf('%02d', $n + 40);
        $status = ['gagal', 'menunggu', 'terkirim'][$n % 3];

        match ($n % 3) {
            0 => barisHalamanBayar($nomor, $status),
            1 => barisHalamanTiket(sprintf('KLH-%04d', $n), JenisTransaksiErp::BiayaPerbaikan, $status, $nomor),
            2 => barisHalamanTiket(sprintf('KLH-%04d', $n), JenisTransaksiErp::Pemeliharaan, $status, $nomor),
        };
    };

    // Satu baris dibanding 20 baris sejenis.
    barisHalamanBayar('01');
    $hitung();
    $satu = $hitung();

    foreach (range(2, 20) as $n) {
        barisHalamanBayar(sprintf('%02d', $n));
    }
    expect($hitung())->toBe($satu);

    // Campuran jenis: 3 baris (satu per jenis) dibanding 20 baris.
    SinkronisasiErp::query()->delete();
    foreach (range(0, 2) as $n) {
        $campuran($n);
    }
    $tiga = $hitung();

    foreach (range(3, 19) as $n) {
        $campuran($n);
    }

    expect(SinkronisasiErp::query()->count())->toBe(20)
        ->and($hitung())->toBe($tiga);
});
