<?php

use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

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
        ['kunci' => 'b', 'label' => 'Tunggakan', 'nilai' => 'Rp500.000', 'keterangan' => '2 tagihan', 'nada' => 'bahaya'],
        ['kunci' => 'c', 'label' => 'Lunas', 'nilai' => 'Rp400.000'],
    ]]);

    expect(substr_count($html, 'data-test="ringkasan-a"'))->toBe(1)
        ->and(substr_count($html, 'data-test="ringkasan-b"'))->toBe(1)
        ->and(substr_count($html, 'data-test="ringkasan-c"'))->toBe(1)
        ->and(substr_count($html, 'data-nada="bahaya"'))->toBe(1)
        ->and($html)->toContain('--kolom: 3;')
        ->and($html)->toContain('2 tagihan')
        ->and($html)->toContain('aria-label="Ringkasan"')
        ->and($html)->toContain('ringkasan-nilai')
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

it('Tagihan: pemilih periode dan tombol Terbitkan ada di kepala halaman', function () {
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

it('CSS mendefinisikan token tipografi dan ukuran kontrol sekali di app.css', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach (['teks-judul', 'teks-judul-dialog', 'teks-bagian', 'teks-angka-utama', 'teks-angka', 'teks-nomor-kamar', 'teks-badan', 'teks-badan-kuat', 'teks-label', 'teks-kolom', 'teks-bantuan', 'teks-isian', 'teks-tombol', 'teks-tombol-kecil', 'teks-chip', 'teks-menu', 'teks-status'] as $token) {
        expect($css)->toContain("@utility {$token} {");
    }

    expect($css)->toContain('height: 44px')->toContain('min-height: 96px')->toContain('clamp(20px, 2vw, 24px)');
});

it('14E: teks-angka-utama memakai satuan cqi dan dibungkus container di tiga halaman', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/@utility teks-angka-utama \{(.*?)\n\}/s', $css, $token);

    expect($token[1])->toContain('clamp(24px, 11cqi, 40px)')
        ->and($token[1])->toContain('white-space: nowrap')
        ->and($token[1])->not->toContain('34px');

    foreach (['verifikasi-pembayaran/⚡index', 'tagihan-saya/⚡index', 'tagihan-saya/⚡unggah'] as $halaman) {
        expect(file_get_contents(resource_path("views/pages/{$halaman}.blade.php")))->toContain('@container');
    }
});

it('teks-isian 15px hanya di laptop dengan pointer halus; 16px bawaan untuk input, select, dan textarea', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/@utility teks-isian \{(.*?)\n\}/s', $css, $token);

    expect($token[1])->toContain('font-size: 16px')
        ->and($token[1])->toContain('@media (min-width: 1024px) and (pointer: fine)')
        ->and($token[1])->toMatch('/@media \(min-width: 1024px\) and \(pointer: fine\) \{\s*font-size: 15px;/');

    // Aturan global untuk input, select, dan textarea juga 15px hanya di dalam media yang sama.
    preg_match('/@media \(min-width: 1024px\) and \(pointer: fine\) \{\s*input:not.*?select,\s*textarea \{\s*font-size: 15px;/s', $css, $global);

    expect($global)->not->toBeEmpty();
});

it('select setinggi 44px sama dengan input, tanpa padding vertikal bawaan Flux', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toMatch('/select\[data-flux-select-native\] \{\s*height: 44px !important;\s*min-height: 44px !important;\s*max-height: 44px !important;\s*padding-block: 0 !important;/');
});

it('tabel Penghuni dan Kontrak tidak menggulirkan halaman: teks sr-only tertahan dan nama boleh turun baris', function (string $url) {
    buatKontrakUji('01', 'Anindya Putri Rahmawati Kusumaningrum');

    $html = $this->actingAs(User::factory()->pengelola()->create())->get($url)->assertOk()->getContent();

    expect($html)->toMatch('#<div class="relative hidden overflow-x-auto [^"]*">\s*<table\b#');

    preg_match('#<td\b([^>]*)>\s*Anindya Putri Rahmawati Kusumaningrum\s*</td>#', $html, $sel);

    expect($sel)->not->toBeEmpty()
        ->and($sel[1])->not->toContain('whitespace-nowrap');
})->with(['/penghuni', '/kontrak']);

