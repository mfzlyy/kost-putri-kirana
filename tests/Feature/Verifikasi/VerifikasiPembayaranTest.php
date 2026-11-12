<?php

use App\Enums\StatusTagihan;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\User;
use App\Support\Format;
use App\Support\MenuNavigasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    // Kamis, 5 November 2026.
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    Http::preventStrayRequests();
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function halamanVerifikasi(?User $user = null, array $query = [])
{
    return Livewire::withQueryParams($query)
        ->actingAs($user ?? User::factory()->pengelola()->create())
        ->test('pages::verifikasi-pembayaran.index');
}

it('F-13 butir 1: antrean hanya berisi pembayaran menunggu, unggahan terlama di atas', function () {
    $baru = siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-04 08:00');
    $lama = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-02 19:42');
    $diterima = siapkanBuktiMenunggu('03', 'Salsa Nabila', '2026-11-01 10:00');
    $diterima->forceFill(['status_verifikasi' => 'diterima'])->save();
    $ditolak = siapkanBuktiMenunggu('04', 'Fitri Handayani', '2026-11-01 11:00');
    $ditolak->forceFill(['status_verifikasi' => 'ditolak', 'alasan_penolakan' => 'Buram'])->save();

    $komponen = halamanVerifikasi();

    expect($komponen->instance()->antrean->pluck('id')->all())->toBe([$lama->id, $baru->id]);

    $komponen->assertSee('2 bukti bayar menunggu')
        ->assertSee('Yang paling lama diunggah ada di atas. Cocokkan nominal di bukti dengan tagihan sebelum menerima.')
        ->assertSeeInOrder(['Kamar 02, Rina', 'Kamar 01, Anisa'])
        ->assertDontSee('Kamar 03')
        ->assertDontSee('Kamar 04');
});

it('F-13: tanpa bukti menunggu tampil "Tidak ada bukti yang menunggu" tanpa panel', function () {
    halamanVerifikasi()
        ->assertSee('Tidak ada bukti yang menunggu')
        ->assertSee('Bukti baru dari penghuni akan muncul di sini.')
        ->assertDontSee('Terima pembayaran')
        ->assertDontSee('bukti bayar menunggu');
});

it('item antrean menampilkan kamar, nama depan, nominal, bulan, dan waktu unggah', function () {
    siapkanBuktiMenunggu('05', 'Maya Sari', '2026-11-04 19:42', nominal: 1400000);

    halamanVerifikasi()
        ->assertSee('Kamar 05, Maya')
        ->assertDontSee('Kamar 05, Maya Sari')
        ->assertSee('Rp1.400.000')
        ->assertSee('November, diunggah kemarin 19.42');
});

it('?bukti memilih item; id tidak valid, sudah diputuskan, atau bukan angka diabaikan', function () {
    $pertama = siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    $kedua = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');
    $diterima = siapkanBuktiMenunggu('03', 'Salsa Nabila', '2026-11-01 08:00');
    $diterima->forceFill(['status_verifikasi' => 'diterima'])->save();

    $dipilih = halamanVerifikasi(query: ['bukti' => (string) $kedua->id]);
    expect($dipilih->instance()->dipilih->id)->toBe($kedua->id)
        ->and($dipilih->instance()->panel->id)->toBe($kedua->id);
    $dipilih->assertSee('Kamar 02, tagihan November')->assertSee('Rina Maharani');

    foreach (['999999', 'abc', '', (string) $diterima->id, '-1', '1e3'] as $nilai) {
        $komponen = halamanVerifikasi(query: ['bukti' => $nilai]);

        expect($komponen->instance()->dipilih)->toBeNull()
            ->and($komponen->instance()->panel->id)->toBe($pertama->id);
    }
});

it('bawaan desktop memilih item teratas, dan aria-current hanya pada item yang dipilih', function () {
    $pertama = siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');

    $html = halamanVerifikasi()->html();

    expect($html)->toContain('Kamar 01, tagihan November')
        ->and(substr_count($html, 'aria-current="true"'))->toBe(1)
        ->and($html)->toMatch('#aria-current="true"[^>]*>\s*<span class="font-\[650\]">Kamar 01#s');
    expect($pertama->id)->toBeInt();
});

it('pilih() di komponen memindahkan panel, dan kembali() mengosongkan pilihan (ponsel)', function () {
    siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    $kedua = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');

    halamanVerifikasi()
        ->call('pilih', $kedua->id)
        ->assertSet('bukti', (string) $kedua->id)
        ->assertSee('Kamar 02, tagihan November')
        ->assertSee('Kembali ke daftar bukti')
        ->call('kembali')
        ->assertSet('bukti', '')
        ->call('pilih', 987654)
        ->assertSet('bukti', '');
});

