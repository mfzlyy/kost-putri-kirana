<?php

use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTiket;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Satu sistem tombol, ukuran huruf, chip filter, dan pemilih periode (docs/design/DESIGN.md, Typography dan Components).
*/

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Semua view buatan aplikasi. Dikecualikan: view vendor, override Flux (resources/views/flux), dan email.
 *
 * @return array<string, string> path relatif => isi
 */
function viewAplikasi(): array
{
    $hasil = [];

    foreach (File::allFiles(resource_path('views')) as $berkas) {
        $nama = str_replace('\\', '/', $berkas->getRelativePathname());

        if (str_ends_with($nama, '.blade.php') && ! preg_match('#^(vendor|flux|emails?|mail)/#', $nama)) {
            $hasil[$nama] = $berkas->getContents();
        }
    }

    return $hasil;
}

/**
 * Setiap elemen yang dirender x-tombol: daftar ['tag', 'varian', 'ukuran', 'atribut', 'teks'].
 *
 * @return list<array<string, string>>
 */
function daftarTombol(string $html): array
{
    preg_match_all('#<(button|a|span)\s[^<>]*?data-varian="([a-z-]+)"\s+data-ukuran="([a-z]+)"([^<>]*)>(.*?)</\1>#s', $html, $cocok, PREG_SET_ORDER);

    return array_map(fn (array $c) => [
        'tag' => $c[1],
        'varian' => $c[2],
        'ukuran' => $c[3],
        'atribut' => $c[4],
        'teks' => trim(preg_replace('/\s+/', ' ', strip_tags($c[5]))),
    ], $cocok);
}

/**
 * Pasangan "varian ukuran" yang dipakai semua tombol berteks $teks.
 *
 * @return list<string>
 */
function gayaTombol(string $html, string $teks): array
{
    return collect(daftarTombol($html))
        ->where('teks', $teks)
        ->map(fn (array $t) => $t['varian'].' '.$t['ukuran'])
        ->unique()
        ->values()
        ->all();
}

it('view tidak menulis ukuran huruf, tinggi baris, atau berat huruf lepas', function () {
    $pola = [
        'ukuran huruf' => '/(?<![\w-])text-(?:xs|sm|base|lg|xl|[0-9]xl|\[)/',
        'tinggi baris' => '/(?<![\w-])leading-[a-z0-9\[]/',
        'berat huruf' => '/(?<![\w-])font-(?:thin|extralight|light|normal|medium|semibold|bold|extrabold|black|\[)/',
    ];

    expect(viewAplikasi())->not->toBeEmpty();

    foreach (viewAplikasi() as $nama => $isi) {
        foreach ($pola as $jenis => $regex) {
            preg_match($regex, $isi, $temuan);

            expect($temuan)->toBe([], "Kelas {$jenis} lepas di {$nama}");
        }
    }
});

it('tidak ada teks di bawah 12px dan token baru terdefinisi di app.css', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $css, $ukuran);

    expect(min(array_map('floatval', $ukuran[1])))->toBeGreaterThanOrEqual(12.0);

    foreach (['teks-peran', 'teks-tab', 'teks-pil-aktif', 'teks-merek', 'teks-merek-atas', 'teks-merek-kecil', 'teks-hero'] as $token) {
        expect($css)->toContain("@utility {$token} {");
    }

    expect($css)->toMatch('/\[data-flux-callout\] \[data-slot=\'text\'\] \{\s*font-size: 15px;/')
        ->and($css)->toMatch('/\[data-flux-pagination\] \.text-xs \{\s*font-size: 13px;/');
});

it('tombol aksi hanya dirender lewat x-tombol: tanpa flux:button dan tanpa kelas tombol di view', function () {
    foreach (viewAplikasi() as $nama => $isi) {
        // x-tombol sendiri; passkey-registration adalah sisa starter kit yang tidak dirender halaman mana pun.
        if (in_array($nama, ['components/tombol.blade.php', 'components/passkey-registration.blade.php'], true)) {
            continue;
        }

        expect(str_contains($isi, '<flux:button'))->toBeFalse("flux:button di {$nama}")
            ->and(preg_match('/class[^\n]*(?<![\w-])(?:tombol-kecil|tombol-(?:utama|sekunder|positif|bahaya)|tombol )/', $isi))->toBe(0, "Kelas tombol di {$nama}");

        // Tombol mentah hanya boleh berupa chip, tombol ikon, atau baris navigasi "Keluar".
        preg_match_all('#<button\b(?:[^>]|->|=>)*?class="([^"]*)"#s', $isi, $mentah);

        foreach ($mentah[1] as $kelas) {
            expect($kelas)->toMatch('/tombol-ikon|sorot-gelap|tautan-sidebar|tautan-bahaya/', "Tombol mentah di {$nama}");
        }
    }
});