it('20D: setiap tabel data memakai table-fixed dengan colgroup selebar jumlah kolomnya', function (string $peran, string $url) {
    $kontrak = buatKontrakUji('01', 'Anisa Putri');
    buatPembayaranDiterima($kontrak, '2026-10-01', '2026-10-05', '2026-10-06 10:00');
    Tagihan::factory()->create(['kontrak_id' => $kontrak->id, 'periode' => now()->startOfMonth()->toDateString()]);
    $user = $peran === 'penghuni' ? $kontrak->penghuni->user : User::factory()->{$peran}()->create();
    SinkronisasiErp::factory()->create();

    $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

    preg_match_all('#<table\b.*?</table>#s', $html, $tabel);
    expect($tabel[0])->not->toBeEmpty("Tidak ada tabel di {$url}");

    foreach ($tabel[0] as $satu) {
        preg_match('#^<table\b[^>]*class="([^"]*)"#', $satu, $kelas);
        preg_match('#<colgroup>(.*?)</colgroup>#s', $satu, $kolom);
        preg_match('#<thead>(.*?)</thead>#s', $satu, $kepala);

        expect($kelas[1] ?? '')->toContain('table-fixed')
            ->and($kolom)->not->toBeEmpty("Tanpa colgroup di {$url}")
            ->and(preg_match_all('#<col[\s>]#', $kolom[1]))->toBe(preg_match_all('#<th[\s>]#', $kepala[1]));
    }
})->with([
    ['pengelola', '/tagihan'],
    ['pemilik', '/tagihan'],
    ['pengelola', '/riwayat-transaksi'],
    ['penghuni', '/riwayat-transaksi'],
    ['pengelola', '/kontrak'],
    ['pemilik', '/kontrak'],
    ['pengelola', '/penghuni'],
    ['pemilik', '/penghuni'],
    ['pengelola', '/kamar'],
    ['pemilik', '/kamar'],
    ['pengelola', '/akun'],
    ['pengelola', '/sinkronisasi-erp?status=semua'],
    ['penghuni', '/tagihan-saya'],
]);

it('20D: tidak ada tabel di view yang luput dari table-fixed', function () {
    foreach (File::allFiles(resource_path('views/pages')) as $berkas) {
        $isi = $berkas->getContents();

        expect(substr_count($isi, '<colgroup>'))->toBe(substr_count($isi, '<table '), $berkas->getRelativePathname())
            ->and(substr_count($isi, 'table-fixed'))->toBeGreaterThanOrEqual(substr_count($isi, '<table '), $berkas->getRelativePathname());
    }
});

it('x-ringkasan-angka adalah container dengan tata letak berbasis lebar kartu', function () {
    $html = Blade::render('<x-ringkasan-angka :items="$items" />', ['items' => [
        ['label' => 'A', 'nilai' => '1'], ['label' => 'B', 'nilai' => '2'], ['label' => 'C', 'nilai' => '3'],
        ['label' => 'D', 'nilai' => '4'], ['label' => 'E', 'nilai' => '5', 'nada' => 'bahaya'],
    ]]);
    $css = file_get_contents(resource_path('css/app.css'));

    // Kartu = container; isinya memakai kelas tata letak yang diatur lewat container query.
    expect($html)->toContain('class="ringkasan ')
        ->and($html)->toContain('class="ringkasan-isi"')
        ->and($html)->toContain('--kolom: 5; --kolom-md: 3;')
        ->and($html)->toContain('x-bind:data-turun="turun"')
        ->and($html)->toContain('ringkasan-nilai')
        ->and($html)->not->toContain('lg:grid-cols')
        ->and($html)->not->toContain('md:grid-cols');

    expect($css)->toMatch('/\.ringkasan \{\s*container-type: inline-size;/')
        ->and($css)->toContain('@container (min-width: 600px)')
        ->and($css)->toContain('@container (min-width: 1000px)')
        // Kolom sama lebar dan tetap: posisi sel tidak mengikuti panjang nilai.
        ->and($css)->toContain('repeat(var(--kolom), minmax(0, 1fr))')
        ->and($css)->toContain('repeat(var(--kolom-md), minmax(0, 1fr))')
        ->and($css)->not->toContain('minmax(max-content')
        ->and($html)->toContain('.ringkasan-nilai')
        ->and($css)->toContain('clamp(18px, 2.1cqi, 24px)');
});

it('sel bahaya menghitung garis dan padding kiri di dalam kolomnya dan tanpa gulir horizontal', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/\.ringkasan-isi:not\(\[data-turun=\'2\'\]\) > \.ringkasan-sel\[data-nada=\'bahaya\'\] \{(.*?)\}/s', $css, $bahaya);

    expect($bahaya[1])->toContain('padding-inline-start: 24px')->toContain('border-inline-start: 1px solid')
        ->and($bahaya[1])->not->toContain('margin')
        ->and($css)->toContain("[data-turun='2']")
        ->and($css)->not->toMatch('/\.ringkasan[^{]*\{[^}]*overflow-x:\s*(?:auto|scroll)/');

    $html = Blade::render('<x-ringkasan-angka :items="$items" />', ['items' => [['label' => 'A', 'nilai' => '1']]]);

    // Skrip pengaman: bila kolom tetap tidak muat, kartu turun ke tiga kolom lalu daftar.
    expect($html)->toContain('scrollWidth')->toContain('turun = 1')->toContain('turun = 2')->toContain('ResizeObserver');
});
