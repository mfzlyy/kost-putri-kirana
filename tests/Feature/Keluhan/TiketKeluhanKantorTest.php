<?php

use App\Enums\LokasiKeluhan;
use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use App\Support\MenuNavigasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta')));

afterEach(fn () => Carbon::setTestNow());

function halamanKantor(?User $user = null, array $params = [])
{
    return Livewire::actingAs($user ?? User::factory()->pengelola()->create())->test('pages::tiket-keluhan.index', $params);
}

/**
 * Tiket di kamar $nomor (atau area bersama bila null), dengan baris riwayat pertamanya.
 */
function tiketKantor(string $nomor, string $lapor, StatusTiket $status = StatusTiket::Diajukan, ?string $nomorKamar = null, array $atribut = []): TiketKeluhan
{
    $penghuni = Penghuni::factory()->create(['user_id' => User::factory()->penghuni()->create(['nama' => 'Maya Sari'])]);
    $kamar = $nomorKamar === null ? null : Kamar::query()->firstOrCreate(['nomor_kamar' => $nomorKamar], ['tarif_bulanan' => 1400000]);
    $fasilitas = Fasilitas::factory()->create(['nama_fasilitas' => $kamar ? 'AC' : 'Pompa air', 'kamar_id' => $kamar?->id]);
    $waktu = Carbon::parse($lapor, 'Asia/Jakarta');

    $tiket = TiketKeluhan::factory()->create(array_merge([
        'nomor_tiket' => $nomor,
        'penghuni_id' => $penghuni->id,
        'fasilitas_id' => $fasilitas->id,
        'lokasi' => $kamar ? 'kamar' : 'area_bersama',
        'tanggal_lapor' => $waktu,
        'status_tiket' => $status,
        'vendor_id' => $status === StatusTiket::Diajukan ? null : Vendor::factory()->create(['nama_vendor' => 'CV Sejuk Abadi'])->id,
        'biaya_perbaikan' => $status === StatusTiket::Selesai ? 350000 : null,
    ], $atribut));

    RiwayatStatusTiket::factory()->perubahan(null, StatusTiket::Diajukan)->create([
        'tiket_id' => $tiket->id,
        'diubah_oleh' => $penghuni->user_id,
        'waktu_perubahan' => $waktu,
    ]);

    return $tiket;
}

it('F-17: pengelola dan pemilik membuka halaman, penghuni dan vendor ditolak', function () {
    foreach (['pengelola', 'pemilik'] as $peran) {
        $this->actingAs(User::factory()->{$peran}()->create())->get('/tiket-keluhan')->assertOk()->assertSee('Keluhan');
    }

    foreach (['penghuni', 'vendor'] as $peran) {
        $this->actingAs(User::factory()->{$peran}()->create())->get('/tiket-keluhan')->assertForbidden();
    }

    foreach ([User::factory()->penghuni()->create(), User::factory()->vendor()->create()] as $user) {
        Livewire::actingAs($user)->test('pages::tiket-keluhan.index')->assertForbidden();
    }
});

it('F-17 butir 1 dan 4: diajukan di atas, lalu status lain; terlama di atas dalam tiap kelompok', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00', StatusTiket::Selesai);
    tiketKantor('KLH-0002', '2026-11-02 08:00', StatusTiket::Ditugaskan);
    tiketKantor('KLH-0003', '2026-11-04 08:00');
    tiketKantor('KLH-0004', '2026-11-03 08:00');

    expect(TiketKeluhan::query()->antreanKantor()->pluck('nomor_tiket')->all())
        ->toBe(['KLH-0004', 'KLH-0003', 'KLH-0001', 'KLH-0002']);

    halamanKantor(params: ['status' => 'semua'])->assertSeeInOrder(['KLH-0004', 'KLH-0003', 'KLH-0001', 'KLH-0002']);
});

