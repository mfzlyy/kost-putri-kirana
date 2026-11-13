<?php

use App\Enums\StatusTagihan;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function tagihanUji(Kontrak $kontrak, string $periode, string $jatuhTempo, StatusTagihan $status, int $nominal = 1250000): Tagihan
{
    return Tagihan::factory()->create([
        'kontrak_id' => $kontrak->id,
        'periode' => $periode,
        'jatuh_tempo' => $jatuhTempo,
        'nominal' => $nominal,
        'status_tagihan' => $status,
    ]);
}

/**
 * Data campuran per 5 November 2026 (sebelum jatuh tempo November):
 * November: 01 lunas 1.250.000, 02 menunggu 1.250.000, 03 belum 1.250.000, 04 belum 1.400.000 (bukti ditolak 2 Nov).
 * Oktober: 05 belum 1.400.000, jatuh tempo 10 Okt (satu-satunya tunggakan).
 *
 * @return array<string, Tagihan>
 */
function dataCampuranTagihan(): array
{
    $k = [];
    foreach (['01' => 'Anisa Putri', '02' => 'Rina Maharani', '03' => 'Salsa Nabila', '04' => 'Fitri Handayani', '05' => 'Maya Sari'] as $nomor => $nama) {
        $k[$nomor] = buatKontrakUji($nomor, $nama);
    }

    $t = [];
    $t['01'] = tagihanUji($k['01'], '2026-11-01', '2026-11-10', StatusTagihan::Lunas);
    Pembayaran::factory()->diterima()->create(['tagihan_id' => $t['01']->id, 'diunggah_pada' => Carbon::parse('2026-11-03 10:00', 'Asia/Jakarta')]);

    $t['02'] = tagihanUji($k['02'], '2026-11-01', '2026-11-10', StatusTagihan::MenungguVerifikasi);
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $t['02']->id, 'diunggah_pada' => Carbon::parse('2026-11-04 10:00', 'Asia/Jakarta')]);

    $t['03'] = tagihanUji($k['03'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);

    $t['04'] = tagihanUji($k['04'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar, 1400000);
    Pembayaran::factory()->ditolak()->create([
        'tagihan_id' => $t['04']->id,
        'diunggah_pada' => Carbon::parse('2026-11-02 08:00', 'Asia/Jakarta'),
        'diverifikasi_pada' => Carbon::parse('2026-11-02 12:00', 'Asia/Jakarta'),
    ]);

    $t['05'] = tagihanUji($k['05'], '2026-10-01', '2026-10-10', StatusTagihan::BelumDibayar, 1400000);

    return $t;
}

function halamanTagihan(User $user, array $query = [])
{
    return Livewire::withQueryParams($query)->actingAs($user)->test('pages::tagihan.index');
}

it('F-15 butir 5 dan 7: pengelola melihat ringkasan dengan angka benar untuk data campuran', function () {
    dataCampuranTagihan();

    $komponen = halamanTagihan(User::factory()->pengelola()->create());

    expect($komponen->instance()->ringkasan)->toBe([
        'total' => 5150000,
        'lunas' => 1250000,
        'menunggu' => 1250000,
        'belum' => 2650000,
        'tunggakan' => 1400000,
        'jumlahTunggakan' => 1,
    ]);

    $komponen->assertSee('Rp5.150.000')
        ->assertSee('Rp2.650.000')
        ->assertSee('Rp1.400.000')
        ->assertSee('1 tagihan, semua periode')
        ->assertDontSee('November: 4 tagihan, 1 kontrak belum ditagih. Jatuh tempo tanggal 10.');
});

it('daftar memuat tagihan periode terpilih dan tunggakan lama di grup "Telat Sebelumnya"', function () {
    $t = dataCampuranTagihan();

    $komponen = halamanTagihan(User::factory()->pengelola()->create());

    expect($komponen->instance()->grupLama->pluck('id')->all())->toBe([$t['05']->id])
        ->and($komponen->instance()->grupPeriode->pluck('kamar')->all())->toBe(['01', '02', '03', '04']);

    $komponen->assertSee('Telat Sebelumnya')
        ->assertSee('Maya Sari')
        ->assertSee('10 Okt, Lewat 26 Hari');
});

it('periode dibaca dari ?periode=YYYY-MM; nilai tidak valid kembali ke bulan berjalan', function () {
    dataCampuranTagihan();
    $pengelola = User::factory()->pengelola()->create();

    $oktober = halamanTagihan($pengelola, ['periode' => '2026-10']);

    expect($oktober->instance()->periodeTerpilih->format('Y-m'))->toBe('2026-10');
    $oktober->assertDontSee('Oktober: 1 tagihan, 4 kontrak belum ditagih. Jatuh tempo tanggal 10.')
        ->assertSee('September')
        ->assertSee('November');

    foreach (['2026-13', 'abc', '2026-1', '', '2026-11-01'] as $tidakValid) {
        expect(halamanTagihan($pengelola, ['periode' => $tidakValid])->instance()->periodeTerpilih->format('Y-m'))->toBe('2026-11');
    }
});

it('nama bulan berbahasa Indonesia dan tahun ditambahkan bila berbeda dari tahun berjalan', function () {
    // Pemilik tidak punya dialog terbitkan, jadi daftar bulan di dialog tidak ikut terbaca.
    $komponen = halamanTagihan(User::factory()->pemilik()->create(), ['periode' => '2026-12']);

    $komponen->assertSee('Januari 2027')->assertSee('Desember')->assertDontSee('Desember 2026');
});

it('F-15: chip filter dan jumlahnya dihitung dari gabungan kedua grup; telat juga dihitung di status asalnya', function () {
    dataCampuranTagihan();

    $komponen = halamanTagihan(User::factory()->pengelola()->create());

    expect($komponen->instance()->hitungan)->toBe(['semua' => 5, 'telat' => 1, 'belum' => 3, 'menunggu' => 1, 'lunas' => 1]);

    $komponen->assertSeeHtml('role="group" aria-label="Saring status"')
        ->assertSeeInOrder(['Semua', 'Telat', 'Belum Bayar', 'Menunggu', 'Lunas']);

    $telat = halamanTagihan(User::factory()->pengelola()->create(), ['status' => 'telat']);
    expect($telat->instance()->barisTersaring->pluck('kamar')->all())->toBe(['05']);

    $lunas = halamanTagihan(User::factory()->pengelola()->create(), ['status' => 'lunas']);
    expect($lunas->instance()->barisTersaring->pluck('kamar')->all())->toBe(['01']);

    // Nilai filter tidak dikenal = Semua.
    expect(halamanTagihan(User::factory()->pengelola()->create(), ['status' => 'aneh'])->instance()->barisTersaring)->toHaveCount(5);
});

it('F-15: "Ditolak, 2 Nov" tampil pada tagihan belum dibayar yang buktinya ditolak', function () {
    dataCampuranTagihan();

    halamanTagihan(User::factory()->pengelola()->create())
        ->assertSee('Ditolak, 2 Nov');
});

it('filter tanpa hasil menampilkan pesan khusus', function () {
    $k = buatKontrakUji('01', 'Anisa Putri');
    tagihanUji($k, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);

    halamanTagihan(User::factory()->pengelola()->create(), ['status' => 'lunas'])
        ->assertSee('Tidak ada tagihan yang sesuai filter.');
});

it('periode belum diterbitkan dan tanpa tunggakan lama: pesan kosong dan tombol terbitkan untuk pengelola', function () {
    $pengelola = User::factory()->pengelola()->create();

    halamanTagihan($pengelola)
        ->assertSee('Tagihan November belum diterbitkan.')
        ->assertSee('November belum diterbitkan.')
        ->assertSeeHtml('data-test="terbitkan-tagihan-kosong"')
        ->assertSeeHtml('data-test="terbitkan-tagihan"');

    halamanTagihan(User::factory()->pemilik()->create())
        ->assertSee('Tagihan November belum diterbitkan.')
        ->assertDontSeeHtml('data-test="terbitkan-tagihan-kosong"');
});

it('F-10 butir 2: dialog menampilkan pratinjau jumlah, yang dilewati, jatuh tempo, dan total, dan memperbaruinya saat periode diganti', function () {
    dataCampuranTagihan(); // lima kontrak aktif @ 1.400.000, tagihan November untuk empat di antaranya

    // November: 4 dari 5 kontrak sudah ditagih, jadi tombol dan dialog memakai November.
    $komponen = halamanTagihan(User::factory()->pengelola()->create())
        ->call('bukaTerbit')
        ->assertSet('periodeTerbit', '2026-11');

    $pratinjau = $komponen->instance()->pratinjau;
    expect($pratinjau->dibuat)->toBe(1)
        ->and($pratinjau->dilewati)->toBe(4)
        ->and($pratinjau->totalNominal)->toBe(1400000);

    $komponen->assertSee('Terbitkan Tagihan November 2026?')
        ->assertSee('Kontrak Ditagih')
        ->assertSee('1 tagihan baru, belum dibayar.')
        ->assertSee('Langsung tampil di Tagihan penghuni.')
        ->assertSee('Kontrak yang sudah ditagih dilewati.');

    $komponen->set('periodeTerbit', '2026-12');
    $pratinjau = $komponen->instance()->pratinjau;

    expect($pratinjau->dibuat)->toBe(5)
        ->and($pratinjau->dilewati)->toBe(0)
        ->and($pratinjau->totalNominal)->toBe(7000000)
        ->and($pratinjau->jatuhTempo->toDateString())->toBe('2026-12-10');

    $komponen->assertSee('Terbitkan Tagihan Desember 2026?')
        ->assertSee('10 Desember 2026')
        ->assertSee('Rp7.000.000');
});

/**
 * Aria-label tombol terbitkan di kepala halaman (memuat periode); null bila tombol tidak tampil (Prompt 11A, 14E).
 */
function labelTombolTerbit($komponen): ?string
{
    if (! preg_match('/<button[^>]*data-test="terbitkan-tagihan"[^>]*>/s', $komponen->html(), $cocok)) {
        return null;
    }

    return preg_match('/aria-label="([^"]*)"/', $cocok[0], $aria) ? $aria[1] : null;
}

/**
 * Teks tampak tombol terbitkan di kepala halaman; null bila tombol tidak tampil (Prompt 14E).
 */
function teksTombolTerbit($komponen): ?string
{
    if (! preg_match('/<button[^>]*data-test="terbitkan-tagihan"[^>]*>(.*?)<\/button>/s', $komponen->html(), $cocok)) {
        return null;
    }

    return trim(preg_replace('/\s+/', ' ', strip_tags($cocok[1])));
}

it('14E: tombol hanya bertuliskan "Terbitkan" tanpa bulan, aria-label memuat periode, dialog memakai periode yang sama', function () {
    $kontrak = [];
    foreach (['01', '02', '03'] as $nomor) {
        $kontrak[$nomor] = buatKontrakUji($nomor, "Penghuni {$nomor}");
    }
    $pengelola = User::factory()->pengelola()->create();

    // Belum lengkap: periode terpilih (November).
    tagihanUji($kontrak['01'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $komponen = halamanTagihan($pengelola);
    expect(teksTombolTerbit($komponen))->toBe('Terbitkan')
        ->and(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan November 2026');
    $komponen->call('bukaTerbit')->assertSet('periodeTerbit', '2026-11')->assertSee('Terbitkan Tagihan November 2026?');

    // Lengkap: bulan berikutnya.
    tagihanUji($kontrak['02'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    tagihanUji($kontrak['03'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $komponen = halamanTagihan($pengelola);
    expect(teksTombolTerbit($komponen))->toBe('Terbitkan')
        ->and(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan Desember 2026');
    $komponen->call('bukaTerbit')->assertSet('periodeTerbit', '2026-12')->assertSee('Terbitkan Tagihan Desember 2026?');
});

it('14E: tombol di keadaan kosong juga bertuliskan "Terbitkan" dengan aria-label periode', function () {
    $komponen = halamanTagihan(User::factory()->pengelola()->create(), ['periode' => '2026-11']);

    expect($komponen->html())->toMatch('/<button[^>]*aria-label="Terbitkan tagihan November 2026"[^>]*data-test="terbitkan-tagihan-kosong"[^>]*>(?:(?!<\/button>).)*<span>Terbitkan<\/span>\s*<\/button>/s');
});

it('11A: periode tanpa tagihan tetapi punya kontrak berhak: tombol "Terbitkan {bulan itu}"', function () {
    buatKontrakUji('01', 'Anisa Putri');
    buatKontrakUji('02', 'Rina Maharani');

    $komponen = halamanTagihan(User::factory()->pengelola()->create(), ['periode' => '2026-11']);

    expect(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan November 2026');
    $komponen->assertSee('November belum diterbitkan.');
});

it('11B: periode belum punya tagihan dan tanpa kontrak sama sekali: tombol "Terbitkan {bulan itu}" dan dialog menjelaskan tidak ada kontrak', function () {
    $komponen = halamanTagihan(User::factory()->pengelola()->create(), ['periode' => '2026-11']);

    expect(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan November 2026');
    $komponen->assertSee('Tagihan November belum diterbitkan.')
        ->assertSeeHtml('data-test="terbitkan-tagihan-kosong"');

    $komponen->call('bukaTerbit')
        ->assertSet('periodeTerbit', '2026-11')
        ->assertSee('Terbitkan Tagihan November 2026?')
        ->assertSee('Tidak ada kontrak yang perlu ditagih untuk November 2026.');

    expect($komponen->instance()->pratinjau->dibuat)->toBe(0);
    expect($komponen->html())->toMatch('/<button[^>]*disabled[^>]*data-test="konfirmasi-terbitkan"|<button[^>]*data-test="konfirmasi-terbitkan"[^>]*disabled/s');

    // Menerbitkan langsung: pesan F-10 butir 7, tidak ada tagihan.
    $komponen->call('terbitkan')
        ->assertDispatched('toast-show', fn (string $nama, array $parameter): bool => ($parameter['slots']['text'] ?? null) === 'Tidak ada kontrak aktif pada November 2026. Tidak ada tagihan yang dibuat.');
    expect(Tagihan::query()->count())->toBe(0);
});

it('11B: aturan pindah ke bulan berikutnya hanya untuk periode yang sudah punya tagihan', function () {
    // Periode dengan tagihan tetapi tidak ada kontrak yang tersisa untuk ditagih: pindah ke bulan berikutnya.
    $kontrak = buatKontrakUji('01', 'Penghuni 01');
    tagihanUji($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $pengelola = User::factory()->pengelola()->create();

    expect(labelTombolTerbit(halamanTagihan($pengelola, ['periode' => '2026-11'])))->toBe('Terbitkan tagihan Desember 2026');

    // Periode lain yang belum punya tagihan sama sekali tetap untuk dirinya sendiri, walau tanpa kontrak berhak.
    expect(labelTombolTerbit(halamanTagihan($pengelola, ['periode' => '2025-12'])))->toBe('Terbitkan tagihan Desember 2025');
});

it('11A: 1 dari 6 kontrak sudah ditagih: tombol tetap "Terbitkan {bulan itu}" dan deskripsi menyebut 5 kontrak belum ditagih', function () {
    $kontrak = [];
    foreach (['01', '02', '03', '04', '05', '06'] as $nomor) {
        $kontrak[] = buatKontrakUji($nomor, "Penghuni {$nomor}");
    }
    tagihanUji($kontrak[0], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);

    $komponen = halamanTagihan(User::factory()->pengelola()->create());

    expect(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan November 2026');
    $komponen->assertDontSee('November: 1 tagihan, 5 kontrak belum ditagih. Jatuh tempo tanggal 10.');
});

it('11A: periode lengkap: tombol "Terbitkan {bulan berikutnya}" dan deskripsi tanpa kontrak belum ditagih', function () {
    foreach (['01', '02', '03'] as $nomor) {
        tagihanUji(buatKontrakUji($nomor, "Penghuni {$nomor}"), '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    }

    $komponen = halamanTagihan(User::factory()->pengelola()->create());

    expect(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan Desember 2026');
    $komponen->assertDontSee('November: 3 tagihan. Jatuh tempo tanggal 10.')
        ->assertDontSee('belum ditagih');
});

it('11A: melihat bulan depan (batas rentang) yang sudah lengkap: tombol tidak tampil', function () {
    foreach (['01', '02'] as $nomor) {
        tagihanUji(buatKontrakUji($nomor, "Penghuni {$nomor}"), '2026-12-01', '2026-12-10', StatusTagihan::BelumDibayar);
    }

    $komponen = halamanTagihan(User::factory()->pengelola()->create(), ['periode' => '2026-12']);

    expect(labelTombolTerbit($komponen))->toBeNull();
    $komponen->assertDontSeeHtml('data-test="terbitkan-tagihan"')
        ->assertDontSeeHtml('data-test="terbitkan-tagihan-kosong"');

    // Bulan depan yang belum lengkap tetap bisa diterbitkan.
    tagihanUji(buatKontrakUji('03', 'Penghuni 03'), '2026-12-01', '2026-12-10', StatusTagihan::BelumDibayar);
    buatKontrakUji('04', 'Penghuni 04');

    expect(labelTombolTerbit(halamanTagihan(User::factory()->pengelola()->create(), ['periode' => '2026-12'])))->toBe('Terbitkan tagihan Desember 2026');
});

it('11A: periode di luar rentang dan bulan berikutnya juga di luar rentang: tombol tidak tampil', function () {
    expect(labelTombolTerbit(halamanTagihan(User::factory()->pengelola()->create(), ['periode' => '2027-01'])))->toBeNull();
});

it('11A: dialog dari tombol memakai periode yang sama dengan label tombol', function () {
    $kontrak = [];
    foreach (['01', '02', '03'] as $nomor) {
        $kontrak[$nomor] = buatKontrakUji($nomor, "Penghuni {$nomor}");
    }
    $pengelola = User::factory()->pengelola()->create();

    // Ada yang belum ditagih: tombol dan dialog sama-sama November.
    tagihanUji($kontrak['01'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $komponen = halamanTagihan($pengelola);
    expect(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan November 2026');
    $komponen->call('bukaTerbit')->assertSet('periodeTerbit', '2026-11')->assertSee('Terbitkan Tagihan November 2026?');

    // Lengkap: tombol dan dialog sama-sama Desember.
    tagihanUji($kontrak['02'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    tagihanUji($kontrak['03'], '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $komponen = halamanTagihan($pengelola);
    expect(labelTombolTerbit($komponen))->toBe('Terbitkan tagihan Desember 2026');
    $komponen->call('bukaTerbit')->assertSet('periodeTerbit', '2026-12')->assertSee('Terbitkan Tagihan Desember 2026?');

    // Periode lain yang dilihat: tombol dan dialog mengikuti periode itu.
    $oktober = halamanTagihan($pengelola, ['periode' => '2026-10']);
    expect(labelTombolTerbit($oktober))->toBe('Terbitkan tagihan Oktober 2026');
    $oktober->call('bukaTerbit')->assertSet('periodeTerbit', '2026-10');
});

it('11A: tanggal jatuh tempo kosong: tombol tampil nonaktif untuk periode yang dilihat, pemilik tanpa tombol', function () {
    PengaturanOperasional::aktif()->update(['tanggal_jatuh_tempo' => null]);
    buatKontrakUji('01', 'Penghuni 01');

    expect(labelTombolTerbit(halamanTagihan(User::factory()->pengelola()->create())))->toBe('Terbitkan tagihan November 2026')
        ->and(labelTombolTerbit(halamanTagihan(User::factory()->pemilik()->create())))->toBeNull();
});

it('dialog: kontrak tidak penuh sebulan disebut dengan strategi yang dipakai', function () {
    buatKontrakUji('01', 'Anisa Putri');
    buatKontrakUji('02', 'Rina Maharani', ['tanggal_mulai' => '2026-12-20', 'tanggal_berakhir' => '2027-12-19']);
    $pengelola = User::factory()->pengelola()->create();

    config(['kirana.tagihan.parsial' => 'penuh']);
    halamanTagihan($pengelola)->call('bukaTerbit')->set('periodeTerbit', '2026-12')
        ->assertSee('Tidak Penuh Sebulan')
        ->assertSee('1 (dihitung tarif penuh)')
        ->assertSee('Rp2.800.000');

    config(['kirana.tagihan.parsial' => 'prorata']);
    halamanTagihan($pengelola)->call('bukaTerbit')->set('periodeTerbit', '2026-12')
        ->assertSee('1 (dihitung prorata harian)')
        ->assertDontSee('Rp2.800.000');
});

it('dialog: catatan muncul bila jatuh tempo periode itu sudah lewat', function () {
    buatKontrakUji('01', 'Anisa Putri');

    halamanTagihan(User::factory()->pengelola()->create())
        ->call('bukaTerbit')->set('periodeTerbit', '2026-10')
        ->assertSee('Jatuh tempo 10 Okt sudah lewat, langsung terhitung tunggakan.')
        ->set('periodeTerbit', '2026-12')
        ->assertDontSee('sudah lewat, langsung terhitung tunggakan.');
});

it('dialog: tanpa kontrak yang perlu ditagih, tombol Terbitkan nonaktif dan ada teksnya', function () {
    // Periode kosong tanpa kontrak: tombol tetap untuk November, dialog menjelaskan tidak ada yang perlu ditagih.
    $komponen = halamanTagihan(User::factory()->pengelola()->create())
        ->call('bukaTerbit')
        ->assertSet('periodeTerbit', '2026-11')
        ->assertSee('Tidak ada kontrak yang perlu ditagih untuk November 2026.');

    expect($komponen->instance()->pratinjau->dibuat)->toBe(0);
    $komponen->assertSeeHtml('data-test="konfirmasi-terbitkan"');
});

it('tanggal jatuh tempo belum diatur: tombol terbitkan nonaktif dengan callout (pengelola), teks saja untuk pemilik', function () {
    PengaturanOperasional::aktif()->update(['tanggal_jatuh_tempo' => null]);

    $pengelola = halamanTagihan(User::factory()->pengelola()->create())
        ->assertSee('Atur tanggal jatuh tempo di')
        ->assertSeeHtml(route('pengaturan.index'));

    expect($pengelola->html())->toMatch('/<button[^>]*data-test="terbitkan-tagihan"[^>]*disabled|<button[^>]*disabled[^>]*data-test="terbitkan-tagihan"/s');

    halamanTagihan(User::factory()->pemilik()->create())
        ->assertSee('Tanggal jatuh tempo belum diatur.')
        ->assertDontSee('Atur tanggal jatuh tempo di');
});

it('F-03: pemilik melihat halaman tanpa tombol terbitkan dan tanpa dialog', function () {
    dataCampuranTagihan();

    $respons = $this->actingAs(User::factory()->pemilik()->create())->get('/tagihan');

    $respons->assertOk()
        ->assertSee('Tagihan')
        ->assertSee('Rp5.150.000')
        ->assertDontSeeHtml('data-test="terbitkan-tagihan"')
        ->assertDontSee('Terbitkan Tagihan')
        ->assertDontSee('Terbitkan Desember');
});

it('F-03: penghuni dan vendor mendapat 403 di /tagihan, tanpa data', function (string $peran) {
    dataCampuranTagihan();

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/tagihan')
        ->assertForbidden()
        ->assertDontSee('Rp5.150.000');
})->with(['penghuni', 'vendor']);

it('jumlah query tidak bertambah saat jumlah tagihan bertambah (tanpa N+1)', function () {
    $pengelola = User::factory()->pengelola()->create();

    $hitung = function () use ($pengelola): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($pengelola)->get('/tagihan')->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    $kontrak = buatKontrakUji('01', 'Anisa Putri');
    tagihanUji($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $sedikit = $hitung();

    foreach (['02', '03', '04', '05', '06'] as $nomor) {
        $kontrak = buatKontrakUji($nomor, "Penghuni {$nomor}");
        tagihanUji($kontrak, '2026-10-01', '2026-10-10', StatusTagihan::BelumDibayar);
        $t = tagihanUji($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
        Pembayaran::factory()->ditolak()->create(['tagihan_id' => $t->id]);
    }

    expect($hitung())->toBe($sedikit);
});

it('pembayaran hasil migrasi (bukti_bayar_path migrasi/tanpa-bukti) tidak membuat halaman galat', function () {
    $kontrak = buatKontrakUji('01', 'Anisa Putri');
    $lunas = tagihanUji($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::Lunas);
    Pembayaran::factory()->diterima()->create(['tagihan_id' => $lunas->id, 'bukti_bayar_path' => 'migrasi/tanpa-bukti']);

    $kontrak2 = buatKontrakUji('02', 'Rina Maharani');
    $ditolak = tagihanUji($kontrak2, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    Pembayaran::factory()->ditolak()->create(['tagihan_id' => $ditolak->id, 'bukti_bayar_path' => 'migrasi/tanpa-bukti']);

    $this->actingAs(User::factory()->pengelola()->create())->get('/tagihan')
        ->assertOk()
        ->assertSee('Anisa Putri')
        ->assertSee('Lunas');
});

it('daftar dan ringkasan memakai Format::rupiah dan tidak mengubah data', function () {
    $t = dataCampuranTagihan();
    $sebelum = Tagihan::query()->orderBy('id')->pluck('status_tagihan', 'id')->all();

    halamanTagihan(User::factory()->pengelola()->create())->assertSee('Rp1.250.000');

    expect(Tagihan::query()->orderBy('id')->pluck('status_tagihan', 'id')->all())->toBe($sebelum)
        ->and($t['05']->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar);
});
