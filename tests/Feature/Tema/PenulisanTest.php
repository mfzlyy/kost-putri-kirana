<?php

use App\Enums\PeranPengguna;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\MenuNavigasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/*
| Aturan penulisan (docs/design/DESIGN.md, bagian Penulisan): judul = label menu, tanpa deskripsi di bawah judul,
| Title Case untuk label, kolom, chip, status, dan tombol.
*/

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Teks h1 pertama di halaman, tanpa tag dan spasi berlebih.
 */
function teksH1(string $html): string
{
    preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $cocok);

    return trim(preg_replace('/\s+/', ' ', strip_tags($cocok[1] ?? '')));
}

dataset('peran_dan_menu', function () {
    foreach (PeranPengguna::cases() as $peran) {
        foreach (MenuNavigasi::untuk($peran) as $item) {
            yield "{$peran->value}: {$item['label']}" => [$peran->value, $item['route'], $item['label']];
        }
    }
});

it('judul setiap halaman menu = label menu, tanpa deskripsi di bawah judul', function (string $peran, string $route, string $label) {
    // Halaman penghuni butuh data penghuni; peran lain cukup akun.
    $user = $peran === 'penghuni'
        ? buatKontrakUji('05', 'Maya Sari')->penghuni->user
        : User::factory()->{$peran}()->create();

    $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();

    expect(teksH1($html))->toBe($label)
        ->and($html)->not->toContain('data-test="deskripsi-judul"');
})->with('peran_dan_menu');

it('halaman form dan subhalaman memakai judul Title Case tanpa deskripsi', function (string $peran, string $url, string $judul) {
    $user = $peran === 'penghuni' ? buatKontrakUji('05', 'Maya Sari')->penghuni->user : User::factory()->{$peran}()->create();

    $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

    expect(teksH1($html))->toBe($judul)
        ->and($html)->not->toContain('data-test="deskripsi-judul"');
})->with([
    ['pengelola', '/kamar/tambah', 'Tambah Kamar'],
    ['pengelola', '/penghuni/tambah', 'Tambah Penghuni'],
    ['pengelola', '/kontrak/buat', 'Buat Kontrak'],
    ['penghuni', '/settings/security', 'Ganti Password'],
]);

it('halaman unggah berjudul Unggah Bukti tanpa deskripsi dan tanpa catatan', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $tagihan = Tagihan::factory()->create(['kontrak_id' => $kontrak->id]);

    $html = $this->actingAs($kontrak->penghuni->user)->get(route('tagihan-saya.unggah', $tagihan))->assertOk()->getContent();

    expect(teksH1($html))->toBe('Unggah Bukti')
        ->and($html)->not->toContain('data-test="deskripsi-judul"')
        ->and($html)->not->toContain('Setelah terkirim')
        ->and($html)->not->toContain('Satu bukti untuk satu tagihan');
});

it('halaman login berjudul Masuk tanpa kalimat di bawah judul', function () {
    $this->get('/login')->assertOk()
        ->assertSee('Masuk')
        ->assertDontSee('Pakai email dan password dari pengelola.');
});

it('chip Semua adalah chip pertama dan berangka di Penghuni, Kontrak, dan Tagihan', function (string $url) {
    buatKontrakUji('01', 'Anisa Putri');

    $html = $this->actingAs(User::factory()->pengelola()->create())->get($url)->assertOk()->getContent();

    preg_match_all('#<button[^>]*aria-pressed="(true|false)"[^>]*>(.*?)</button>#s', $html, $chip, PREG_SET_ORDER);
    $teks = array_map(fn ($c) => trim(preg_replace('/\s+/', ' ', strip_tags($c[2]))), $chip);

    expect($teks)->not->toBeEmpty()
        ->and($teks[0])->toMatch('/^Semua \d+$/');
})->with(['/penghuni', '/kontrak', '/tagihan']);

