<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\Format;
use App\Support\Migrasi\PembacaCsv;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-11-12 12:00:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Isi laporan kesalahan terbaru sebagai daftar [berkas, baris, kode, kolom, pesan, jenis].
 *
 * @return list<array{berkas: string, baris: int, kode: string, kolom: string, pesan: string, jenis: string}>
 */
function laporanKesalahanMigrasi(): array
{
    $berkas = collect(Storage::disk('local')->files('migrasi/laporan'))->first(fn ($f) => str_contains($f, 'kesalahan-'));

    return array_map(fn (array $b) => [
        'berkas' => $b['data']['berkas'],
        'baris' => (int) $b['data']['baris'],
        'kode' => $b['data']['kode'],
        'kolom' => $b['data']['kolom'],
        'pesan' => $b['data']['pesan'],
        'jenis' => $b['data']['jenis'],
    ], PembacaCsv::baca(Storage::disk('local')->path($berkas), ['berkas', 'baris', 'kode', 'kolom', 'pesan', 'jenis']));
}

it('menolak folder tanpa _anonim.json', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => jalurFixtureMigrasi('mentah')])
        ->expectsOutputToContain('Data belum dianonimkan. Jalankan php artisan migrasi:anonimkan dulu.')
        ->assertExitCode(1);

    expect(Kamar::query()->count())->toBe(0);
});

it('menolak folder yang nomor identitasnya tidak berawalan 00 walau _anonim.json ada', function () {
    siapkanDasarMigrasi();

    $folder = Storage::disk('local')->path('migrasi/palsu');
    mkdir($folder, 0775, true);
    foreach (['kamar', 'penghuni', 'kontrak', 'pembayaran'] as $nama) {
        copy(jalurFixtureMigrasi('mentah')."/{$nama}.csv", "{$folder}/{$nama}.csv");
    }
    file_put_contents($folder.'/_anonim.json', '{}');

    $this->artisan('migrasi:impor', ['folder' => $folder])
        ->expectsOutputToContain('Data belum dianonimkan.')
        ->assertExitCode(1);

    expect(Penghuni::query()->count())->toBe(0);
});

it('menolak basis data yang sudah berisi data hunian', function () {
    siapkanDasarMigrasi();
    $folder = anonimkanFixtureMigrasi();

    Kamar::factory()->create(['nomor_kamar' => '99']);

    $this->artisan('migrasi:impor', ['folder' => $folder])
        ->expectsOutputToContain('Basis data sudah berisi data hunian. Migrasi hanya untuk basis data kosong. Lihat docs/migrasi/README.md.')
        ->assertExitCode(1);

    expect(Kamar::query()->count())->toBe(1);
});

it('menolak bila sudah ada akun penghuni walau tabel hunian kosong', function () {
    siapkanDasarMigrasi();
    User::factory()->penghuni()->create();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])
        ->expectsOutputToContain('Basis data sudah berisi data hunian.')
        ->assertExitCode(1);
});

it('menolak bila pengaturan operasional atau akun pengelola aktif belum ada', function () {
    $folder = anonimkanFixtureMigrasi();

    $this->artisan('migrasi:impor', ['folder' => $folder])
        ->expectsOutputToContain('pengaturan_operasional belum ada')
        ->assertExitCode(1);

    DB::table('pengaturan_operasional')->updateOrInsert(['id' => 1], []);
    User::factory()->pengelola()->nonaktif()->create();

    $this->artisan('migrasi:impor', ['folder' => $folder])
        ->expectsOutputToContain('Belum ada akun pengelola aktif')
        ->assertExitCode(1);
});

it('--uji menjalankan semuanya lalu membatalkan, tidak ada yang tersimpan', function () {
    siapkanDasarMigrasi();
    $folder = anonimkanFixtureMigrasi();

    $this->artisan('migrasi:impor', ['folder' => $folder, '--uji' => true])
        ->expectsOutputToContain('MODE UJI — tidak ada data yang disimpan.')
        ->expectsOutputToContain('Rekonsiliasi cocok: selisih 0.')
        ->assertSuccessful();

    expect(Kamar::query()->count())->toBe(0)
        ->and(Penghuni::query()->count())->toBe(0)
        ->and(Kontrak::query()->count())->toBe(0)
        ->and(Tagihan::query()->count())->toBe(0)
        ->and(Pembayaran::query()->count())->toBe(0)
        ->and(User::query()->where('peran', PeranPengguna::Penghuni->value)->count())->toBe(0);
});