it('F-17: chip berurutan dengan angka, bawaan Perlu Ditangani, nilai tidak dikenal kembali ke bawaan', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00', StatusTiket::Selesai);
    tiketKantor('KLH-0002', '2026-11-02 08:00', StatusTiket::Ditugaskan);
    tiketKantor('KLH-0003', '2026-11-03 08:00', StatusTiket::Dikerjakan);
    tiketKantor('KLH-0004', '2026-11-04 08:00');

    $komponen = halamanKantor();
    $html = $komponen->html();

    preg_match_all('#<button[^>]*aria-pressed="(true|false)"[^>]*>(.*?)</button>#s', $html, $chip, PREG_SET_ORDER);
    $label = collect($chip)->map(fn ($c) => trim(preg_replace('/\s+/', ' ', strip_tags($c[2]))))->all();
    $terpilih = collect($chip)->filter(fn ($c) => $c[1] === 'true')->map(fn ($c) => trim(preg_replace('/\s+/', ' ', strip_tags($c[2]))))->values()->all();

    expect($label)->toBe(['Semua 4', 'Perlu Ditangani 3', 'Diajukan 1', 'Ditugaskan 1', 'Dikerjakan 1', 'Selesai 1'])
        ->and($terpilih)->toBe(['Perlu Ditangani 3']);

    $komponen->assertDontSee('KLH-0001');

    halamanKantor(params: ['status' => 'ngawur'])->assertSee('KLH-0004')->assertDontSee('KLH-0001');
    halamanKantor(params: ['status' => 'selesai'])->assertSee('KLH-0001')->assertDontSee('KLH-0004');

    $komponen->call('pilihStatus', 'selesai')->assertSet('status', 'selesai')->assertSee('KLH-0001')->assertDontSee('KLH-0002');
});

it('F-17: item daftar menampilkan nomor, fasilitas, status, lokasi dengan tanggal, dan vendor atau Belum Ada Vendor', function () {
    tiketKantor('KLH-0001', '2026-11-02 08:00', nomorKamar: '03');
    tiketKantor('KLH-0002', '2026-11-03 08:00', StatusTiket::Ditugaskan);

    halamanKantor(params: ['status' => 'semua'])
        ->assertSeeInOrder(['KLH-0001', 'AC', 'Diajukan', 'Kamar 03, 2 Nov', 'Belum Ada Vendor'])
        ->assertSeeInOrder(['KLH-0002', 'Pompa air', 'Ditugaskan', 'Area Bersama, 3 Nov', 'CV Sejuk Abadi']);
});

it('20D: tanpa pilihan, id ngawur, atau id tidak ada: daftar selebar konten tanpa panel', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00');
    tiketKantor('KLH-0002', '2026-11-02 08:00');

    foreach (['', '999999', 'abc', '-1'] as $nilai) {
        $komponen = halamanKantor(params: ['tiket' => $nilai])
            ->assertDontSeeHtml('data-test="panel-tiket"')
            ->assertDontSeeHtml('data-test="isi-panel"')
            ->assertDontSeeHtml('aria-current="true"')
            ->assertDontSeeHtml('lg:grid-cols-')
            ->assertSee('KLH-0001')
            ->assertSee('KLH-0002');

        expect($komponen->instance()->dipilih)->toBeNull();
    }
});

it('20D: ?tiket= membuka panel dua kolom berisi tiket itu; kembali() menutupnya', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00');
    $baru = tiketKantor('KLH-0002', '2026-11-02 08:00');

    $komponen = halamanKantor(params: ['tiket' => (string) $baru->id])
        ->assertSeeHtml('data-test="panel-tiket"')
        ->assertSeeHtml('lg:grid-cols-[minmax(280px,2fr)_minmax(0,3fr)]')
        ->assertSeeHtml('<h2 id="judul-panel" tabindex="-1"')
        ->assertSeeHtml('aria-label="Tutup detail"');

    expect($komponen->instance()->dipilih->id)->toBe($baru->id)
        ->and(substr_count($komponen->html(), 'aria-current="true"'))->toBe(1);

    $komponen->call('kembali')
        ->assertSet('tiket', '')
        ->assertDontSeeHtml('data-test="panel-tiket"');
});

