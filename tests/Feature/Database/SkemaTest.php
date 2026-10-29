<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('memiliki 12 tabel ERD di database', function () {
    $tabelERD = [
        'users', 'penghuni', 'vendor', 'pengaturan_operasional',
        'kamar', 'kontrak', 'tagihan', 'pembayaran',
        'fasilitas', 'tiket_keluhan', 'riwayat_status_tiket', 'sinkronisasi_erp',
    ];

    foreach ($tabelERD as $tabel) {
        expect(DB::getSchemaBuilder()->hasTable($tabel))->toBeTrue("Tabel {$tabel} harus ada");
    }
});

it('memiliki 10 tipe enum di database', function () {
    $enumTypes = DB::select("
        SELECT typname
        FROM pg_type
        WHERE typtype = 'e'
        ORDER BY typname
    ");

    $namaEnum = array_map(fn ($row) => $row->typname, $enumTypes);

    expect($namaEnum)->toHaveCount(10)
        ->and($namaEnum)->toContain(
            'peran_pengguna',
            'status_akun',
            'status_penghuni',
            'status_kontrak',
            'status_tagihan',
            'status_verifikasi',
            'lokasi_keluhan',
            'status_tiket',
            'jenis_transaksi_erp',
            'status_sinkronisasi'
        );
});

it('enum peran_pengguna memiliki 4 nilai berurutan', function () {
    $values = DB::select("
        SELECT enumlabel
        FROM pg_enum
        JOIN pg_type ON pg_enum.enumtypid = pg_type.oid
        WHERE pg_type.typname = 'peran_pengguna'
        ORDER BY enumsortorder
    ");

    $nilaiPeran = array_map(fn ($row) => $row->enumlabel, $values);

    expect($nilaiPeran)->toBe(['pemilik', 'pengelola', 'penghuni', 'vendor']);
});

it('ATB-01 menolak insert users dengan peran admin', function () {
    expect(fn () => DB::table('users')->insert([
        'nama' => 'Test Admin',
        'email' => 'admin@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'admin',
        'status_akun' => 'aktif',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('BR-06 menolak insert pengaturan_operasional dengan id = 2', function () {
    DB::table('users')->insert([
        'nama' => 'Pengelola',
        'email' => 'pengelola@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'pengelola',
        'status_akun' => 'aktif',
    ]);

    expect(fn () => DB::table('pengaturan_operasional')->insert([
        'id' => 2,
        'tanggal_jatuh_tempo' => 10,
        'batas_peringatan_kontrak_hari' => 7,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('BR-06 menolak tanggal_jatuh_tempo = 29', function () {
    expect(fn () => DB::table('pengaturan_operasional')->insert([
        'id' => 1,
        'tanggal_jatuh_tempo' => 29,
        'batas_peringatan_kontrak_hari' => 7,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('ATB-01 menolak dua kontrak aktif untuk kamar yang sama', function () {
    $userId1 = DB::table('users')->insertGetId([
        'nama' => 'User 1',
        'email' => 'user1@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $userId2 = DB::table('users')->insertGetId([
        'nama' => 'User 2',
        'email' => 'user2@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId1 = DB::table('penghuni')->insertGetId([
        'user_id' => $userId1,
        'no_identitas_enc' => 'encrypted_nik_1',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $penghuniId2 = DB::table('penghuni')->insertGetId([
        'user_id' => $userId2,
        'no_identitas_enc' => 'encrypted_nik_2',
        'no_telepon' => '081234567891',
        'status_penghuni' => 'aktif',
    ]);

    $kamarId = DB::table('kamar')->insertGetId([
        'nomor_kamar' => '101',
        'tarif_bulanan' => 1000000,
        'keterangan' => 'Kamar test',
    ]);

    DB::table('kontrak')->insert([
        'penghuni_id' => $penghuniId1,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-10-01',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'aktif',
    ]);

    expect(fn () => DB::table('kontrak')->insert([
        'penghuni_id' => $penghuniId2,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-15',
        'tanggal_berakhir' => '2027-10-15',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'aktif',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('ATB-01 menerima kontrak kedua berstatus berakhir untuk kamar yang sama', function () {
    $userId1 = DB::table('users')->insertGetId([
        'nama' => 'User 1',
        'email' => 'user1@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $userId2 = DB::table('users')->insertGetId([
        'nama' => 'User 2',
        'email' => 'user2@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId1 = DB::table('penghuni')->insertGetId([
        'user_id' => $userId1,
        'no_identitas_enc' => 'encrypted_nik_1',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $penghuniId2 = DB::table('penghuni')->insertGetId([
        'user_id' => $userId2,
        'no_identitas_enc' => 'encrypted_nik_2',
        'no_telepon' => '081234567891',
        'status_penghuni' => 'aktif',
    ]);

    $kamarId = DB::table('kamar')->insertGetId([
        'nomor_kamar' => '101',
        'tarif_bulanan' => 1000000,
        'keterangan' => 'Kamar test',
    ]);

    DB::table('kontrak')->insert([
        'penghuni_id' => $penghuniId1,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_berakhir' => '2026-09-30',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'berakhir',
    ]);

    DB::table('kontrak')->insert([
        'penghuni_id' => $penghuniId2,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-10-01',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'berakhir',
    ]);

    expect(DB::table('kontrak')->where('kamar_id', $kamarId)->count())->toBe(2);
});

it('BR-06 menolak tagihan kedua untuk kontrak dan periode yang sama', function () {
    $userId = DB::table('users')->insertGetId([
        'nama' => 'User',
        'email' => 'user@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId = DB::table('penghuni')->insertGetId([
        'user_id' => $userId,
        'no_identitas_enc' => 'encrypted_nik',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $kamarId = DB::table('kamar')->insertGetId([
        'nomor_kamar' => '101',
        'tarif_bulanan' => 1000000,
    ]);

    $kontrakId = DB::table('kontrak')->insertGetId([
        'penghuni_id' => $penghuniId,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-10-01',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'aktif',
    ]);

    DB::table('tagihan')->insert([
        'kontrak_id' => $kontrakId,
        'periode' => '2026-11-01',
        'nominal' => 1000000,
        'jatuh_tempo' => '2026-11-10',
        'status_tagihan' => 'belum_dibayar',
    ]);

    expect(fn () => DB::table('tagihan')->insert([
        'kontrak_id' => $kontrakId,
        'periode' => '2026-11-01',
        'nominal' => 1000000,
        'jatuh_tempo' => '2026-11-10',
        'status_tagihan' => 'belum_dibayar',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('ATB-03 menolak pembayaran ditolak tanpa alasan_penolakan', function () {
    $userId = DB::table('users')->insertGetId([
        'nama' => 'User',
        'email' => 'user@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId = DB::table('penghuni')->insertGetId([
        'user_id' => $userId,
        'no_identitas_enc' => 'encrypted_nik',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $kamarId = DB::table('kamar')->insertGetId([
        'nomor_kamar' => '101',
        'tarif_bulanan' => 1000000,
    ]);

    $kontrakId = DB::table('kontrak')->insertGetId([
        'penghuni_id' => $penghuniId,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-10-01',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'aktif',
    ]);

    $tagihanId = DB::table('tagihan')->insertGetId([
        'kontrak_id' => $kontrakId,
        'periode' => '2026-11-01',
        'nominal' => 1000000,
        'jatuh_tempo' => '2026-11-10',
        'status_tagihan' => 'belum_dibayar',
    ]);

    expect(fn () => DB::table('pembayaran')->insert([
        'tagihan_id' => $tagihanId,
        'tanggal_bayar' => '2026-11-05',
        'nominal_bayar' => 1000000,
        'bukti_bayar_path' => 'bukti.jpg',
        'status_verifikasi' => 'ditolak',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('ATB-03 menolak dua pembayaran menunggu untuk tagihan yang sama', function () {
    $userId = DB::table('users')->insertGetId([
        'nama' => 'User',
        'email' => 'user@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId = DB::table('penghuni')->insertGetId([
        'user_id' => $userId,
        'no_identitas_enc' => 'encrypted_nik',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $kamarId = DB::table('kamar')->insertGetId([
        'nomor_kamar' => '101',
        'tarif_bulanan' => 1000000,
    ]);

    $kontrakId = DB::table('kontrak')->insertGetId([
        'penghuni_id' => $penghuniId,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-10-01',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'aktif',
    ]);

    $tagihanId = DB::table('tagihan')->insertGetId([
        'kontrak_id' => $kontrakId,
        'periode' => '2026-11-01',
        'nominal' => 1000000,
        'jatuh_tempo' => '2026-11-10',
        'status_tagihan' => 'menunggu_verifikasi',
    ]);

    DB::table('pembayaran')->insert([
        'tagihan_id' => $tagihanId,
        'tanggal_bayar' => '2026-11-05',
        'nominal_bayar' => 1000000,
        'bukti_bayar_path' => 'bukti1.jpg',
        'status_verifikasi' => 'menunggu',
    ]);

    expect(fn () => DB::table('pembayaran')->insert([
        'tagihan_id' => $tagihanId,
        'tanggal_bayar' => '2026-11-06',
        'nominal_bayar' => 1000000,
        'bukti_bayar_path' => 'bukti2.jpg',
        'status_verifikasi' => 'menunggu',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('ATB-07 menolak tiket selesai tanpa biaya_perbaikan', function () {
    $userId = DB::table('users')->insertGetId([
        'nama' => 'User',
        'email' => 'user@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId = DB::table('penghuni')->insertGetId([
        'user_id' => $userId,
        'no_identitas_enc' => 'encrypted_nik',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $fasilitasId = DB::table('fasilitas')->insertGetId([
        'nama_fasilitas' => 'AC',
        'lokasi' => 'Kamar 101',
    ]);

    expect(fn () => DB::table('tiket_keluhan')->insert([
        'nomor_tiket' => 'KLH-0001',
        'penghuni_id' => $penghuniId,
        'fasilitas_id' => $fasilitasId,
        'lokasi' => 'kamar',
        'deskripsi' => 'AC rusak',
        'status_tiket' => 'selesai',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('sinkronisasi_erp menolak jenis pembayaran yang mengisi tiket_id', function () {
    expect(fn () => DB::table('sinkronisasi_erp')->insert([
        'jenis_transaksi' => 'pembayaran',
        'referensi_unik' => 'KPK-TEST-001',
        'tiket_id' => 1,
        'status_sinkronisasi' => 'menunggu',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('sinkronisasi_erp menolak referensi_unik ganda', function () {
    $userId = DB::table('users')->insertGetId([
        'nama' => 'User',
        'email' => 'user@test.com',
        'password_hash' => bcrypt('password'),
        'peran' => 'penghuni',
        'status_akun' => 'aktif',
    ]);

    $penghuniId = DB::table('penghuni')->insertGetId([
        'user_id' => $userId,
        'no_identitas_enc' => 'encrypted_nik',
        'no_telepon' => '081234567890',
        'status_penghuni' => 'aktif',
    ]);

    $kamarId = DB::table('kamar')->insertGetId([
        'nomor_kamar' => '101',
        'tarif_bulanan' => 1000000,
    ]);

    $kontrakId = DB::table('kontrak')->insertGetId([
        'penghuni_id' => $penghuniId,
        'kamar_id' => $kamarId,
        'tanggal_mulai' => '2026-10-01',
        'tanggal_berakhir' => '2027-10-01',
        'tarif_bulanan' => 1000000,
        'status_kontrak' => 'aktif',
    ]);

    $tagihanId = DB::table('tagihan')->insertGetId([
        'kontrak_id' => $kontrakId,
        'periode' => '2026-11-01',
        'nominal' => 1000000,
        'jatuh_tempo' => '2026-11-10',
        'status_tagihan' => 'lunas',
    ]);

    $pembayaranId = DB::table('pembayaran')->insertGetId([
        'tagihan_id' => $tagihanId,
        'tanggal_bayar' => '2026-11-05',
        'nominal_bayar' => 1000000,
        'bukti_bayar_path' => 'bukti.jpg',
        'status_verifikasi' => 'diterima',
    ]);

    DB::table('sinkronisasi_erp')->insert([
        'jenis_transaksi' => 'pembayaran',
        'referensi_unik' => 'KPK-PAY-001',
        'pembayaran_id' => $pembayaranId,
        'status_sinkronisasi' => 'terkirim',
    ]);

    expect(fn () => DB::table('sinkronisasi_erp')->insert([
        'jenis_transaksi' => 'pembayaran',
        'referensi_unik' => 'KPK-PAY-001',
        'pembayaran_id' => $pembayaranId,
        'status_sinkronisasi' => 'menunggu',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