it('F-09 butir 1: fixture dimuat lengkap (8 kamar, 7 penghuni, 6 kontrak aktif, 1 berakhir, tagihan dan pembayaran)', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])->assertSuccessful();

    expect(Kamar::query()->count())->toBe(8)
        ->and(Penghuni::query()->count())->toBe(7)
        ->and(Penghuni::query()->where('status_penghuni', StatusPenghuni::Aktif->value)->count())->toBe(6)
        ->and(Kontrak::query()->aktif()->count())->toBe(6)
        ->and(Kontrak::query()->where('status_kontrak', StatusKontrak::Berakhir->value)->count())->toBe(1)
        ->and(Tagihan::query()->count())->toBe(38)
        ->and(Tagihan::query()->where('status_tagihan', StatusTagihan::Lunas->value)->count())->toBe(37)
        ->and(Tagihan::query()->where('status_tagihan', StatusTagihan::BelumDibayar->value)->count())->toBe(1)
        ->and(Pembayaran::query()->count())->toBe(37);

    // Status hunian dihitung dari kontrak aktif (ATB-01): kamar 07 (kontrak berakhir) dan 08 kosong.
    expect(Kamar::query()->kosong()->orderBy('nomor_kamar')->pluck('nomor_kamar')->all())->toBe(['07', '08'])
        ->and(Kamar::query()->terisi()->count())->toBe(6);
});

it('membaca pemisah titik koma, BOM, tanggal DD/MM/YYYY, dan semua format rupiah dari fixture', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])->assertSuccessful();

    // penghuni.csv berpemisah titik koma dan berBOM; tidak ada penghuni yang hilang atau salah kolom.
    expect(Penghuni::query()->where('no_telepon', 'like', '08000000%')->count())->toBe(7);

    // Tarif kamar: "1.600.000,00" (05) dan "1400000.00" (07) dan "Rp 1.500.000" (03).
    expect((int) Kamar::query()->where('nomor_kamar', '05')->value('tarif_bulanan'))->toBe(1600000)
        ->and((int) Kamar::query()->where('nomor_kamar', '07')->value('tarif_bulanan'))->toBe(1400000)
        ->and((int) Kamar::query()->where('nomor_kamar', '03')->value('tarif_bulanan'))->toBe(1500000);

    // Kontrak K02: mulai "01/02/2026" dan berakhir "2027-01-31".
    $kontrak = Kontrak::query()->whereHas('kamar', fn ($q) => $q->where('nomor_kamar', '02'))->firstOrFail();
    expect($kontrak->tanggal_mulai->toDateString())->toBe('2026-02-01')
        ->and($kontrak->tanggal_berakhir->toDateString())->toBe('2027-01-31');

    // Semua nominal tagihan terbaca benar apa pun format rupiahnya.
    expect(Tagihan::query()->selectRaw('distinct nominal')->orderBy('nominal')->pluck('nominal')->map(fn ($n) => (int) $n)->all())
        ->toBe([1400000, 1500000, 1600000]);
});

it('menyimpan nomor identitas terenkripsi dan akun penghuni berpassword acak', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])->assertSuccessful();

    $penghuni = Penghuni::query()->with('user')->orderBy('id')->get();
    $mentah = DB::table('penghuni')->orderBy('id')->pluck('no_identitas_enc');

    foreach ($penghuni as $i => $p) {
        expect($p->no_identitas_enc)->toMatch('/^00\d{14}$/')
            ->and($mentah[$i])->not->toContain($p->no_identitas_enc)
            ->and($p->user->peran)->toBe(PeranPengguna::Penghuni)
            ->and($p->user->email)->toMatch('/^penghuni\.p0\d@migrasi\.kirana\.test$/')
            ->and($p->user->password_hash)->not->toBe('')->not->toBeNull();
    }

    // Akun penghuni nonaktif mengikuti status penghuni (P07).
    $nonaktif = User::query()->where('email', 'penghuni.p07@migrasi.kirana.test')->firstOrFail();
    expect($nonaktif->status_akun)->toBe(StatusAkun::Nonaktif)
        ->and($nonaktif->penghuni->status_penghuni)->toBe(StatusPenghuni::Nonaktif);
});

