<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\LokasiKeluhan;
use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTagihan;
use App\Enums\StatusTiket;
use App\Enums\StatusVerifikasi;
use Illuminate\Support\Facades\DB;

dataset('enum_postgres', [
    'peran_pengguna' => ['peran_pengguna', PeranPengguna::class],
    'status_akun' => ['status_akun', StatusAkun::class],
    'status_penghuni' => ['status_penghuni', StatusPenghuni::class],
    'status_kontrak' => ['status_kontrak', StatusKontrak::class],
    'status_tagihan' => ['status_tagihan', StatusTagihan::class],
    'status_verifikasi' => ['status_verifikasi', StatusVerifikasi::class],
    'lokasi_keluhan' => ['lokasi_keluhan', LokasiKeluhan::class],
    'status_tiket' => ['status_tiket', StatusTiket::class],
    'jenis_transaksi_erp' => ['jenis_transaksi_erp', JenisTransaksiErp::class],
    'status_sinkronisasi' => ['status_sinkronisasi', StatusSinkronisasi::class],
]);

it('memiliki nilai enum PHP yang sama persis dengan tipe enum PostgreSQL', function (string $tipe, string $kelas) {
    $nilaiDatabase = DB::table('pg_enum')
        ->join('pg_type', 'pg_type.oid', '=', 'pg_enum.enumtypid')
        ->where('pg_type.typname', $tipe)
        ->orderBy('pg_enum.enumsortorder')
        ->pluck('pg_enum.enumlabel')
        ->all();

    expect(array_map(fn ($case) => $case->value, $kelas::cases()))->toBe($nilaiDatabase);
})->with('enum_postgres');

it('memberi label Bahasa Indonesia untuk setiap case enum', function (string $tipe, string $kelas) {
    foreach ($kelas::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
})->with('enum_postgres');

it('memberi label yang terbaca untuk status dengan garis bawah', function () {
    expect(StatusTagihan::BelumDibayar->label())->toBe('Belum dibayar')
        ->and(StatusTagihan::MenungguVerifikasi->label())->toBe('Menunggu verifikasi')
        ->and(LokasiKeluhan::AreaBersama->label())->toBe('Area bersama');
});
