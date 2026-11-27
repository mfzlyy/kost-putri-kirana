<?php

use App\Models\Fasilitas;
use App\Models\Kamar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->kunci = konfigurasiOdooUji();
});

function fasilitasKamar(string $nama, string $nomor, ?int $tertaut = null): Fasilitas
{
    $kamar = Kamar::query()->firstOrCreate(['nomor_kamar' => $nomor], ['tarif_bulanan' => 1400000]);

    return Fasilitas::factory()->create([
        'nama_fasilitas' => $nama,
        'kamar_id' => $kamar->id,
        'odoo_equipment_id' => $tertaut,
    ]);
}

/**
 * @param  array<int, string>  $peralatan  ID => nama peralatan di Odoo
 */
function fakePeralatanOdoo(array $peralatan): void
{
    fakeOdoo(['maintenance.equipment/search_read' => Http::response(
        collect($peralatan)->map(fn (string $nama, int $id) => ['id' => $id, 'name' => $nama])->values()->all(),
    )]);
}

/**
 * Satu fasilitas untuk setiap hasil: cocok, ganda, tidak ditemukan, sudah tertaut, dan berbeda.
 *
 * @return array<string, Fasilitas>
 */
function fasilitasSemuaHasil(): array
{
    $daftar = [
        'cocok' => fasilitasKamar('AC', '01'),
        'ganda' => fasilitasKamar('Kasur', '01'),
        'tidak' => fasilitasKamar('Lemari', '01'),
        'tertaut' => fasilitasKamar('AC', '02', 20),
        'berbeda' => fasilitasKamar('AC', '03', 99),
    ];
    fakePeralatanOdoo([
        10 => 'AC Kamar 01',
        11 => 'Kasur Kamar 01',
        12 => 'kasur kamar 01',
        20 => 'AC Kamar 02',
        30 => 'AC Kamar 03',
    ]);

    return $daftar;
}

/**
 * @return array<int, int|null>
 */
function tautanPeralatan(): array
{
    return DB::table('fasilitas')->orderBy('id')->pluck('odoo_equipment_id', 'id')->all();
}

it('F-28: nama peralatan memakai lokasi fasilitas tanpa menggandakannya', function (string $nama, ?string $nomor, string $hasil) {
    $fasilitas = $nomor === null
        ? Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => $nama])
        : fasilitasKamar($nama, $nomor);

    expect($fasilitas->namaPeralatanErp())->toBe($hasil);
})->with([
    'fasilitas kamar' => ['AC', '03', 'AC Kamar 03'],
    'spasi dirapikan' => ['  Meja   belajar ', '03', 'Meja belajar Kamar 03'],
    'lokasi sudah tertulis' => ['AC kamar  03', '03', 'AC kamar 03'],
    'nomor kamar lain yang berawalan sama' => ['Cermin Kamar 10', '1', 'Cermin Kamar 10 Kamar 1'],
    'kata kamar tanpa nomor' => ['Lampu kamar mandi', '03', 'Lampu kamar mandi Kamar 03'],
    'area bersama' => ['Pompa air', null, 'Pompa air Area Bersama'],
    'bersama bukan lokasi' => ['Dapur bersama', null, 'Dapur bersama Area Bersama'],
    'area bersama sudah tertulis' => ['Wifi area bersama', null, 'Wifi area bersama'],
]);

it('pratinjau menampilkan hasil per fasilitas tanpa mengubah basis data', function () {
    $f = fasilitasSemuaHasil();
    $sebelum = tautanPeralatan();

    $this->artisan('erp:cocokkan-peralatan')
        ->expectsTable(['fasilitas id', 'lokasi', 'nama yang dicari di Odoo', 'hasil'], [
            [$f['cocok']->id, 'Kamar 01', 'AC Kamar 01', 'cocok #10'],
            [$f['ganda']->id, 'Kamar 01', 'Kasur Kamar 01', 'ganda: #11, #12'],
            [$f['tidak']->id, 'Kamar 01', 'Lemari Kamar 01', 'tidak ditemukan'],
            [$f['tertaut']->id, 'Kamar 02', 'AC Kamar 02', 'sudah tertaut #20'],
            [$f['berbeda']->id, 'Kamar 03', 'AC Kamar 03', 'berbeda: web #99, Odoo #30'],
        ])
        ->expectsOutputToContain('Ringkasan: cocok 1, sudah tertaut 1, berbeda 1, ganda 1, tidak ditemukan 1.')
        ->expectsOutputToContain('Pratinjau: tidak ada data yang diubah.')
        ->expectsOutputToContain("Buat peralatan dengan nama persis seperti kolom 'nama yang dicari' di Odoo")
        ->expectsOutputToContain('Fasilitas yang ditautkan setelah tiketnya selesai tidak membuat baris pemeliharaan baru.')
        ->assertExitCode(0);

    expect(tautanPeralatan())->toBe($sebelum);
});