it('tagihan lunas punya tepat satu pembayaran diterima tanpa berkas bukti dan tanpa sinkronisasi ERP', function () {
    $pengelola = siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])->assertSuccessful();

    Tagihan::query()->where('status_tagihan', StatusTagihan::Lunas->value)->with('pembayaran')->get()->each(function (Tagihan $tagihan) use ($pengelola) {
        expect($tagihan->pembayaran)->toHaveCount(1);

        $pembayaran = $tagihan->pembayaran->first();

        expect($pembayaran->status_verifikasi)->toBe(StatusVerifikasi::Diterima)
            ->and((float) $pembayaran->nominal_bayar)->toBe((float) $tagihan->nominal)
            ->and($pembayaran->diverifikasi_oleh)->toBe($pengelola->id)
            ->and($pembayaran->bukti_bayar_path)->toBe('migrasi/tanpa-bukti')
            ->and($pembayaran->diunggah_pada->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe($pembayaran->tanggal_bayar->toDateString().' 12:00')
            ->and($pembayaran->diverifikasi_pada->equalTo($pembayaran->diunggah_pada))->toBeTrue();
    });

    expect(Tagihan::query()->where('status_tagihan', StatusTagihan::BelumDibayar->value)->first()->pembayaran)->toHaveCount(0)
        ->and(DB::table('sinkronisasi_erp')->count())->toBe(0);
});

it('pengelola yang diverifikasi adalah akun pengelola aktif dengan id terkecil', function () {
    User::factory()->pengelola()->nonaktif()->create();
    $pertama = siapkanDasarMigrasi();
    User::factory()->pengelola()->create();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])->assertSuccessful();

    expect(Pembayaran::query()->pluck('diverifikasi_oleh')->unique()->all())->toBe([$pertama->id]);
});

it('ATB-05: hanya tagihan belum dibayar yang lewat jatuh tempo dihitung tunggakan', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()])->assertSuccessful();

    $tunggakan = Tagihan::query()->tunggakan(now())->with('kontrak.kamar')->get();

    expect($tunggakan)->toHaveCount(1)
        ->and($tunggakan->first()->kontrak->kamar->nomor_kamar)->toBe('03')
        ->and($tunggakan->first()->periode->toDateString())->toBe('2026-10-01')
        ->and((int) $tunggakan->first()->nominal)->toBe(1500000)
        ->and($tunggakan->first()->hariTerlambatPada(now()))->toBe(33);

    // Sebelum jatuh tempo (10 Okt) belum ada tunggakan.
    expect(Tagihan::query()->tunggakan(Carbon::parse('2026-10-10', 'Asia/Jakarta'))->count())->toBe(0);
});

it('keluaran konsol dan laporan tidak memuat nama, nomor identitas, atau telepon', function () {
    siapkanDasarMigrasi();

    Artisan::call('migrasi:impor', ['folder' => anonimkanFixtureMigrasi()]);
    $keluaran = Artisan::output();

    $laporan = collect(Storage::disk('local')->files('migrasi/laporan'))
        ->map(fn ($f) => Storage::disk('local')->get($f))
        ->implode("\n");

    foreach (Penghuni::query()->with('user')->get() as $p) {
        foreach ([$p->user->nama, $p->no_identitas_enc, $p->no_telepon] as $rahasia) {
            expect($keluaran)->not->toContain($rahasia)
                ->and($laporan)->not->toContain($rahasia);
        }
    }
});

it('F-09 butir 4: setiap galat fixture salah tercatat dengan berkas, baris, dan pesan yang benar', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => jalurFixtureMigrasi('salah')])->assertSuccessful();

    $galat = collect(laporanKesalahanMigrasi())->where('jenis', 'galat')
        ->map(fn ($t) => [$t['berkas'], $t['baris'], $t['kode'], $t['kolom'], $t['pesan']])
        ->all();

    expect($galat)->toEqualCanonicalizing([
        // tarif 0
        ['kamar.csv', 9, '08', 'tarif_bulanan', 'Tarif bulanan harus lebih dari 0.'],
        // NIK 15 angka, dan telepon salah
        ['penghuni.csv', 7, 'P06', 'no_identitas', 'Nomor identitas harus 16 angka.'],
        ['penghuni.csv', 9, 'P08', 'no_telepon', 'Nomor telepon harus diawali 0 dan terdiri dari 10 sampai 14 angka.'],
        // penolakan berantai: penghuni P06 tertolak, kontrak K06 ikut ditolak
        ['kontrak.csv', 7, 'K06', 'kode_penghuni', 'Merujuk penghuni.csv P06 yang tidak dimuat.'],
        // tanggal berakhir sebelum mulai
        ['kontrak.csv', 8, 'K07', 'tanggal_berakhir', 'Tanggal berakhir harus setelah tanggal mulai.'],
        // dua kontrak aktif di kamar yang sama
        ['kontrak.csv', 9, 'K08', 'nomor_kamar', 'Kamar sudah punya kontrak aktif lain (K01).'],
        // pembayaran: berantai dari K06, K07, K08; kode tidak dikenal; periode ganda
        ['pembayaran.csv', 32, 'K06', 'kode_kontrak', 'Merujuk kontrak.csv K06 yang tidak dimuat.'],
        ['pembayaran.csv', 33, 'K07', 'kode_kontrak', 'Merujuk kontrak.csv K07 yang tidak dimuat.'],
        ['pembayaran.csv', 34, 'K99', 'kode_kontrak', 'Kode K99 tidak dikenal di kontrak.csv.'],
        ['pembayaran.csv', 35, 'K01', 'periode', 'Periode ganda untuk kontrak yang sama.'],
        ['pembayaran.csv', 36, 'K08', 'kode_kontrak', 'Merujuk kontrak.csv K08 yang tidak dimuat.'],
    ]);
});

