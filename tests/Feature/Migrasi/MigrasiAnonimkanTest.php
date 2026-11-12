<?php

use App\Support\Migrasi\PembacaCsv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/**
 * @return array<string, list<array{baris: int, data: array<string, string>}>>
 */
function bacaFolderMigrasi(string $folder): array
{
    $hasil = [];
    foreach (PembacaCsv::KOLOM as $nama => $kolom) {
        $hasil[$nama] = PembacaCsv::baca($folder.'/'.$nama.'.csv', $kolom);
    }

    return $hasil;
}

it('F-09 butir 2: nama, nomor identitas, dan telepon diganti dengan nilai fiktif', function () {
    $tujuan = anonimkanFixtureMigrasi();

    $sumber = bacaFolderMigrasi(jalurFixtureMigrasi('mentah'))['penghuni'];
    $hasil = bacaFolderMigrasi($tujuan)['penghuni'];

    expect($hasil)->toHaveCount(7);

    foreach ($sumber as $i => $baris) {
        $anonim = $hasil[$i]['data'];

        expect($anonim['nama'])->not->toBe($baris['data']['nama'])->not->toBe('')
            ->and($anonim['no_identitas'])->not->toBe($baris['data']['no_identitas'])->toMatch('/^00\d{14}$/')
            ->and($anonim['no_telepon'])->not->toBe($baris['data']['no_telepon'])->toMatch('/^08000000\d{4}$/');
    }

    expect(array_unique(array_column(array_column($hasil, 'data'), 'nama')))->toHaveCount(7);
});

it('F-09 butir 2: kode, nomor kamar, tarif, tanggal, periode, nominal, dan status sama persis dengan sumber', function () {
    $tujuan = anonimkanFixtureMigrasi();

    $sumber = bacaFolderMigrasi(jalurFixtureMigrasi('mentah'));
    $hasil = bacaFolderMigrasi($tujuan);

    expect($hasil['kamar'])->toBe($sumber['kamar'])
        ->and($hasil['kontrak'])->toBe($sumber['kontrak'])
        ->and($hasil['pembayaran'])->toBe($sumber['pembayaran']);

    foreach ($sumber['penghuni'] as $i => $baris) {
        expect($hasil['penghuni'][$i]['baris'])->toBe($baris['baris'])
            ->and($hasil['penghuni'][$i]['data']['kode_penghuni'])->toBe($baris['data']['kode_penghuni'])
            ->and($hasil['penghuni'][$i]['data']['status'])->toBe($baris['data']['status']);
    }
});

it('seed yang sama menghasilkan berkas yang sama', function () {
    $a = anonimkanFixtureMigrasi('migrasi/a', 7);
    $b = anonimkanFixtureMigrasi('migrasi/b', 7);
    $c = anonimkanFixtureMigrasi('migrasi/c', 8);

    expect(file_get_contents($a.'/penghuni.csv'))->toBe(file_get_contents($b.'/penghuni.csv'))
        ->and(file_get_contents($a.'/penghuni.csv'))->not->toBe(file_get_contents($c.'/penghuni.csv'));
});

it('membuat penanda _anonim.json dan tidak menyimpan tabel padanan', function () {
    $tujuan = anonimkanFixtureMigrasi();

    $penanda = json_decode(file_get_contents($tujuan.'/_anonim.json'), true);

    expect($penanda['jumlah_baris'])->toBe(['kamar' => 8, 'penghuni' => 7, 'kontrak' => 7, 'pembayaran' => 38])
        ->and(Carbon\Carbon::parse($penanda['dibuat_pada']))->not->toBeNull();

    $isi = array_map('basename', glob($tujuan.'/*'));
    sort($isi);

    expect($isi)->toBe(['_anonim.json', 'kamar.csv', 'kontrak.csv', 'pembayaran.csv', 'penghuni.csv']);
});

it('menolak berjalan di production', function () {
    app()->detectEnvironment(fn () => 'production');

    $tujuan = Storage::disk('local')->path('migrasi/anonim');

    $this->artisan('migrasi:anonimkan', ['sumber' => jalurFixtureMigrasi('mentah'), 'tujuan' => $tujuan])
        ->expectsOutputToContain('Anonimisasi hanya dijalankan di laptop pengembang.')
        ->assertExitCode(1);

    expect(is_dir($tujuan))->toBeFalse();
});

it('keluaran konsol hanya jumlah baris dan pengingat, tanpa nama atau nomor identitas sumber', function () {
    Artisan::call('migrasi:anonimkan', [
        'sumber' => jalurFixtureMigrasi('mentah'),
        'tujuan' => Storage::disk('local')->path('migrasi/anonim'),
        '--seed' => 7,
    ]);

    $keluaran = Artisan::output();

    expect($keluaran)->toContain('penghuni.csv: 7 baris')
        ->toContain('Hapus folder sumber setelah impor selesai.');

    foreach (bacaFolderMigrasi(jalurFixtureMigrasi('mentah'))['penghuni'] as $baris) {
        expect($keluaran)->not->toContain($baris['data']['nama'])
            ->not->toContain($baris['data']['no_identitas'])
            ->not->toContain($baris['data']['no_telepon']);
    }
});

it('berhenti bila folder tujuan sudah berisi CSV, kecuali diberi --timpa', function () {
    $tujuan = anonimkanFixtureMigrasi();

    $this->artisan('migrasi:anonimkan', ['sumber' => jalurFixtureMigrasi('mentah'), 'tujuan' => $tujuan])
        ->expectsOutputToContain('--timpa')
        ->assertExitCode(1);

    $this->artisan('migrasi:anonimkan', ['sumber' => jalurFixtureMigrasi('mentah'), 'tujuan' => $tujuan, '--timpa' => true])
        ->assertSuccessful();
});

it('melaporkan berkas sumber yang hilang atau kehilangan kolom tanpa menulis apa pun', function () {
    $sumber = Storage::disk('local')->path('migrasi/sumber-rusak');
    mkdir($sumber, 0775, true);
    copy(jalurFixtureMigrasi('mentah').'/kamar.csv', $sumber.'/kamar.csv');

    $tujuan = Storage::disk('local')->path('migrasi/anonim');

    $this->artisan('migrasi:anonimkan', ['sumber' => $sumber, 'tujuan' => $tujuan])
        ->expectsOutputToContain('penghuni.csv tidak ditemukan')
        ->assertExitCode(1);

    expect(is_dir($tujuan))->toBeFalse();
});