it('hanya membaca peralatan Odoo, tanpa membuat atau mengubah', function () {
    fasilitasSemuaHasil();

    $this->artisan('erp:cocokkan-peralatan --simpan --timpa')->assertExitCode(0);

    expect(panggilanOdoo())->toBe(['maintenance.equipment/search_read'])
        ->and(bodyOdoo('maintenance.equipment/search_read'))->toBe([
            'domain' => [], 'fields' => ['id', 'name'], 'limit' => 1000,
        ]);
});

it('--simpan hanya mengisi yang cocok; ganda, tidak ditemukan, dan berbeda dibiarkan', function () {
    $f = fasilitasSemuaHasil();

    $this->artisan('erp:cocokkan-peralatan --simpan')
        ->expectsOutputToContain('Disimpan: 1 fasilitas.')
        ->expectsOutputToContain('Tautan yang berbeda hanya diubah dengan --simpan --timpa.')
        ->assertExitCode(0);

    expect(tautanPeralatan())->toBe([
        $f['cocok']->id => 10,
        $f['ganda']->id => null,
        $f['tidak']->id => null,
        $f['tertaut']->id => 20,
        $f['berbeda']->id => 99,
    ]);
});

it('tautan ke ID lain diganti hanya dengan --simpan --timpa', function () {
    $f = fasilitasSemuaHasil();

    $this->artisan('erp:cocokkan-peralatan --timpa')
        ->expectsOutputToContain('--timpa hanya berlaku bersama --simpan.')
        ->assertExitCode(0);
    expect($f['berbeda']->fresh()->odoo_equipment_id)->toBe(99);

    $this->artisan('erp:cocokkan-peralatan --simpan --timpa')
        ->expectsOutputToContain('Disimpan: 2 fasilitas.')
        ->assertExitCode(0);

    expect($f['berbeda']->fresh()->odoo_equipment_id)->toBe(30)
        ->and($f['ganda']->fresh()->odoo_equipment_id)->toBeNull()
        ->and($f['tidak']->fresh()->odoo_equipment_id)->toBeNull();
});

it('nama dicocokkan tanpa peka spasi dan huruf besar-kecil', function () {
    $ac = fasilitasKamar('AC', '01');
    $pompa = Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Pompa   air']);
    fakePeralatanOdoo([4 => '  ac   KAMAR 01 ', 5 => 'POMPA AIR area bersama']);

    $this->artisan('erp:cocokkan-peralatan --simpan')->assertExitCode(0);

    expect($ac->fresh()->odoo_equipment_id)->toBe(4)
        ->and($pompa->fresh()->odoo_equipment_id)->toBe(5);
});

it('tautan tanpa nama yang cocok dibedakan antara masih ada dan sudah hilang di Odoo', function () {
    $ada = fasilitasKamar('AC', '01', 4);
    $hilang = fasilitasKamar('AC', '02', 8);
    fakePeralatanOdoo([4 => 'Pendingin ruangan']);

    $this->artisan('erp:cocokkan-peralatan --simpan --timpa')
        ->expectsTable(['fasilitas id', 'lokasi', 'nama yang dicari di Odoo', 'hasil'], [
            [$ada->id, 'Kamar 01', 'AC Kamar 01', 'sudah tertaut #4 (nama berbeda)'],
            [$hilang->id, 'Kamar 02', 'AC Kamar 02', 'tidak ditemukan (web #8 tidak ada di Odoo)'],
        ])
        ->assertExitCode(0);

    expect($ada->fresh()->odoo_equipment_id)->toBe(4)
        ->and($hilang->fresh()->odoo_equipment_id)->toBe(8);
});

it('Odoo mati: exit 1 tanpa perubahan dan tanpa API key di output', function () {
    Http::fake(['*' => fn () => throw new ConnectionException("cURL error 7: gagal {$this->kunci}")]);
    fasilitasKamar('AC', '01');

    $kode = Artisan::call('erp:cocokkan-peralatan', ['--simpan' => true]);

    expect($kode)->toBe(1)
        ->and(Artisan::output())->toContain('Odoo tidak dapat dihubungi')
        ->not->toContain($this->kunci)
        ->and(tautanPeralatan())->each->toBeNull();
});

it('tanpa fasilitas di web, perintah selesai dengan keterangan', function () {
    fakePeralatanOdoo([]);

    $this->artisan('erp:cocokkan-peralatan')
        ->expectsOutputToContain('Belum ada fasilitas di web.')
        ->assertExitCode(0);
});