it('x-tombol merender kelas varian dan ukuran, tautan bila ada href, dan menolak nilai lain', function () {
    foreach (['utama', 'sekunder', 'positif', 'bahaya', 'bahaya-penuh'] as $varian) {
        $normal = Blade::render('<x-tombol :varian="$v">Aksi</x-tombol>', ['v' => $varian]);
        $kecil = Blade::render('<x-tombol :varian="$v" ukuran="kecil">Aksi</x-tombol>', ['v' => $varian]);

        expect(kelasElemen($normal, 'data-varian="'.$varian.'"'))->toBe("tombol tombol-{$varian}")
            ->and(kelasElemen($kecil, 'data-varian="'.$varian.'"'))->toBe("tombol-kecil tombol-{$varian}")
            ->and($normal)->toContain('type="button"');
    }

    expect(Blade::render('<x-tombol href="/kamar">Ubah</x-tombol>'))->toMatch('#<a\s+href="/kamar"#')->not->toContain('type=')
        ->and(Blade::render('<x-tombol wire:click="aktifkan(3)">Aktifkan</x-tombol>'))
        ->toContain('wire:loading.attr="data-sibuk"')
        ->toContain('wire:target="aktifkan(3)"')
        ->and(Blade::render('<x-tombol :disabled="false">Simpan</x-tombol>'))->not->toContain('disabled')
        ->and(fn () => Blade::render('<x-tombol varian="ghost">x</x-tombol>'))->toThrow(Exception::class)
        ->and(fn () => Blade::render('<x-tombol ukuran="sm">x</x-tombol>'))->toThrow(Exception::class);
});

it('CSS: tinggi 44px dan 36px, varian dari token, hover positif dan bahaya memakai container', function () {
    $css = cssAplikasi();
    $hover = blokCss($css, '@media (hover: hover)');

    expect(blokCss($css, '@utility tombol'))->toContain('height: 44px')->toContain('border-radius: 8px')->toContain('font-size: 15px')
        ->and(blokCss($css, '@utility tombol-kecil'))->toContain('height: 36px')->toContain('font-size: 14px')
        ->and($css)->toMatch('/\.tombol-positif \{\s*border: 1px solid var\(--color-primary\);\s*background-color: var\(--color-surface\);\s*color: var\(--color-primary\);/')
        ->and($css)->toMatch('/\.tombol-bahaya \{\s*border: 1px solid var\(--color-danger\);\s*background-color: var\(--color-surface\);\s*color: var\(--color-danger\);/')
        ->and($css)->toMatch('/\.tombol-bahaya-penuh \{\s*background-color: var\(--color-danger\);\s*color: #ffffff;/')
        ->and($hover)->toMatch('/\.tombol-positif:not\(:disabled\):hover \{[^}]*background-color: var\(--color-primary-container\);/')
        ->and($hover)->toMatch('/\.tombol-bahaya:not\(:disabled\):hover \{[^}]*background-color: var\(--color-danger-container\);/');
});

it('Akun: Aktifkan positif kecil dan Nonaktifkan bahaya kecil, selebar yang sama di tabel', function () {
    User::factory()->penghuni()->create();
    User::factory()->vendor()->nonaktif()->create();

    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/akun')->assertOk()->getContent();
    preg_match('#<table\b.*?</table>#s', $html, $tabel);

    expect(gayaTombol($tabel[0], 'Aktifkan'))->toBe(['positif kecil'])
        ->and(gayaTombol($tabel[0], 'Nonaktifkan'))->toBe(['bahaya kecil'])
        ->and(collect(daftarTombol($tabel[0]))->every(fn ($t) => $t['tag'] === 'button'))->toBeTrue()
        ->and(substr_count($tabel[0], 'min-w-30'))->toBe(count(daftarTombol($tabel[0])))
        ->and(gayaTombol($html, 'Tambah'))->toBe(['utama normal']);

    // Di dalam dialog yang sama kata "Nonaktifkan" menjadi tombol konfirmasi merah penuh.
    preg_match('#<dialog\b[^>]*data-modal="nonaktifkan-akun".*?</dialog>#s', $html, $dialog);

    expect(gayaTombol($dialog[0], 'Nonaktifkan'))->toBe(['bahaya-penuh normal'])
        ->and(gayaTombol($dialog[0], 'Batal'))->toBe(['sekunder normal'])
        ->and(gayaTombol(str_replace($dialog[0], '', $html), 'Nonaktifkan'))->toBe(['bahaya kecil']);
});

