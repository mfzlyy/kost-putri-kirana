<?php

use App\Support\Migrasi\PembacaCsv;
use App\Support\Migrasi\Rekonsiliasi;
use App\Support\Migrasi\ValidatorMigrasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-11-12 12:00:00', 'Asia/Jakarta'));
    siapkanDasarMigrasi();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Baris rekonsiliasi terbaru, kunci = uraian, nilai = [sumber, dimuat, ditolak, selisih] sebagai teks.
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
 */
function rekonsiliasiMigrasi(): array
{
    $berkas = collect(Storage::disk('local')->files('migrasi/laporan'))->first(fn ($f) => str_contains($f, 'rekonsiliasi-'));
    $hasil = [];

    foreach (PembacaCsv::baca(Storage::disk('local')->path($berkas), ['uraian', 'sumber', 'dimuat', 'ditolak', 'selisih']) as $b) {
        $hasil[$b['data']['uraian']] = [$b['data']['sumber'], $b['data']['dimuat'], $b['data']['ditolak'], $b['data']['selisih']];
    }

    return $hasil;
}

it('F-09 butir 3: fixture valid menghasilkan semua selisih 0 dan kesimpulan cocok', function () {
    Artisan::call('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()]);
    $keluaran = Artisan::output();

    expect($keluaran)->toContain('Rekonsiliasi cocok: selisih 0.')
        ->not->toContain('Ada selisih')
        ->not->toContain('baris ditolak dan tidak dimuat');

    $baris = rekonsiliasiMigrasi();

    expect($baris['Kamar'])->toBe(['8', '8', '0', '0'])
        ->and($baris['Penghuni'])->toBe(['7', '7', '0', '0'])
        ->and($baris['Kontrak aktif'])->toBe(['6', '6', '0', '0'])
        ->and($baris['Kontrak berakhir'])->toBe(['1', '1', '0', '0']);

    foreach ($baris as $uraian => [, , , $selisih]) {
        expect($selisih)->toBeIn(['0', 'Rp0'], $uraian);
    }

    // Tabel konsol memuat kolom dan baris yang sama.
    expect($keluaran)->toContain('Sumber')->toContain('Dimuat')->toContain('Ditolak')->toContain('Selisih')
        ->toContain('2026-10 · Total dibayar')->toContain('Rp7.500.000');
});

it('total nominal dan total dibayar per periode sama dengan penjumlahan manual fixture', function () {
    Artisan::call('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()]);

    $baris = rekonsiliasiMigrasi();

    // Enam kontrak aktif: 2 x 1.400.000 + 2 x 1.500.000 + 2 x 1.600.000 = 9.000.000 per periode.
    // Juni dan Juli ditambah kontrak berakhir 1.400.000 = 10.400.000. Oktober: tagihan kamar 03 (1.500.000) belum dibayar.
    $harapan = [
        '2026-06' => [7, 'Rp10.400.000', 7, 'Rp10.400.000'],
        '2026-07' => [7, 'Rp10.400.000', 7, 'Rp10.400.000'],
        '2026-08' => [6, 'Rp9.000.000', 6, 'Rp9.000.000'],
        '2026-09' => [6, 'Rp9.000.000', 6, 'Rp9.000.000'],
        '2026-10' => [6, 'Rp9.000.000', 5, 'Rp7.500.000'],
        '2026-11' => [6, 'Rp9.000.000', 6, 'Rp9.000.000'],
    ];

    foreach ($harapan as $periode => [$jumlah, $nominal, $lunas, $dibayar]) {
        expect($baris["{$periode} · Jumlah tagihan"])->toBe([(string) $jumlah, (string) $jumlah, '0', '0'])
            ->and($baris["{$periode} · Total nominal tagihan"])->toBe([$nominal, $nominal, 'Rp0', 'Rp0'])
            ->and($baris["{$periode} · Jumlah lunas"])->toBe([(string) $lunas, (string) $lunas, '0', '0'])
            ->and($baris["{$periode} · Total dibayar"])->toBe([$dibayar, $dibayar, 'Rp0', 'Rp0']);
    }

    // Tidak ada periode lain yang muncul.
    expect(array_filter(array_keys($baris), fn ($u) => str_contains($u, 'Jumlah tagihan')))->toHaveCount(6);
});

it('fixture salah: kolom Ditolak terisi dan Selisih tetap 0 (Sumber = Dimuat + Ditolak)', function () {
    Artisan::call('migrasi:impor', ['folder' => jalurFixtureMigrasi('salah')]);
    $keluaran = Artisan::output();

    $baris = rekonsiliasiMigrasi();

    expect($baris['Kamar'])->toBe(['8', '7', '1', '0'])
        ->and($baris['Penghuni'])->toBe(['9', '7', '2', '0'])
        ->and($baris['Kontrak aktif'])->toBe(['7', '5', '2', '0'])
        ->and($baris['Kontrak berakhir'])->toBe(['1', '0', '1', '0'])
        ->and($baris['2026-11 · Jumlah tagihan'])->toBe(['9', '5', '4', '0'])
        ->and($baris['2026-11 · Total nominal tagihan'])->toBe(['Rp13.200.000', 'Rp7.400.000', 'Rp5.800.000', 'Rp0'])
        ->and($baris['2026-06 · Jumlah tagihan'])->toBe(['6', '5', '1', '0']);

    foreach ($baris as $uraian => [, , , $selisih]) {
        expect($selisih)->toBeIn(['0', 'Rp0'], $uraian);
    }

    expect($keluaran)->toContain('Rekonsiliasi cocok: selisih 0.')
        ->toContain('11 baris ditolak dan tidak dimuat')
        ->toContain('kesalahan-');
});

it('selisih terdeteksi bila basis data tidak sama dengan sumber', function () {
    $folder = anonimkanFixtureMigrasi();

    $rekon = new Rekonsiliasi;
    $sumber = [];
    foreach (PembacaCsv::KOLOM as $nama => $kolom) {
        $sumber[$nama] = PembacaCsv::baca($folder.'/'.$nama.'.csv', $kolom);
    }
    $hasil = (new ValidatorMigrasi)->validasi($sumber);

    // Basis data masih kosong: semua baris sumber valid tetapi belum dimuat, jadi ada selisih.
    $baris = $rekon->hitung($hasil);

    expect($rekon->cocok($baris))->toBeFalse()
        ->and(collect($baris)->firstWhere('uraian', 'Kamar')['selisih'])->toBe(8);
});
