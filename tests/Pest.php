<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusTagihan;
use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Odoo\IntegrasiErpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
    // Tidak ada tes yang boleh memanggil layanan luar (Odoo): permintaan HTTP yang tidak dipalsukan langsung gagal.
    ->beforeEach(fn () => Http::preventStrayRequests())
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
 * Konfigurasi Odoo fiktif, supaya tes tidak memakai nilai .env. Hasilnya API key uji.
 */
function konfigurasiOdooUji(): string
{
    $kunci = 'kunci-uji-bukan-rahasia-erp-0123456789';

    config(['services.odoo' => [
        'url' => 'http://odoo.test:8069',
        'db' => 'kpk_uji',
        'api_key' => $kunci,
        'api_key_expires_at' => now()->addDays(60)->toDateString(),
        'timeout' => 30,
        'user_agent' => 'KostPutriKirana/1.0',
    ]]);

    return $kunci;
}

/**
 * Balasan account.move/search_read atau read untuk faktur pada $state dan $paymentState: Rp1.250.000, periode
 * November 2026, partner id 7. $timpa mengganti field faktur.
 *
 * @param  array<string, mixed>  $timpa
 * @return list<array<string, mixed>>
 */
function fakturOdoo(string $paymentState, string $state = 'posted', int $id = 41, array $timpa = []): array
{
    return [array_merge([
        'id' => $id,
        'state' => $state,
        'payment_state' => $paymentState,
        'amount_total' => 1250000.0,
        'amount_residual' => $paymentState === 'paid' ? 0.0 : 1250000.0,
        'invoice_date' => '2026-11-01',
        'partner_id' => [7, 'Penghuni'],
    ], $timpa)];
}

/**
 * Memasang Odoo palsu: kunci "model/method", nilai balasannya.
 *
 * @param  array<string, mixed>  $balasan
 */
function fakeOdoo(array $balasan): void
{
    Http::fake(collect($balasan)->mapWithKeys(fn (mixed $isi, string $jalur) => ["*/json/2/{$jalur}" => $isi])->all());
}

/**
 * Odoo palsu untuk alur pembayaran baru: partner dan faktur belum ada, lalu faktur (id 41) lunas setelah dibayar.
 * res.partner/read menjawab partner milik penghuni pertama di basis data tes. $timpa mengganti balasan per
 * "model/method".
 *
 * @param  array<string, mixed>  $timpa
 */
function fakeOdooPembayaran(array $timpa = []): void
{
    fakeOdoo(array_merge([
        'res.partner/search_read' => Http::response([]),
        'res.partner/create' => Http::response([7]),
        'res.partner/read' => fn () => Http::response([['id' => 7, 'ref' => 'PNH-'.Penghuni::query()->orderBy('id')->value('id')]]),
        'account.move/search_read' => Http::response([]),
        'account.move/create' => Http::response([41]),
        'account.move/action_post' => Http::response('false'),
        'account.move/read' => Http::sequence()->push(fakturOdoo('not_paid'))->push(fakturOdoo('paid')),
        'account.journal/search_read' => Http::response([['id' => 6]]),
        'account.payment.register/create' => Http::response([3]),
        'account.payment.register/action_create_payments' => Http::response('false'),
    ], $timpa));
}

/**
 * Balasan account.move/search_read untuk tagihan vendor (id 61) yang cocok dengan tiketErpUji(): Rp150.000,
 * tanggal 5 November 2026, partner id 8. $timpa mengganti field tagihan.
 *
 * @param  array<string, mixed>  $timpa
 * @return list<array<string, mixed>>
 */
function tagihanVendorOdoo(string $state = 'posted', array $timpa = []): array
{
    return [array_merge([
        'id' => 61,
        'state' => $state,
        'amount_total' => 150000.0,
        'invoice_date' => '2026-11-05',
        'partner_id' => [8, 'Vendor'],
    ], $timpa)];
}

/**
 * Odoo palsu untuk alur biaya perbaikan baru: partner (id 8) dan tagihan vendor (id 61) belum ada, lalu tagihan
 * posted. res.partner/read menjawab partner milik vendor pertama di basis data tes.
 *
 * @param  array<string, mixed>  $timpa
 */
function fakeOdooBiaya(array $timpa = []): void
{
    fakeOdoo(array_merge([
        'res.partner/search_read' => Http::response([]),
        'res.partner/create' => Http::response([8]),
        'res.partner/read' => fn () => Http::response([['id' => 8, 'ref' => 'VDR-'.Vendor::query()->orderBy('id')->value('id')]]),
        'account.move/search_read' => Http::response([]),
        'account.move/create' => Http::response([61]),
        'account.move/action_post' => Http::response('false'),
        'account.move/read' => Http::response([['id' => 61, 'state' => 'posted']]),
    ], $timpa));
}

/**
 * Balasan maintenance.request/search_read untuk permintaan (id 5) yang cocok dengan tiketErpUji(): peralatan 42,
 * tanggal 5 November 2026, tahap $idTahap. $timpa mengganti field permintaan.
 *
 * @param  array<string, mixed>  $timpa
 * @return list<array<string, mixed>>
 */