it('Kontrak: Perpanjang sekunder kecil dan Akhiri bahaya kecil', function () {
    buatKontrakUji('01', 'Anisa Putri');

    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/kontrak')->assertOk()->getContent();
    preg_match('#<dialog\b[^>]*data-modal="akhiri-kontrak".*?</dialog>#s', $html, $dialog);
    $daftar = str_replace($dialog[0], '', $html);

    expect(gayaTombol($daftar, 'Perpanjang'))->toBe(['sekunder kecil'])
        ->and(gayaTombol($daftar, 'Akhiri'))->toBe(['bahaya kecil'])
        ->and(gayaTombol($daftar, 'Buat'))->toBe(['utama normal'])
        ->and($html)->not->toContain('tautan-aksi')
        ->and($html)->not->toContain('tautan-bahaya');
});

it('Kamar dan Penghuni: Ubah sekunder kecil, Hapus bahaya kecil', function () {
    buatKontrakUji('01', 'Anisa Putri');
    $pengelola = User::factory()->pengelola()->create();

    $kamar = $this->actingAs($pengelola)->get('/kamar')->assertOk()->getContent();
    $penghuni = $this->actingAs($pengelola)->get('/penghuni')->assertOk()->getContent();

    expect(gayaTombol($kamar, 'Ubah'))->toBe(['sekunder kecil'])
        ->and(gayaTombol($penghuni, 'Ubah'))->toBe(['sekunder kecil']);

    preg_match('#<table\b.*?</table>#s', $kamar, $tabel);

    foreach (collect(daftarTombol($tabel[0]))->where('teks', 'Hapus') as $hapus) {
        expect($hapus['varian'].' '.$hapus['ukuran'])->toBe('bahaya kecil');
    }
});

it('Verifikasi, Sinkronisasi, dan Pekerjaan vendor memakai varian yang ditetapkan', function () {
    $bukti = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $pengelola = User::factory()->pengelola()->create();

    $verifikasi = Livewire::actingAs($pengelola)->test('pages::verifikasi-pembayaran.index')->call('pilih', $bukti->id)->html();
    preg_match('#<section\b[^>]*data-test="panel-periksa".*?</section>#s', $verifikasi, $panel);

    expect(gayaTombol($panel[0], 'Terima'))->toBe(['utama normal'])
        ->and(gayaTombol($panel[0], 'Tolak'))->toBe(['bahaya normal']);

    SinkronisasiErp::factory()->create(['status_sinkronisasi' => StatusSinkronisasi::Gagal]);

    $sinkron = Livewire::actingAs($pengelola)->test('pages::sinkronisasi-erp.index')->html();

    expect(collect(daftarTombol($sinkron))->map(fn ($t) => $t['varian'].' '.$t['ukuran'])->unique()->values()->all())
        ->toBe(['sekunder kecil']);

    $vendor = Vendor::factory()->create();
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0001', 'vendor_id' => $vendor->id, 'status_tiket' => StatusTiket::Ditugaskan]);
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0002', 'vendor_id' => $vendor->id, 'status_tiket' => StatusTiket::Dikerjakan]);

    $pekerjaan = Livewire::actingAs($vendor->user)->test('pages::permintaan-kerja.index')->html();

    expect(gayaTombol($pekerjaan, 'Mulai'))->toBe(['utama kecil'])
        ->and(gayaTombol($pekerjaan, 'Selesai'))->toBe(['utama kecil']);
});