it('20D: tiket pilihan di luar filter tetap tampil di panel walau daftar kosong', function () {
    $selesai = tiketKantor('KLH-0001', '2026-11-01 08:00', StatusTiket::Selesai);

    halamanKantor(params: ['tiket' => (string) $selesai->id])
        ->assertSee('Tidak ada tiket yang sesuai filter.')
        ->assertSeeHtml('data-test="panel-tiket"')
        ->assertSee('KLH-0001');
});

it('F-18: panel menampilkan pelapor, diajukan, deskripsi (baris baru dipertahankan, di-escape), dan riwayat bernama pengubah', function () {
    $tiket = tiketKantor('KLH-0001', '2026-11-02 08:15', nomorKamar: '03', atribut: ['deskripsi' => "Baris satu\n<b>tebal</b>"]);
    $pengelola = User::factory()->pengelola()->create(['nama' => 'Budi Pengelola']);

    $html = halamanKantor($pengelola, ['tiket' => (string) $tiket->id])
        ->assertSeeInOrder(['KLH-0001', 'AC, Kamar 03', 'Pelapor', 'Maya Sari', 'Diajukan', '2 Nov 2026, 08.15', 'Deskripsi'])
        ->assertSee('&lt;b&gt;tebal&lt;/b&gt;', false)
        ->assertDontSee('<b>tebal</b>', false)
        ->assertSeeInOrder(['Riwayat Status', 'Diajukan', '2 Nov, 08.15', 'Maya Sari'])
        ->assertSeeHtml('data-terisi="1"')
        ->html();

    expect($html)->toContain('whitespace-pre-line');
});

it('F-18 butir 4: tiket selesai menampilkan vendor, biaya, durasi, dan seluruh riwayat berurutan', function () {
    $tiket = tiketKantor('KLH-0005', '2026-11-01 07:00', StatusTiket::Selesai, '03', ['jenis_kerusakan' => 'Freon habis', 'catatan_instruksi' => 'Cek freon']);
    $pengelola = User::factory()->pengelola()->create(['nama' => 'Budi Pengelola']);

    foreach ([
        [StatusTiket::Diajukan, StatusTiket::Ditugaskan, '2026-11-01 09:00', $pengelola->id],
        [StatusTiket::Ditugaskan, StatusTiket::Dikerjakan, '2026-11-02 10:30', $tiket->vendor->user_id],
        [StatusTiket::Dikerjakan, StatusTiket::Selesai, '2026-11-02 12:00', $tiket->vendor->user_id],
    ] as [$sebelum, $sesudah, $waktu, $oleh]) {
        RiwayatStatusTiket::factory()->perubahan($sebelum, $sesudah)->create([
            'tiket_id' => $tiket->id,
            'diubah_oleh' => $oleh,
            'waktu_perubahan' => Carbon::parse($waktu, 'Asia/Jakarta'),
        ]);
    }

    halamanKantor($pengelola, ['tiket' => (string) $tiket->id, 'status' => 'semua'])
        ->assertSee('CV Sejuk Abadi, ')
        ->assertSee('Freon habis')
        ->assertSee('Cek freon')
        ->assertSee('Rp350.000')
        ->assertSee('1 hari 5 jam')
        ->assertSeeInOrder(['Diajukan', '1 Nov, 07.00', 'Ditugaskan', '1 Nov, 09.00', 'Budi Pengelola', 'Dikerjakan', '2 Nov, 10.30', 'Selesai', '2 Nov, 12.00'])
        ->assertSeeHtml('data-terisi="4"')
        ->assertDontSee('Tugaskan Vendor');
});