it('chip bawaan Penghuni dan Kontrak tetap Aktif dan terpilih saat dibuka', function (string $url, string $label) {
    buatKontrakUji('01', 'Anisa Putri');

    $html = $this->actingAs(User::factory()->pengelola()->create())->get($url)->assertOk()->getContent();

    preg_match_all('#<button[^>]*aria-pressed="(true|false)"[^>]*>(.*?)</button>#s', $html, $chip, PREG_SET_ORDER);
    $terpilih = collect($chip)->filter(fn ($c) => $c[1] === 'true')->map(fn ($c) => trim(preg_replace('/\s+/', ' ', strip_tags($c[2]))))->values()->all();

    expect($terpilih)->toBe([$label]);
})->with([
    ['/penghuni', 'Aktif 1'],
    ['/kontrak', 'Aktif 1'],
]);

it('chip Penghuni berurutan Semua, Aktif, Keluar; Kontrak Semua, Aktif, Berakhir; Tagihan Semua, Telat, Belum Bayar, Menunggu, Lunas', function () {
    $this->actingAs(User::factory()->pengelola()->create());

    foreach ([
        '/penghuni' => ['Semua', 'Aktif', 'Keluar'],
        '/kontrak' => ['Semua', 'Aktif', 'Berakhir'],
        '/tagihan' => ['Semua', 'Telat', 'Belum Bayar', 'Menunggu', 'Lunas'],
    ] as $url => $urutan) {
        $html = $this->get($url)->getContent();
        preg_match_all('#<button[^>]*aria-pressed="(?:true|false)"[^>]*>(.*?)</button>#s', $html, $chip);
        $label = array_map(fn ($t) => preg_replace('/\s*\d+$/', '', trim(preg_replace('/\s+/', ' ', strip_tags($t)))), $chip[1]);

        expect($label)->toBe($urutan);
    }
});

it('angka antrean Verifikasi memakai gaya aktif di halamannya dan gaya biasa di halaman lain', function () {
    siapkanBuktiMenunggu('02', 'Rina Maharani');
    $pengelola = User::factory()->pengelola()->create();

    $aktif = $this->actingAs($pengelola)->get('/verifikasi-pembayaran')->getContent();
    $lain = $this->actingAs($pengelola)->get('/tagihan')->getContent();

    $angka = fn (string $html) => (function () use ($html) {
        preg_match('#<span\s+x-data="\{ n: \d+ \}".*?data-test="angka-antrean"#s', $html, $cocok);

        return $cocok[0] ?? '';
    })();

    expect($angka($aktif))->toContain('bg-primary')->toContain('teks-pil-aktif')->not->toContain('bg-primary-container/[.14]')
        ->and($angka($lain))->toContain('bg-primary-container/[.14]')->not->toContain('teks-pil-aktif')
        ->and($angka($aktif))->toContain('h-[22px]')->toContain('rounded-full')->toContain('teks-pil')->toContain('flex-none');
    expect($aktif)->toContain('1 bukti menunggu');
});

it('label menu sidebar satu baris dengan title lengkap', function () {
    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/kamar')->getContent();

    expect($html)->toContain('title="Verifikasi"')
        ->and($html)->toContain('whitespace-nowrap');
});

it('tombol utama memakai satu kata Title Case', function () {
    $pengelola = User::factory()->pengelola()->create();

    $this->actingAs($pengelola)->get('/kamar')->assertSee('data-test="tambah-kamar"', false);

    foreach (['/kamar' => 'Tambah', '/penghuni' => 'Tambah', '/kontrak' => 'Buat', '/akun' => 'Tambah'] as $url => $kata) {
        $html = $this->get($url)->getContent();

        expect(preg_match('#<(?:a|button)[^>]*data-test="(?:tambah-kamar|tambah-penghuni|buat-kontrak|tambah-akun-button)"[^>]*>\s*'.$kata.'\s*</(?:a|button)>#s', $html))->toBe(1);
    }
});

it('Verifikasi: tombol Terima, Tolak, dan Buka satu kata; dialog berjudul Title Case', function () {
    siapkanBuktiMenunggu('02', 'Rina Maharani');

    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/verifikasi-pembayaran')->getContent();

    expect($html)->toMatch('#data-test="buka-terima">Terima</button>#')
        ->and($html)->toMatch('#data-test="buka-tolak"\s*>Tolak</button>#')
        ->and($html)->toContain('>Buka</a>')
        ->and($html)->toContain('Terima Pembayaran Kamar 02?')
        ->and($html)->toContain('Tolak Bukti Kamar 02?');
    expect(Pembayaran::query()->menunggu()->count())->toBe(1);
});