it('setiap baris chip memakai x-chip-filter.baris yang bisa digulir tanpa scrollbar', function (string $url, int $baris) {
    $html = $this->actingAs(User::factory()->pengelola()->create())->get($url)->assertOk()->getContent();

    preg_match_all('#<div role="group" aria-label="Saring[^"]*"\s+class="([^"]*)"#', $html, $wadah);

    expect($wadah[1])->toHaveCount($baris);

    foreach ($wadah[1] as $kelas) {
        expect($kelas)->toContain('baris-chip')->toContain('max-sm:overflow-x-auto')->toContain('max-sm:snap-x');
    }

    expect(kelasElemen($html, 'aria-pressed="true"'))->toContain('max-sm:snap-start')->toContain('teks-chip')->toContain('h-9')
        ->and($html)->toContain("scrollIntoView({ inline: 'nearest', block: 'nearest' })")
        ->and($html)->toContain('x-bind:data-pudar-kanan');
})->with([
    'Akun' => ['/akun', 2],
    'Kamar' => ['/kamar', 1],
    'Penghuni' => ['/penghuni', 1],
    'Kontrak' => ['/kontrak', 1],
    'Tagihan' => ['/tagihan', 1],
    'Keluhan' => ['/tiket-keluhan', 1],
    'Sinkronisasi' => ['/sinkronisasi-erp', 1],
]);

it('baris chip tidak ditulis tangan di halaman', function () {
    foreach (viewAplikasi() as $nama => $isi) {
        if (str_starts_with($nama, 'pages/') && str_contains($isi, '<x-chip-filter ')) {
            expect(str_contains($isi, '<x-chip-filter.baris'))->toBeTrue("Baris chip di {$nama}")
                ->and(preg_match('/role="group" aria-label="Saring/', $isi))->toBe(0, "Baris chip tulisan tangan di {$nama}");
        }
    }
});

it('CSS baris chip: scrollbar disembunyikan, melebar sampai tepi, pudar hanya saat ada sisa', function () {
    $css = cssAplikasi();
    $ponsel = blokCss($css, '@media (max-width: 39.999rem)');

    expect(blokCss($css, '.baris-chip'))->toContain('flex-wrap: wrap')->toContain('gap: 8px')->toContain('padding-block: 4px')
        ->and($ponsel)->toContain('flex-wrap: nowrap')
        ->and($ponsel)->toContain('scrollbar-width: none')
        ->and($ponsel)->toMatch('/\.baris-chip::-webkit-scrollbar \{\s*display: none;/')
        ->and($ponsel)->toContain('margin-inline: -16px')
        ->and($ponsel)->toContain('padding-inline: 16px')
        ->and($ponsel)->toContain('mask-image: linear-gradient(')
        ->and($ponsel)->toContain('--pudar-kanan: 0px')
        ->and($ponsel)->toMatch('/\.baris-chip\[data-pudar-kanan\] \{\s*--pudar-kanan: 32px;/');
});

it('20D pemilih periode: dua tombol persegi 40px dan label selebar tetap, dirender x-tombol', function () {
    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/tagihan')->assertOk()->getContent();
    $css = cssAplikasi();

    expect($html)->toContain('role="group" aria-label="Periode"')
        ->and(kelasElemen($html, 'data-test="pemilih-periode"'))->toContain('max-sm:w-full')
        ->and(gayaTombol($html, 'November 2026'))->toBe(['sekunder sedang'])
        ->and(collect(daftarTombol($html))->where('ukuran', 'persegi')->pluck('varian')->all())->toBe(['sekunder', 'sekunder'])
        ->and(blokCss($css, '@utility tombol-sedang'))->toContain('height: 40px')->toContain('font-size: 14px')->toContain('font-weight: 600')
        ->and(blokCss($css, '@utility tombol-persegi'))->toContain('width: 40px')->toContain('height: 40px')
        ->and(blokCss($css, '.pemilih-periode-label'))->toContain('width: 10rem')->toContain('font-variant-numeric: tabular-nums')
        ->and(blokCss($css, '@media (max-width: 39.999rem)'))->toMatch('/\.pemilih-periode-label \{\s*width: 100%;/')
        ->and($css)->toContain('.tombol-persegi:disabled')
        ->and($css)->toContain('scrollbar-gutter: stable');
});

it('20D: komponen segmen periode lama sudah tidak ada', function () {
    expect(File::exists(resource_path('views/components/segmen-periode.blade.php')))->toBeFalse()
        ->and(cssAplikasi())->not->toContain('segmen');

    foreach (viewAplikasi() as $nama => $isi) {
        expect($isi)->not->toContain('x-segmen-periode', $nama);
    }
});