it('fixture salah: baris valid tetap dimuat dan laporan tidak memuat nomor identitas', function () {
    siapkanDasarMigrasi();

    $this->artisan('migrasi:impor', ['folder' => jalurFixtureMigrasi('salah')])->assertSuccessful();

    expect(Kamar::query()->count())->toBe(7)
        ->and(Penghuni::query()->count())->toBe(7)
        ->and(Kontrak::query()->aktif()->count())->toBe(5)
        ->and(Kontrak::query()->count())->toBe(5)
        ->and(Tagihan::query()->count())->toBe(30)
        ->and(Penghuni::query()->get()->pluck('no_identitas_enc')->all())->not->toContain('001234567890123');

    $laporan = collect(Storage::disk('local')->files('migrasi/laporan'))
        ->map(fn ($f) => Storage::disk('local')->get($f))
        ->implode("\n");

    expect($laporan)->not->toContain('00123456789012');
});

it('peringatan: kontrak aktif kedaluwarsa dan periode lebih dari 6 bulan tetap dimuat', function () {
    siapkanDasarMigrasi();
    Carbon::setTestNow(Carbon::parse('2027-02-12 12:00:00', 'Asia/Jakarta'));

    $this->artisan('migrasi:impor', ['folder' => jalurFixtureMigrasi('salah')])->assertSuccessful();

    $peringatan = collect(laporanKesalahanMigrasi())->where('jenis', 'peringatan');

    // K01 berakhir 2026-12-31 (sudah lewat per 2027-02-12); periode sebelum 2026-08-01 di luar 6 bulan (Juni, Juli).
    expect($peringatan->where('berkas', 'kontrak.csv')->pluck('kode')->unique()->values()->all())->toBe(['K01', 'K02'])
        ->and($peringatan->where('berkas', 'kontrak.csv')->first()['pesan'])->toBe('Kontrak aktif sudah lewat tanggal berakhir; akan diakhiri oleh kontrak:akhiri-kedaluwarsa.')
        ->and($peringatan->where('berkas', 'pembayaran.csv')->first()['pesan'])->toBe('Periode di luar 6 bulan terakhir; pastikan diizinkan pemilik (DEP-02).')
        ->and($peringatan->where('berkas', 'pembayaran.csv'))->toHaveCount(10)
        ->and(Tagihan::query()->count())->toBe(30);
});

it('Format::parseRupiah dan parseTanggal menerima semua format templat', function () {
    foreach (['1250000', '1.250.000', 'Rp1.250.000', 'Rp 1.250.000', '1.250.000,00', '1250000.00'] as $teks) {
        expect(Format::parseRupiah($teks))->toBe(1250000);
    }

    expect(Format::parseRupiah('1.250.000,50'))->toBeNull()
        ->and(Format::parseRupiah('1250000.5'))->toBeNull()
        ->and(Format::parseTanggal('2026-03-05')->toDateString())->toBe('2026-03-05')
        ->and(Format::parseTanggal('05/03/2026')->toDateString())->toBe('2026-03-05')
        ->and(Format::parseTanggal('31/02/2026'))->toBeNull()
        ->and(Format::parseTanggal('03-05-2026'))->toBeNull();
});

it('PembacaCsv mendeteksi pemisah, membuang BOM, dan melewati baris kosong', function () {
    $berkas = Storage::disk('local')->path('migrasi/uji.csv');
    mkdir(dirname($berkas), 0775, true);
    file_put_contents($berkas, "\xEF\xBB\xBFnomor_kamar;tarif_bulanan;keterangan\n01;\"1.400.000\";Satu; dua\n;;\n02;1500000;\n");

    $baris = PembacaCsv::baca($berkas, ['nomor_kamar', 'tarif_bulanan', 'keterangan']);

    expect($baris)->toHaveCount(2)
        ->and($baris[0])->toBe(['baris' => 2, 'data' => ['nomor_kamar' => '01', 'tarif_bulanan' => '1.400.000', 'keterangan' => 'Satu']])
        ->and($baris[1]['baris'])->toBe(4);
});