it('F-17: pemilik melihat panel yang sama tanpa form', function () {
    $tiket = tiketKantor('KLH-0001', '2026-11-02 08:15');
    Vendor::factory()->create(['nama_vendor' => 'CV Sejuk Abadi']);

    halamanKantor(User::factory()->pemilik()->create(), ['tiket' => (string) $tiket->id])
        ->assertSee('Riwayat Status')
        ->assertDontSee('Tugaskan Vendor')
        ->assertDontSee('CV Sejuk Abadi')
        ->assertDontSeeHtml('data-test="form-tugaskan"');
});

it('F-17: keadaan kosong membedakan belum ada tiket dan filter tanpa hasil', function () {
    halamanKantor()->assertSee('Belum ada keluhan.')->assertDontSee('Tidak ada tiket yang sesuai filter.');

    tiketKantor('KLH-0001', '2026-11-01 08:00', StatusTiket::Selesai);

    halamanKantor()->assertSee('Tidak ada tiket yang sesuai filter.')->assertDontSee('Belum ada keluhan.');
});

it('F-17: daftar dipaginasi 20 per halaman', function () {
    foreach (range(1, 21) as $i) {
        tiketKantor(sprintf('KLH-%04d', $i), '2026-11-01 08:00');
    }

    $komponen = halamanKantor();

    expect($komponen->instance()->halaman->perPage())->toBe(20)
        ->and($komponen->instance()->halaman->total())->toBe(21)
        ->and($komponen->instance()->halaman->count())->toBe(20);
});

it('F-17: jumlah query daftar sama untuk 1 dan 20 tiket (tanpa N+1)', function () {
    $hitung = function () {
        DB::enableQueryLog();
        DB::flushQueryLog();
        halamanKantor(params: ['status' => 'semua']);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    tiketKantor('KLH-0001', '2026-11-01 08:00', StatusTiket::Ditugaskan, '01');
    $sedikit = $hitung();

    foreach (range(2, 20) as $i) {
        tiketKantor(sprintf('KLH-%04d', $i), '2026-11-01 08:00', StatusTiket::Ditugaskan, sprintf('%02d', $i));
    }

    expect($hitung())->toBe($sedikit);
});

it('F-17 Bagian D: angka antrean Keluhan di menu pengelola dan pemilik menghitung tiket diajukan', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00');
    tiketKantor('KLH-0002', '2026-11-02 08:00');
    tiketKantor('KLH-0003', '2026-11-03 08:00', StatusTiket::Ditugaskan);

    foreach (['pengelola', 'pemilik'] as $peran) {
        $user = User::factory()->{$peran}()->create();
        $menu = MenuNavigasi::denganAngka(MenuNavigasi::untuk($user->peran), $user->peran);
        $item = collect($menu)->firstWhere('route', 'tiket-keluhan.index');

        expect($item['jumlah'])->toBe(2)->and($item['antrean'])->toBe('keluhan')->and($item['satuan'])->toBe('keluhan baru');

        $this->actingAs($user)->get('/kamar')->assertOk()
            ->assertSee('x-on:antrean-keluhan.window', false)
            ->assertSee('2 keluhan baru');
    }
});

it('F-17 Bagian D: penghuni dan vendor tidak mendapat angka antrean', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00');

    foreach (['penghuni', 'vendor'] as $peran) {
        $menu = MenuNavigasi::denganAngka(MenuNavigasi::untuk(User::factory()->{$peran}()->make()->peran), User::factory()->{$peran}()->make()->peran);

        expect(collect($menu)->pluck('jumlah')->filter()->all())->toBe([]);
    }
});

