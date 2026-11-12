<?php

use App\Models\PengaturanOperasional;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

/*
| Kepala halaman seragam, ringkasan angka, dan token tipografi (docs/design/DESIGN.md, Typography dan Layout).
*/

beforeEach(fn () => PengaturanOperasional::factory()->lengkap(10, 30)->create());

/**
 * Isi elemen <header> pertama yang memuat h1 halaman.
 */
function kepalaHalaman(string $html): string
{
    preg_match('#<header\b[^>]*>(?:(?!</header>).)*<h1\b.*?</header>#s', $html, $cocok);

    return $cocok[0] ?? '';
}

it('x-ringkasan-angka merender satu sel per item, item bahaya di akhir dengan penanda gaya bahaya', function () {
    $html = Blade::render('<x-ringkasan-angka label="Ringkasan" :items="$items" />', ['items' => [
        ['kunci' => 'a', 'label' => 'Total', 'nilai' => 'Rp1.000.000'],
        ['kunci' => 'b', 'label' => 'Tunggakan', 'nilai' => 'Rp500.000', 'keterangan' => '2 tagihan, semua periode', 'nada' => 'bahaya'],
        ['kunci' => 'c', 'label' => 'Lunas', 'nilai' => 'Rp400.000'],
    ]]);

    expect(substr_count($html, 'data-test="ringkasan-a"'))->toBe(1)
        ->and(substr_count($html, 'data-test="ringkasan-b"'))->toBe(1)
        ->and(substr_count($html, 'data-test="ringkasan-c"'))->toBe(1)
        ->and(substr_count($html, 'data-nada="bahaya"'))->toBe(1)
        ->and($html)->toContain('--kolom: 3;')
        ->and($html)->toContain('2 tagihan, semua periode')
        ->and($html)->toContain('aria-label="Ringkasan"')
        ->and($html)->toContain('teks-angka')
        ->and($html)->toContain('teks-kolom')
        ->and($html)->toContain('text-status-late');

    // Item bahaya dipindah ke akhir walau diberikan di tengah.
    expect(strpos($html, 'ringkasan-c"'))->toBeLessThan(strpos($html, 'data-nada="bahaya"'));
});

it('x-ringkasan-angka tanpa item bahaya tidak memakai penanda bahaya dan kisi mengikuti jumlah item', function () {
    $html = Blade::render('<x-ringkasan-angka :items="$items" />', ['items' => [
        ['label' => 'Pembayaran', 'nilai' => '5'],
        ['label' => 'Total', 'nilai' => 'Rp7.000.000'],
    ]]);

    expect($html)->not->toContain('data-nada="bahaya"')
        ->and($html)->toContain('--kolom: 2;')
        ->and($html)->toContain('--kolom-md: 2;');
});

it('x-judul-halaman menaruh slot aksi di elemen kepala yang sama dengan h1, rata tengah', function () {
    $html = Blade::render('<x-judul-halaman judul="Kamar"><button>Tambah</button></x-judul-halaman>');

    preg_match('#<header\b([^>]*)>(.*?)</header>#s', $html, $kepala);

    expect($kepala)->not->toBeEmpty()
        ->and($kepala[1])->toContain('items-center')->toContain('justify-between')
        ->and($kepala[2])->toContain('<h1')->toContain('teks-judul')
        ->and($kepala[2])->toContain('data-test="aksi-halaman"')
        ->and($kepala[2])->toContain('<button>Tambah</button>')
        ->and(strpos($kepala[2], '<h1'))->toBeLessThan(strpos($kepala[2], 'data-test="aksi-halaman"'));
});

it('x-judul-halaman tanpa slot tidak merender wadah aksi', function () {
    expect(Blade::render('<x-judul-halaman judul="Riwayat" />'))->not->toContain('aksi-halaman');
});

it('tombol Tambah dan Buat ada di kepala halaman, bukan di baris filter', function (string $url, string $kunci) {
    $html = $this->actingAs(User::factory()->pengelola()->create())->get($url)->assertOk()->getContent();

    $kepala = kepalaHalaman($html);

    expect($kepala)->toContain($kunci);

    // Satu-satunya tombol aksi halaman itu ada di kepala; baris filter (role=group) tidak memuatnya.
    expect(substr_count($html, $kunci))->toBe(1);
    preg_match_all('#<div role="group" aria-label="Saring[^"]*".*?</div>#s', $html, $filter);
    foreach ($filter[0] as $baris) {
        expect($baris)->not->toContain($kunci);
    }
})->with([
    'Kamar' => ['/kamar', 'data-test="tambah-kamar"'],
    'Penghuni' => ['/penghuni', 'data-test="tambah-penghuni"'],
    'Akun' => ['/akun', 'data-test="tambah-akun-button"'],
    'Kontrak' => ['/kontrak', 'data-test="buat-kontrak"'],
]);

it('Tagihan: segmen periode dan tombol Terbitkan ada di kepala halaman', function () {
    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/tagihan')->assertOk()->getContent();
    $kepala = kepalaHalaman($html);

    expect($kepala)->toContain('data-test="pemilih-periode"')
        ->and($kepala)->toContain('data-test="terbitkan-tagihan"')
        ->and($kepala)->toContain('max-sm:w-full');
});

it('Tagihan dan Riwayat memakai x-ringkasan-angka dengan kisi tetap', function () {
    $pengelola = User::factory()->pengelola()->create();

    $tagihan = $this->actingAs($pengelola)->get('/tagihan')->getContent();
    $riwayat = $this->actingAs($pengelola)->get('/riwayat-transaksi')->getContent();

    expect($tagihan)->toContain('--kolom: 5;')
        ->and($tagihan)->toContain('data-nada="bahaya"')
        ->and($tagihan)->toContain('data-test="ringkasan-tunggakan"')
        ->and($riwayat)->toContain('--kolom: 2;');
});

it('view tidak menulis ukuran huruf lepas di halaman dan komponen bersama', function () {
    $berkas = collect(
        File::allFiles(resource_path('views/pages'))
    )->merge(File::files(resource_path('views/components')))
        ->merge(File::allFiles(resource_path('views/components/menu-samping')));

    $terlarang = '/\btext-(?:\[\d+px\]|xs|sm|base|lg|xl|2xl|3xl)\b/';

    foreach ($berkas as $f) {
        $isi = file_get_contents($f->getPathname());

        // Brand (logo, hero login) dan tab bawah 12px boleh punya ukuran sendiri.
        if (in_array($f->getFilename(), ['app-logo.blade.php', 'menu-tab-penghuni.blade.php', 'passkey-registration.blade.php', 'auth-session-status.blade.php'], true)) {
            continue;
        }

        expect(preg_match($terlarang, $isi))->toBe(0, "Ukuran huruf lepas di {$f->getFilename()}");
    }
});

it('CSS mendefinisikan token tipografi dan ukuran kontrol sekali di app.css', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach (['teks-judul', 'teks-judul-dialog', 'teks-bagian', 'teks-angka-utama', 'teks-angka', 'teks-nomor-kamar', 'teks-badan', 'teks-badan-kuat', 'teks-label', 'teks-kolom', 'teks-bantuan', 'teks-isian', 'teks-tombol', 'teks-tombol-kecil', 'teks-chip', 'teks-menu', 'teks-status'] as $token) {
        expect($css)->toContain("@utility {$token} {");
    }

    expect($css)->toContain('height: 44px')->toContain('min-height: 96px')->toContain('clamp(20px, 2vw, 24px)');
});
