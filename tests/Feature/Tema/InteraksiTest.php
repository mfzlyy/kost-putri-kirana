<?php

use App\Enums\StatusTiket;
use App\Models\Kamar;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Kursor, hover, teks peran, teks dialog, dan panel detail (docs/design/DESIGN.md, bagian Interaksi).
*/

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function cssAplikasi(): string
{
    return str_replace("\r\n", "\n", file_get_contents(resource_path('css/app.css')));
}

/**
 * Isi semua blok CSS yang diawali $pembuka (termasuk blok bersarang di dalamnya), digabung.
 */
function blokCss(string $css, string $pembuka): string
{
    $hasil = '';
    $mulai = 0;

    while (($posisi = strpos($css, $pembuka.' {', $mulai)) !== false) {
        $awal = $posisi + strlen($pembuka) + 2;
        $dalam = 1;

        for ($i = $awal; $i < strlen($css) && $dalam > 0; $i++) {
            $dalam += match ($css[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
        }

        $hasil .= substr($css, $awal, $i - $awal - 1);
        $mulai = $i;
    }

    return $hasil;
}

/**
 * Nilai atribut class elemen pertama yang cocok dengan $penanda (regex atribut di tag yang sama).
 */
function kelasElemen(string $html, string $penanda): string
{
    preg_match_all('#<[a-z][^<>]*(?:<[^<>]*>)?[^<>]*>#i', $html, $tag);

    foreach ($tag[0] as $satu) {
        if (preg_match('#'.$penanda.'#', $satu) && preg_match('#\sclass="([^"]*)"#', $satu, $kelas)) {
            return $kelas[1];
        }
    }

    return '';
}

/**
 * Teks yang terlihat di dialog konfirmasi bernama $nama.
 */
function teksDialog(string $html, string $nama): string
{
    preg_match('#<dialog\b[^>]*data-modal="'.preg_quote($nama, '#').'".*?</dialog>#s', $html, $dialog);

    expect($dialog)->not->toBeEmpty("Dialog {$nama} tidak ditemukan.");

    return trim(preg_replace('/\s+/', ' ', strip_tags($dialog[0])));
}

function pastikanBahasaPengguna(string $teks): void
{
    expect($teks)->not->toMatch('/odoo|antre|sinkron/i')
        ->and($teks)->not->toMatch('/\bERP\b|\bjob\b/');
}

function tiketInteraksi(string $nomor, array $atribut = []): TiketKeluhan
{
    return TiketKeluhan::factory()->create(array_merge(['nomor_tiket' => $nomor], $atribut));
}

it('app.css memberi pointer pada elemen klik dan not-allowed pada elemen nonaktif', function () {
    $dasar = blokCss(cssAplikasi(), '@layer base');

    expect($dasar)->toMatch('/button:not\(:disabled\),\s*\[role=\'button\'\]:not\(\[aria-disabled=\'true\'\]\),\s*a\[href\],\s*label\[for\],\s*summary,\s*select:not\(:disabled\),\s*input\[type=\'checkbox\'\]:not\(:disabled\),\s*input\[type=\'radio\'\]:not\(:disabled\),\s*input\[type=\'file\'\]:not\(:disabled\)::file-selector-button \{\s*cursor: pointer;/')
        ->and($dasar)->toMatch('/:disabled,\s*\[aria-disabled=\'true\'\],[^{]*\{\s*cursor: not-allowed;/');

    // Flux mematikan pointer-events tombol nonaktif; tanpa ini kursor not-allowed tidak pernah tampil.
    expect(cssAplikasi())->toMatch('/\[data-flux-button\]:disabled \{\s*cursor: not-allowed;\s*pointer-events: auto;/');

    // Utilitas tombol tidak memaksakan pointer pada tombol nonaktif.
    preg_match_all('/@utility tombol(?:-kecil)? \{(.*?)\n\}/s', cssAplikasi(), $utilitas);
    expect($utilitas[1])->toHaveCount(2)
        ->and(implode($utilitas[1]))->not->toContain('cursor');
});

it('setiap jenis elemen klik punya aturan hover di dalam media hover', function (string $pemilih) {
    expect(blokCss(cssAplikasi(), '@media (hover: hover)'))->toContain($pemilih);
})->with([
    '.tombol-utama:not(:disabled):hover',
    '.tombol-sekunder:not(:disabled):hover',
    '.tombol-bahaya:not(:disabled):hover',
    '.tombol-bahaya-penuh:not(:disabled):hover',
    '.tautan-aksi:not(:disabled):hover',
    '.tautan-bahaya:not(:disabled):hover',
    '.tautan-status:hover .teks-status',
    '.tombol-ikon:not(:disabled):hover',
    '.sorot-gelap:hover',
    ".chip[aria-pressed='false']:not(:disabled):hover",
    "a.segmen:not([aria-current='true']):hover",
    'label.pilihan:not(:has(input:checked, input:disabled)):hover',
    '.kartu-klik:hover',
    '.baris-pilih:hover',
    ".menu-sidebar:not([aria-current='page']):hover",
    '.tab-penghuni:hover',
    '.tautan-sidebar:hover',
    '[data-flux-main] a:not([data-flux-button]):hover',
    'textarea:not(:disabled',
]);

it('status tekan berlaku di semua perangkat dan baris tabel yang tidak bisa diklik tidak disorot', function () {
    $css = cssAplikasi();
    $diLuarHover = str_replace(blokCss($css, '@media (hover: hover)'), '', $css);

    foreach (['.tombol-utama:not(:disabled):active', '.tombol-bahaya-penuh:not(:disabled):active', '.baris-pilih:active', '.menu-sidebar:not([aria-current=\'page\']):active', '.tab-penghuni:active'] as $pemilih) {
        expect($diLuarHover)->toContain($pemilih);
    }

    expect($css)->not->toContain('tbody tr:hover')
        ->and($css)->toMatch('/:focus-visible \{\s*outline: 2px solid var\(--color-primary\);\s*outline-offset: 2px;/');
});

it('animasi panel dan transisi hanya aktif bila gerak tidak dikurangi', function () {
    $css = cssAplikasi();
    $bergerak = blokCss($css, '@media (prefers-reduced-motion: no-preference)');

    expect($bergerak)->toContain('.panel-masuk')
        ->and($bergerak)->toContain('animation: panel-masuk 180ms ease-out')
        ->and($css)->toMatch('/@keyframes panel-masuk \{\s*from \{\s*opacity: 0;\s*transform: translateY\(8px\);/')
        ->and(str_replace($bergerak, '', $css))->not->toMatch('/\btransition:|\banimation:/');
});

it('teks peran di sidebar memakai satu warna dengan kontras minimal 4,5:1', function (string $peran) {
    $user = User::factory()->{$peran}()->create();
    $html = $this->actingAs($user)->get(route($user->peran->halamanUtama()))->assertOk()->getContent();

    expect(kelasElemen($html, 'data-test="peran-pengguna"'))
        ->toContain('text-on-sidebar-muted!')
        ->toContain('font-medium!')
        ->toContain('teks-bantuan');

    $warna = function (string $token): float {
        preg_match('/--color-'.$token.': #([0-9a-f]{6});/i', cssAplikasi(), $cocok);
        $kanal = array_map(function (string $heks): float {
            $nilai = hexdec($heks) / 255;

            return $nilai <= 0.04045 ? $nilai / 12.92 : (($nilai + 0.055) / 1.055) ** 2.4;
        }, str_split($cocok[1], 2));

        return 0.2126 * $kanal[0] + 0.7152 * $kanal[1] + 0.0722 * $kanal[2];
    };

    expect(($warna('on-sidebar-muted') + 0.05) / ($warna('sidebar') + 0.05))->toBeGreaterThanOrEqual(4.5);
})->with(['pemilik', 'pengelola', 'penghuni', 'vendor']);

it('chip, menu sidebar, tab penghuni, dan tautan Batal merender kelas interaksinya', function () {
    foreach ([true, false] as $aktif) {
        $chip = Blade::render('<x-chip-filter :aktif="$aktif">Semua</x-chip-filter>', ['aktif' => $aktif]);
        expect(kelasElemen($chip, 'aria-pressed'))->toMatch('/(^|\s)chip(\s|$)/');
    }

    expect(Blade::render('<x-tautan-batal href="/kamar" />'))->toContain('tautan-aksi');

    $penghuni = User::factory()->penghuni()->create();
    $html = $this->actingAs($penghuni)->get('/tagihan-saya')->assertOk()->getContent();

    expect(substr_count($html, 'menu-sidebar '))->toBe(4)
        ->and(substr_count($html, 'tab-penghuni '))->toBe(4)
        ->and(substr_count($html, 'tautan-sidebar'))->toBe(2)
        ->and($html)->toContain('sorot-gelap');
});

it('Tagihan merender segmen periode dan tombol utama; Sinkronisasi dan Akun merender kelas aksinya', function () {
    $pengelola = User::factory()->pengelola()->create();
    User::factory()->penghuni()->create();
    User::factory()->vendor()->nonaktif()->create();

    $tagihan = $this->actingAs($pengelola)->get('/tagihan')->assertOk()->getContent();
    preg_match('#<nav aria-label="Periode".*?</nav>#s', $tagihan, $periode);

    expect(substr_count($periode[0], 'segmen '))->toBe(3)
        ->and(kelasElemen($tagihan, 'data-test="terbitkan-tagihan"'))->toContain('tombol-utama');

    $akun = $this->actingAs($pengelola)->get('/akun')->assertOk()->getContent();

    expect(kelasElemen($akun, 'wire:click="konfirmasiNonaktifkan'))->toContain('tautan-bahaya')
        ->and(kelasElemen($akun, 'wire:click="aktifkan'))->toContain('tautan-aksi')
        ->and(kelasElemen($akun, 'data-test="tambah-akun-button"'))->toContain('tombol-utama');
});

it('halaman tidak menulis hover latar sendiri pada tombol, chip, dan kartu', function () {
    foreach (File::allFiles(resource_path('views/pages')) as $berkas) {
        expect($berkas->getContents())->not->toMatch('/hover:bg-|hover:border-/', $berkas->getFilename());
    }
});

it('kartu antrean Verifikasi dirender sebagai tautan dengan kelas baris-pilih', function () {
    siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');

    $html = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::verifikasi-pembayaran.index')
        ->html();

    expect(preg_match_all('#<a\s[^>]*href="[^"]*"[^>]*class="baris-pilih [^"]*"\s+data-test="item-antrean"#s', $html))->toBe(2)
        ->and(substr_count($html, 'data-test="item-antrean"'))->toBe(2)
        ->and(kelasElemen($html, 'data-test="buka-terima"'))->toContain('tombol-utama')
        ->and(kelasElemen($html, 'data-test="buka-tolak"'))->toContain('tombol-bahaya');
});

it('kartu tiket Keluhan dirender sebagai tautan dan pilihan vendor memakai kelas pilihan', function () {
    Vendor::factory()->create();
    tiketInteraksi('KLH-0001');
    tiketInteraksi('KLH-0002');

    $html = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::tiket-keluhan.index')
        ->html();

    expect(preg_match_all('#<a\s[^>]*href="[^"]*"[^>]*class="baris-pilih [^"]*"\s+data-test="item-tiket"#s', $html))->toBe(2)
        ->and(substr_count($html, 'data-test="item-tiket"'))->toBe(2)
        ->and($html)->toMatch('/class="pilihan [^"]*has-\[:checked\]:border-primary/')
        ->and(kelasElemen($html, 'data-test="tombol-tugaskan"'))->toContain('tombol-utama');
});

it('kartu Pekerjaan vendor bisa diklik; kartu Keluhan penghuni tidak, jadi tanpa hover', function () {
    $vendor = Vendor::factory()->create();
    $penghuni = Penghuni::factory()->create();
    tiketInteraksi('KLH-0001', ['vendor_id' => $vendor->id, 'penghuni_id' => $penghuni->id, 'status_tiket' => StatusTiket::Dikerjakan]);

    $pekerjaan = Livewire::actingAs($vendor->user)->test('pages::permintaan-kerja.index')->html();
    $keluhan = Livewire::actingAs($penghuni->user)->test('pages::keluhan-saya.index')->html();

    expect(kelasElemen($pekerjaan, 'data-test="kartu-tiket"'))->toContain('kartu-klik')
        ->and(kelasElemen($pekerjaan, 'data-test="selesai-tiket"'))->toContain('tombol-sekunder')
        ->and(kelasElemen($keluhan, 'data-test="kartu-tiket"'))->not->toBeEmpty()->not->toContain('kartu-klik');
});

it('panel Verifikasi memakai wire:key berisi id item terpilih', function () {
    $pertama = siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    $kedua = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');

    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::verifikasi-pembayaran.index')
        ->assertSeeHtml('wire:key="panel-'.$pertama->id.'"')
        ->assertSeeHtml('x-data="{ siap: false }"')
        ->assertSeeHtml('siap && $el.classList.add(\'panel-masuk\')')
        ->assertDontSeeHtml('class="panel-masuk');

    expect($komponen->html())->toMatch('#wire:loading\.class="opacity-60"\s+wire:target="pilih"[^>]*data-test="panel-periksa"#s');

    $komponen->call('pilih', $kedua->id)
        ->assertSeeHtml('wire:key="panel-'.$kedua->id.'"')
        ->assertDontSeeHtml('wire:key="panel-'.$pertama->id.'"');
});

it('panel Keluhan memakai wire:key berisi id tiket terpilih', function () {
    $lama = tiketInteraksi('KLH-0001', ['tanggal_lapor' => now()->subDays(2)]);
    $baru = tiketInteraksi('KLH-0002', ['tanggal_lapor' => now()->subDay()]);

    $komponen = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::tiket-keluhan.index')
        ->assertSeeHtml('wire:key="panel-'.$lama->id.'"')
        ->assertSeeHtml('x-data="{ siap: false }"')
        ->assertSeeHtml('siap && $el.classList.add(\'panel-masuk\')');

    expect($komponen->html())->toMatch('#wire:loading\.class="opacity-60"\s+wire:target="pilih"[^>]*data-test="panel-tiket"#s');

    $komponen->call('pilih', $baru->id)
        ->assertSeeHtml('wire:key="panel-'.$baru->id.'"')
        ->assertDontSeeHtml('wire:key="panel-'.$lama->id.'"');
});

it('dialog Terima dan Tolak memakai bahasa pengguna tanpa istilah teknis', function () {
    siapkanBuktiMenunggu('02', 'Rina Maharani');

    $html = Livewire::actingAs(User::factory()->pengelola()->create())
        ->test('pages::verifikasi-pembayaran.index')
        ->html();

    $terima = teksDialog($html, 'terima-pembayaran');
    $tolak = teksDialog($html, 'tolak-bukti');

    expect($terima)->toContain('Tagihan ini akan ditandai lunas dan dicatat ke pembukuan.')
        ->and($tolak)->toContain('Penghuni akan melihat alasan ini dan bisa mengunggah bukti baru.');

    pastikanBahasaPengguna($terima);
    pastikanBahasaPengguna($tolak);
});

it('dialog Selesai vendor memakai bahasa pengguna tanpa istilah teknis', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketInteraksi('KLH-0001', ['vendor_id' => $vendor->id, 'status_tiket' => StatusTiket::Dikerjakan]);

    $html = Livewire::actingAs($vendor->user)
        ->test('pages::permintaan-kerja.detail', ['tiket' => $tiket])
        ->html();

    $selesai = teksDialog($html, 'selesaikan-tiket');

    expect($selesai)->toContain('Pekerjaan akan ditandai selesai dan biaya dicatat. Setelah ini tidak bisa diubah.');
    pastikanBahasaPengguna($selesai);
});

it('dialog lain memakai kalimat biasa dan tombol Batal serta aksi berkelas interaksi', function () {
    $pengelola = User::factory()->pengelola()->create();
    Kamar::factory()->create(['nomor_kamar' => '08']);

    $html = Livewire::actingAs($pengelola)->test('pages::kamar.index')->html();
    pastikanBahasaPengguna(teksDialog($html, 'hapus-kamar'));

    preg_match('#<dialog\b[^>]*data-modal="hapus-kamar".*?</dialog>#s', $html, $dialog);
    expect($dialog[0])->toContain('tombol-sekunder')->toContain('tombol-bahaya-penuh')->not->toContain('<li');

    // Sumbernya juga diperiksa, supaya dialog baru tidak kembali memakai daftar butir atau istilah teknis.
    $jumlah = 0;

    foreach (File::allFiles(resource_path('views/pages')) as $berkas) {
        preg_match_all('#<x-slot:akibat>(.*?)</x-slot:akibat>#s', $berkas->getContents(), $akibat);

        foreach ($akibat[1] as $isi) {
            $jumlah++;
            expect($isi)->not->toContain('<li');
            pastikanBahasaPengguna($isi);
        }
    }

    expect($jumlah)->toBe(9);
});