it('format waktu unggah: hari ini, kemarin, nama hari, dan tanggal', function (string $diunggah, string $harapan) {
    expect(Format::waktuUnggah(Carbon::parse($diunggah, 'Asia/Jakarta')))->toBe($harapan);
})->with([
    'hari ini' => ['2026-11-05 07:30', 'hari ini 07.30'],
    'tepat tengah malam hari ini' => ['2026-11-05 00:00', 'hari ini 00.00'],
    'kemarin' => ['2026-11-04 19:42', 'kemarin 19.42'],
    'kemarin menjelang tengah malam' => ['2026-11-04 23:59', 'kemarin 23.59'],
    '3 hari lalu (Senin)' => ['2026-11-02 08:15', 'Senin 08.15'],
    '2 hari lalu (Selasa)' => ['2026-11-03 19:42', 'Selasa 19.42'],
    '6 hari lalu (Jumat)' => ['2026-10-30 08:15', 'Jumat 08.15'],
    '7 hari lalu' => ['2026-10-29 08:15', '29 Okt, 08.15'],
    'bulan lalu' => ['2026-09-03 07:05', '3 Sep, 07.05'],
]);

it('format waktu unggah memakai tanggal kalender Asia/Jakarta, bukan UTC', function () {
    // 4 November 18.30 UTC = 5 November 01.30 WIB: sudah "hari ini" bagi pengelola.
    expect(Format::waktuUnggah(Carbon::parse('2026-11-04 18:30', 'UTC')))->toBe('hari ini 01.30');
});

it('item telat menampilkan "Telat n hari" di antrean dan di panel', function () {
    siapkanBuktiMenunggu('05', 'Maya Sari', '2026-11-03 10:00', periode: '2026-10-01', jatuhTempo: '2026-10-10', nominal: 1400000);

    $html = halamanVerifikasi()->html();

    expect(substr_count($html, 'Telat 26 hari'))->toBe(2);
});

it('bukti tepat waktu tidak ditandai telat', function () {
    siapkanBuktiMenunggu('06', 'Dewi Lestari', '2026-10-10 23:00', periode: '2026-10-01', jatuhTempo: '2026-10-10', nominal: 1400000);

    halamanVerifikasi()->assertDontSee('Telat');
});

it('angka antrean di menu sidebar pengelola sesuai jumlah bukti menunggu', function () {
    siapkanBuktiMenunggu('01', 'Anisa Putri');
    siapkanBuktiMenunggu('02', 'Rina Maharani');
    siapkanBuktiMenunggu('03', 'Salsa Nabila');
    $diterima = siapkanBuktiMenunggu('04', 'Fitri Handayani');
    $diterima->forceFill(['status_verifikasi' => 'diterima'])->save();

    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/verifikasi-pembayaran')
        ->assertOk()
        ->assertSee('data-test="angka-antrean"', false)
        ->assertSee('x-data="{ n: 3 }"', false);
});

it('angka antrean disembunyikan saat kosong, dan tidak ada untuk pemilik', function () {
    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/tagihan')
        ->assertOk()
        ->assertSee('x-data="{ n: 0 }"', false)
        ->assertSee('style="display: none"', false);

    siapkanBuktiMenunggu('01', 'Anisa Putri');

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/tagihan')
        ->assertOk()
        ->assertDontSee('angka-antrean', false)
        ->assertDontSee('Verifikasi pembayaran');
});

it('menghitung angka antrean memakai satu query', function () {
    siapkanBuktiMenunggu('01', 'Anisa Putri');
    $pengelola = User::factory()->pengelola()->create();

    DB::enableQueryLog();
    $menu = MenuNavigasi::denganAngka(MenuNavigasi::untuk($pengelola->peran), $pengelola->peran);
    $jumlahQuery = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($jumlahQuery)->toBe(1)
        ->and(collect($menu)->firstWhere('route', 'verifikasi-pembayaran.index')['jumlah'])->toBe(1);
});

it('jumlah query tidak bertambah menurut panjang antrean (tanpa N+1)', function () {
    $pengelola = User::factory()->pengelola()->create();

    $hitung = function () use ($pengelola) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        halamanVerifikasi($pengelola);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-01 08:00', periode: '2026-10-01', jatuhTempo: '2026-10-10');
    siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-02 08:00');
    $hitung(); // pemanasan
    $sedikit = $hitung();

    foreach (range(3, 8) as $n) {
        siapkanBuktiMenunggu('0'.$n, 'Penghuni '.$n, '2026-11-03 0'.$n.':00');
    }

    expect($hitung())->toBe($sedikit);
});

