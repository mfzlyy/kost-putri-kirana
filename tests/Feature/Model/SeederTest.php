<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusTiket;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\TiketKeluhan;
use App\Models\User;
use Database\Seeders\AkunAwalSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DataContohSeeder;

function isiKredensialSeeder(): void
{
    config([
        'seeder.pemilik' => [
            'nama' => 'Pemilik Uji',
            'email' => 'pemilik@contoh.test',
            'password' => 'sandi-uji-pemilik-123',
        ],
        'seeder.pengelola' => [
            'nama' => 'Pengelola Uji',
            'email' => 'pengelola@contoh.test',
            'password' => 'sandi-uji-pengelola-123',
        ],
    ]);
}

it('gagal dengan pesan jelas bila SEED_PEMILIK_EMAIL kosong', function () {
    isiKredensialSeeder();
    config(['seeder.pemilik.email' => '']);

    expect(fn () => $this->seed(AkunAwalSeeder::class))
        ->toThrow(RuntimeException::class, 'SEED_PEMILIK_EMAIL');

    expect(User::query()->count())->toBe(0);
});

it('gagal dengan pesan jelas bila password pengelola kosong atau kurang dari 12 karakter', function () {
    isiKredensialSeeder();
    config(['seeder.pengelola.password' => 'pendek']);

    expect(fn () => $this->seed(AkunAwalSeeder::class))
        ->toThrow(RuntimeException::class, 'SEED_PENGELOLA_PASSWORD');
});

it('membuat tepat satu pemilik, satu pengelola, dan satu baris pengaturan', function () {
    isiKredensialSeeder();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('peran', PeranPengguna::Pemilik->value)->count())->toBe(1)
        ->and(User::query()->where('peran', PeranPengguna::Pengelola->value)->count())->toBe(1)
        ->and(PengaturanOperasional::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'pemilik@contoh.test')->first()->isAktif())->toBeTrue();
});

it('tidak membuat data contoh di luar APP_ENV=local', function () {
    isiKredensialSeeder();

    $this->seed(DatabaseSeeder::class);

    expect(Kamar::query()->count())->toBe(0)
        ->and(PengaturanOperasional::aktif()->tanggal_jatuh_tempo)->toBeNull()
        ->and(PengaturanOperasional::aktif()->batas_peringatan_kontrak_hari)->toBeNull();
});

it('aman dijalankan dua kali tanpa menggandakan data', function () {
    isiKredensialSeeder();
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);
    $hitung = fn () => [
        User::query()->count(),
        PengaturanOperasional::query()->count(),
        Kamar::query()->count(),
        Penghuni::query()->count(),
        Kontrak::query()->count(),
        Tagihan::query()->count(),
        TiketKeluhan::query()->count(),
    ];
    $pertama = $hitung();

    $this->seed(DatabaseSeeder::class);

    expect($hitung())->toBe($pertama);
});

it('menjaga pengaturan yang sudah diubah pemilik saat seeder dijalankan ulang', function () {
    isiKredensialSeeder();

    $this->seed(DatabaseSeeder::class);
    PengaturanOperasional::aktif()->update(['tanggal_jatuh_tempo' => 5]);
    $this->seed(DatabaseSeeder::class);

    expect(PengaturanOperasional::aktif()->tanggal_jatuh_tempo)->toBe(5);
});

it('menghasilkan data contoh yang mencakup semua status penting saat APP_ENV=local', function () {
    isiKredensialSeeder();
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    expect(Tagihan::tunggakan()->count())->toBe(1)
        ->and(Kamar::kosong()->pluck('nomor_kamar')->sort()->values()->all())->toBe(['07', '08'])
        ->and(Kamar::terisi()->count())->toBe(6)
        ->and(Kontrak::akanBerakhir(30)->count())->toBeGreaterThanOrEqual(1)
        ->and(Penghuni::query()->count())->toBe(7)
        ->and(Kontrak::aktif()->count())->toBe(6)
        ->and(PengaturanOperasional::aktif()->tanggal_jatuh_tempo)->toBe(10)
        ->and(PengaturanOperasional::aktif()->batas_peringatan_kontrak_hari)->toBe(30);

    $statusTagihan = Tagihan::query()->get()->countBy(fn ($t) => $t->status_tagihan->value)->all();

    expect($statusTagihan)->toBe(['lunas' => 3, 'menunggu_verifikasi' => 1, 'belum_dibayar' => 2]);

    $selesai = TiketKeluhan::query()->where('status_tiket', StatusTiket::Selesai->value)->first();

    expect(TiketKeluhan::query()->count())->toBe(2)
        ->and(TiketKeluhan::query()->where('status_tiket', StatusTiket::Diajukan->value)->count())->toBe(1)
        ->and($selesai->vendor)->not->toBeNull()
        ->and($selesai->biaya_perbaikan)->not->toBeNull()
        ->and($selesai->riwayatStatus)->toHaveCount(4)
        ->and(SinkronisasiErp::query()->count())->toBe(0);
});

it('mengenkripsi nomor identitas penghuni pada data contoh', function () {
    isiKredensialSeeder();
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    $penghuni = Penghuni::query()->first();
    $mentah = DB::table('penghuni')->where('id', $penghuni->id)->value('no_identitas_enc');

    expect($mentah)->not->toContain($penghuni->no_identitas_enc);
});

it('DataContohSeeder berhenti dengan pesan jelas bila akun pengelola belum ada', function () {
    $this->app['env'] = 'local';

    expect(fn () => $this->seed(DataContohSeeder::class))
        ->toThrow(RuntimeException::class, 'AkunAwalSeeder');
});