function permintaanOdoo(int $idTahap = 3, array $timpa = []): array
{
    return [array_merge([
        'id' => 5,
        'name' => 'KPK-TKT-0001-M Tidak dingin',
        'equipment_id' => [42, 'AC Kamar ZX9'],
        'stage_id' => [$idTahap, 'Tahap'],
        'request_date' => '2026-11-05',
    ], $timpa)];
}

/**
 * Odoo palsu untuk alur pemeliharaan baru: permintaan (id 5) belum ada, tahap selesai ber-ID 3.
 *
 * @param  array<string, mixed>  $timpa
 */
function fakeOdooPemeliharaan(array $timpa = []): void
{
    fakeOdoo(array_merge([
        'maintenance.request/search_read' => Http::response([]),
        'maintenance.request/create' => Http::response([5]),
        'maintenance.stage/search_read' => Http::response([['id' => 3]]),
        'maintenance.request/write' => Http::response('true'),
        'maintenance.request/read' => Http::response([['id' => 5, 'stage_id' => [3, 'Repaired']]]),
    ], $timpa));
}

/**
 * Tiket $nomor yang diselesaikan vendor saat ini juga (lewat selesaikan()), milik penghuni "Rina Maharani" di kamar
 * ZX9, untuk fasilitas AC kamar itu. $idPeralatan null berarti fasilitas belum ditautkan (tanpa baris pemeliharaan).
 * Panggil Queue::fake() lebih dulu supaya barisnya tidak langsung dikirim.
 */
function tiketErpUji(int $biaya = 150000, ?int $idPeralatan = 42, string $nomor = 'KLH-0001', ?Vendor $vendor = null): TiketKeluhan
{
    $kontrak = Kontrak::query()->whereRelation('kamar', 'nomor_kamar', 'ZX9')->first()
        ?? buatKontrakUji('ZX9', 'Rina Maharani');
    $vendor ??= Vendor::factory()->create(['nama_vendor' => 'CV Teknik Uji']);
    $fasilitas = Fasilitas::factory()->create([
        'nama_fasilitas' => 'AC',
        'kamar_id' => $kontrak->kamar_id,
        'odoo_equipment_id' => $idPeralatan,
    ]);
    $tiket = TiketKeluhan::factory()->create([
        'nomor_tiket' => $nomor,
        'penghuni_id' => $kontrak->penghuni_id,
        'fasilitas_id' => $fasilitas->id,
        'vendor_id' => $vendor->id,
        'status_tiket' => StatusTiket::Dikerjakan,
        'deskripsi' => 'AC di kamar saya meneteskan air sejak semalam',
        'jenis_kerusakan' => 'Tidak dingin',
        'catatan_instruksi' => 'Hubungi penghuni sebelum datang',
    ]);

    $tiket->selesaikan($biaya, $vendor->user);

    return $tiket;
}

/**
 * Baris sinkronisasi tiket menurut jenisnya.
 */
function barisTiket(TiketKeluhan $tiket, JenisTransaksiErp $jenis): SinkronisasiErp
{
    return $tiket->sinkronisasi()->where('jenis_transaksi', $jenis->value)->sole();
}

function kirimKeOdoo(SinkronisasiErp $baris): SinkronisasiErp
{
    app(IntegrasiErpService::class)->kirim($baris);

    return $baris->fresh();
}

/**
 * Isi body satu panggilan "model/method" yang terkirim tepat sekali.
 *
 * @return array<string, mixed>
 */
function bodyOdoo(string $jalur): array
{
    $cocok = Http::recorded(fn (Request $request) => str_ends_with($request->url(), "/json/2/{$jalur}"));
    expect($cocok)->toHaveCount(1);

    return $cocok->first()[0]->data();
}

/**
 * Tidak satu pun permintaan ke Odoo memuat data penghuni tiket atau teks yang ditulis tentangnya (NFR-02).
 */
function pastikanTanpaDataPenghuni(TiketKeluhan $tiket): void
{
    $penghuni = $tiket->penghuni;
    $terlarang = [
        'Rina', 'Maharani', $penghuni->user->email, $penghuni->no_identitas_enc, $penghuni->no_telepon,
        'meneteskan air', 'Hubungi penghuni',
    ];

    expect(Http::recorded())->not->toBeEmpty();

    foreach (Http::recorded() as [$request]) {
        foreach ($terlarang as $isi) {
            expect($isi)->toBeString()->not->toBeEmpty()
                ->and($request->body())->not->toContain($isi);
        }
    }
}

/**
 * "model/method" setiap panggilan Odoo yang terkirim, berurutan.
 *
 * @return list<string>
 */
function panggilanOdoo(): array
{
    return Http::recorded()->map(fn (array $catatan) => Str::after($catatan[0]->url(), '/json/2/'))->values()->all();
}

/**
 * ->assertRingkasan('jumlah', '5'): nilai sel x-ringkasan-angka berkunci "jumlah" sama dengan '5'.
 */
Testable::macro('assertRingkasan', function (string $kunci, string $nilai) {
    preg_match('#data-test="ringkasan-'.preg_quote($kunci, '#').'"[^>]*>(.*?)</div>#s', $this->html(), $cocok);

    expect(trim($cocok[1] ?? ''))->toBe($nilai);

    return $this;
});
