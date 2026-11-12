<?php

use App\Enums\StatusTagihan;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Kamar bernomor $nomor yang dihuni $nama lewat kontrak aktif. $kontrak menimpa isian kontrak (tanggal, tarif, status).
 */
function buatKontrakUji(string $nomor, string $nama, array $kontrak = []): Kontrak
{
    $kamar = Kamar::query()->firstOrCreate(
        ['nomor_kamar' => $nomor],
        ['tarif_bulanan' => 1400000],
    );
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => $nama]),
    ]);

    return Kontrak::factory()->create(array_merge([
        'penghuni_id' => $penghuni->id,
        'kamar_id' => $kamar->id,
        'tarif_bulanan' => $kamar->tarif_bulanan,
    ], $kontrak));
}

/**
 * Dasar yang dipenuhi AkunAwalSeeder dan PengaturanOperasionalSeeder sebelum migrasi: baris pengaturan dan satu pengelola aktif.
 */
function siapkanDasarMigrasi(): User
{
    DB::table('pengaturan_operasional')->updateOrInsert(['id' => 1], []);

    return User::factory()->pengelola()->create();
}

function jalurFixtureMigrasi(string $nama): string
{
    return base_path('tests/Fixtures/migrasi/'.$nama);
}

/**
 * Anonimkan fixture mentah (fiktif) ke disk local palsu; hasilnya jalur folder tujuan.
 */
function anonimkanFixtureMigrasi(string $tujuan = 'migrasi/anonim', int $seed = 7): string
{
    $jalur = Storage::disk('local')->path($tujuan);

    Artisan::call('migrasi:anonimkan', ['sumber' => jalurFixtureMigrasi('mentah'), 'tujuan' => $jalur, '--seed' => $seed]);

    return $jalur;
}

/**
 * Isi berkas bukti contoh yang dikenali finfo sebagai JPG, PNG, PDF, atau teks biasa ('txt').
 */
function isiBerkasContoh(string $jenis): string
{
    return match ($jenis) {
        'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        'pdf' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
        'txt' => "ini hanya teks biasa, bukan gambar\n",
    };
}

/**
 * Pembayaran berstatus menunggu untuk tagihan menunggu verifikasi milik penghuni baru di kamar $nomor.
 * Bila $berkas diisi ('jpg', 'png', 'pdf', 'txt'), berkasnya ditulis ke disk local; selain itu path berkas
 * hasil migrasi (tanpa bukti).
 */
function siapkanBuktiMenunggu(
    string $nomor,
    string $nama,
    string $diunggah = '2026-11-04 08:00',
    ?string $berkas = 'jpg',
    string $periode = '2026-11-01',
    string $jatuhTempo = '2026-11-10',
    int $nominal = 1250000,
): Pembayaran {
    $kontrak = buatKontrakUji($nomor, $nama);
    $tagihan = Tagihan::factory()->create([
        'kontrak_id' => $kontrak->id,
        'periode' => $periode,
        'jatuh_tempo' => $jatuhTempo,
        'nominal' => $nominal,
        'status_tagihan' => StatusTagihan::MenungguVerifikasi,
    ]);

    $path = Pembayaran::PATH_TANPA_BUKTI;

    if ($berkas !== null) {
        $path = 'bukti-bayar/'.substr($periode, 0, 4).'/'.substr($periode, 5, 2).'/'.Str::uuid().'.'.$berkas;
        Storage::disk('local')->put($path, isiBerkasContoh($berkas));
    }

    return Pembayaran::factory()->menunggu()->create([
        'tagihan_id' => $tagihan->id,
        'bukti_bayar_path' => $path,
        'diunggah_pada' => Carbon\Carbon::parse($diunggah, 'Asia/Jakarta'),
    ]);
}

/**
 * Pembayaran berstatus diterima atas tagihan lunas milik $kontrak. $sinkron: 'terkirim', 'menunggu', 'gagal',
 * atau null (tanpa baris sinkronisasi, seperti data migrasi).
 */
function buatPembayaranDiterima(
    Kontrak $kontrak,
    string $periode,
    string $tanggalBayar,
    string $diterima,
    int $nominal = 1400000,
    ?User $oleh = null,
    ?string $sinkron = 'terkirim',
): Pembayaran {
    $tagihan = Tagihan::factory()->lunas()->create([
        'kontrak_id' => $kontrak->id,
        'periode' => $periode,
        'jatuh_tempo' => substr($periode, 0, 8).'10',
        'nominal' => $nominal,
    ]);

    $pembayaran = Pembayaran::factory()->diterima()->create([
        'tagihan_id' => $tagihan->id,
        'tanggal_bayar' => $tanggalBayar,
        'nominal_bayar' => $nominal,
        'diunggah_pada' => Carbon\Carbon::parse($tanggalBayar.' 08:00', 'Asia/Jakarta'),
        'diverifikasi_oleh' => ($oleh ?? User::factory()->pengelola()->create())->id,
        'diverifikasi_pada' => Carbon\Carbon::parse($diterima, 'Asia/Jakarta'),
    ]);

    if ($sinkron !== null) {
        $factory = SinkronisasiErp::factory()->untukPembayaran($pembayaran);
        $factory = match ($sinkron) {
            'terkirim' => $factory->terkirim(),
            'gagal' => $factory->gagal(),
            default => $factory,
        };
        $factory->create();
    }

    return $pembayaran;
}

/**
 * ->assertRingkasan('jumlah', '5'): nilai sel x-ringkasan-angka berkunci "jumlah" sama dengan '5'.
 */
Testable::macro('assertRingkasan', function (string $kunci, string $nilai) {
    preg_match('#data-test="ringkasan-'.preg_quote($kunci, '#').'"[^>]*>(.*?)</div>#s', $this->html(), $cocok);

    expect(trim($cocok[1] ?? ''))->toBe($nilai);

    return $this;
});