it('panel gambar: pratinjau dari route bukti, keterangan jenis dan ukuran, serta tautan ukuran penuh', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-01 19:42', 'jpg');

    $komponen = halamanVerifikasi()
        ->assertSee('Kamar 02, tagihan November')
        ->assertSee('Rina Maharani')
        ->assertSee('Menunggu dicek')
        ->assertSee('Nominal tagihan')
        ->assertSee('Rp1.250.000')
        ->assertSee('5 November 2026')
        ->assertSee('1 November, 19.42')
        ->assertSee('10 November')
        ->assertSee('Setelah diterima, tagihan jadi lunas dan dicatat ke Odoo.')
        ->assertSee('Terima pembayaran')
        ->assertSee('Tolak bukti')
        ->assertSee('alt="Bukti transfer Kamar 02 November"', false)
        ->assertSee('Buka ukuran penuh')
        ->assertSee('target="_blank" rel="noopener"', false);

    expect($komponen->html())->toContain('<img')
        ->and($komponen->html())->toContain('src="'.route('bukti-bayar.tampil', $p->id).'"')
        ->and($komponen->html())->toMatch('#JPG, \d+ KB#');
});

it('panel PDF: kotak "Berkas PDF" tanpa embed', function () {
    siapkanBuktiMenunggu('02', 'Rina Maharani', berkas: 'pdf');

    $html = halamanVerifikasi()->assertSee('Berkas PDF')->assertSee('Buka ukuran penuh')->html();

    expect($html)->not->toContain('<img')->not->toContain('<embed')->not->toContain('<iframe')
        ->and($html)->toMatch('#PDF, \d+ KB#');
});

it('panel: bukti migrasi tanpa berkas tampil "Bukti tidak tersedia." tanpa tautan', function () {
    siapkanBuktiMenunggu('02', 'Rina Maharani', berkas: null);

    halamanVerifikasi()
        ->assertSee('Bukti tidak tersedia.')
        ->assertDontSee('Buka ukuran penuh')
        ->assertDontSee('<img', false);
});

it('panel: berkas yang hilang dari disk tampil "Bukti tidak tersedia."', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    Storage::disk('local')->delete($p->bukti_bayar_path);

    halamanVerifikasi()->assertSee('Bukti tidak tersedia.')->assertDontSee('Buka ukuran penuh');
});

it('dialog terima dan tolak memuat ringkasan, akibat, dan field alasan sesuai spesifikasi', function () {
    siapkanBuktiMenunggu('02', 'Rina Maharani');

    halamanVerifikasi()
        ->assertSee('Terima pembayaran Kamar 02 November?')
        ->assertSee('Tagihan menjadi lunas.')
        ->assertSee('Pembayaran masuk riwayat transaksi.')
        ->assertSee('Transaksi diantrekan untuk dicatat ke Odoo.')
        ->assertSee('Tolak bukti Kamar 02 November?')
        ->assertSee('Alasan penolakan')
        ->assertSee('Alasan ini tampil di Tagihan saya milik penghuni. Contoh: Nominal transfer kurang Rp100.000.')
        ->assertSee('Tagihan kembali belum dibayar.')
        ->assertSee('Penghuni bisa mengunggah bukti baru.');
});

it('F-13: pemilik, penghuni, dan vendor mendapat 403 di /verifikasi-pembayaran', function (string $peran) {
    siapkanBuktiMenunggu('02', 'Rina Maharani');

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/verifikasi-pembayaran')
        ->assertForbidden();

    Livewire::actingAs(User::factory()->{$peran}()->create())
        ->test('pages::verifikasi-pembayaran.index')
        ->assertForbidden();
})->with(['pemilik', 'penghuni', 'vendor']);

it('F-13: aksi terima, tolak, dan buka-tolak langsung ditolak untuk peran selain pengelola', function (string $peran) {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $pengelola = User::factory()->pengelola()->create();
    $penyusup = User::factory()->{$peran}()->create();

    // Komponen dibuka pengelola, lalu sesi berganti ke peran lain; satu komponen baru per aksi
    // karena respons 403 tidak memperbarui snapshot.
    foreach (['terima', 'tolak', 'bukaTolak', 'pilih'] as $aksi) {
        $komponen = halamanVerifikasi($pengelola);
        $this->actingAs($penyusup);

        $komponen->set('alasan', 'Nominal tidak sesuai')->call($aksi, $p->id)->assertForbidden();

        $this->actingAs($pengelola);
    }

    $p->refresh();
    expect($p->status_verifikasi->value)->toBe('menunggu')
        ->and($p->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi)
        ->and(SinkronisasiErp::query()->count())->toBe(0);
})->with(['pemilik', 'penghuni', 'vendor']);

it('tamu diarahkan ke login dari halaman verifikasi', function () {
    $this->get('/verifikasi-pembayaran')->assertRedirect(route('login'));
});