it('F-17: angka chip sama dengan jumlah baris yang tampil, dan Perlu Ditangani tidak memuat tiket selesai', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00', StatusTiket::Selesai);
    tiketKantor('KLH-0002', '2026-11-02 08:00', StatusTiket::Ditugaskan);
    tiketKantor('KLH-0003', '2026-11-03 08:00', StatusTiket::Dikerjakan);
    tiketKantor('KLH-0004', '2026-11-04 08:00');

    $komponen = halamanKantor();

    foreach (['perlu' => 3, 'semua' => 4, 'diajukan' => 1, 'ditugaskan' => 1, 'dikerjakan' => 1, 'selesai' => 1] as $nilai => $jumlah) {
        $komponen->call('pilihStatus', $nilai);

        expect($komponen->instance()->halaman->total())->toBe($jumlah)
            ->and($komponen->instance()->hitungan[$nilai])->toBe($jumlah);
    }

    $komponen->call('pilihStatus', 'perlu')->assertDontSee('KLH-0001');
});

it('F-16 dan F-17: tiket baru dari ajukanKeluhan langsung tampil di antrean teratas', function () {
    $kontrak = buatKontrakUji('07', 'Rina Maharani');
    $ac = Fasilitas::factory()->create(['nama_fasilitas' => 'AC', 'kamar_id' => $kontrak->kamar_id]);
    tiketKantor('KLH-0001', '2026-11-04 08:00', StatusTiket::Ditugaskan);

    $baru = TiketKeluhan::ajukanKeluhan($kontrak->penghuni, LokasiKeluhan::Kamar, $ac, 'AC tidak dingin', $kontrak->penghuni->user);

    halamanKantor()
        ->assertSeeInOrder([$baru->nomor_tiket, 'AC', 'Diajukan', 'Kamar 07', 'Belum Ada Vendor', 'KLH-0001'])
        ->assertSet('status', 'perlu');

    expect(TiketKeluhan::query()->antreanKantor()->first()->id)->toBe($baru->id);
});

it('F-18: pemilik juga melihat riwayat lengkap dengan nama pengubah', function () {
    $tiket = tiketKantor('KLH-0001', '2026-11-02 08:15', StatusTiket::Ditugaskan, '03');
    $pengelola = User::factory()->pengelola()->create(['nama' => 'Budi Pengelola']);
    RiwayatStatusTiket::factory()->perubahan(StatusTiket::Diajukan, StatusTiket::Ditugaskan)->create([
        'tiket_id' => $tiket->id,
        'diubah_oleh' => $pengelola->id,
        'waktu_perubahan' => Carbon::parse('2026-11-02 10:00', 'Asia/Jakarta'),
    ]);

    halamanKantor(User::factory()->pemilik()->create(), ['tiket' => (string) $tiket->id])
        ->assertSeeInOrder(['Riwayat Status', 'Diajukan', '2 Nov, 08.15', 'Maya Sari', 'Ditugaskan', '2 Nov, 10.00', 'Budi Pengelola']);
});

it('F-17: ?tiket= yang tidak ada tidak menimbulkan galat dan hanya menampilkan daftar', function () {
    tiketKantor('KLH-0001', '2026-11-01 08:00');

    halamanKantor(params: ['tiket' => '424242'])->assertOk()->assertSee('KLH-0001')->assertDontSeeHtml('data-test="panel-tiket"');
});

it('Bagian D: angka menu Keluhan tidak tampil saat 0 dan tidak ada untuk penghuni dan vendor', function () {
    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/kamar')->assertOk()->getContent();

    expect($html)->toContain('x-on:antrean-keluhan.window="n = $event.detail.jumlah"')
        ->toContain('x-show="n > 0"');
    preg_match('#<span\s+x-data="\{ n: (\d+) \}"\s+x-on:antrean-keluhan.*?style="display: none"#s', $html, $cocok);
    expect($cocok)->not->toBeEmpty()->and($cocok[1])->toBe('0');

    foreach (['penghuni' => '/tagihan-saya', 'vendor' => '/permintaan-kerja'] as $peran => $url) {
        $this->actingAs(User::factory()->{$peran}()->create())->get($url)->assertOk()
            ->assertDontSee('antrean-keluhan', false)
            ->assertDontSee('keluhan baru');
    }
});
