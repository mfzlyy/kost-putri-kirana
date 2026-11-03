<?php

use App\Enums\StatusKontrak;
use App\Enums\StatusTiket;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\TiketKeluhan;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('menghitung status hunian kamar dari kontrak aktif (ATB-01)', function () {
    $kamar = Kamar::factory()->create();

    expect($kamar->status_hunian)->toBe('kosong');

    $kontrak = Kontrak::factory()->aktif()->create(['kamar_id' => $kamar]);

    expect($kamar->status_hunian)->toBe('terisi')
        ->and(Kamar::terisi()->pluck('id')->all())->toBe([$kamar->id])
        ->and(Kamar::kosong()->count())->toBe(0);

    $kontrak->forceFill(['status_kontrak' => StatusKontrak::Berakhir])->save();

    expect($kamar->status_hunian)->toBe('kosong')
        ->and(Kamar::kosong()->pluck('id')->all())->toBe([$kamar->id])
        ->and(Kamar::terisi()->count())->toBe(0);
});

it('tidak menyimpan status hunian sebagai kolom', function () {
    expect(Schema::hasColumn('kamar', 'status_hunian'))->toBeFalse()
        ->and(Schema::hasColumn('kamar', 'status'))->toBeFalse();
});

it('memakai relasi kontrakAktif yang sudah dimuat untuk status hunian', function () {
    Kontrak::factory()->aktif()->create();
    Kamar::factory()->create();

    $daftar = Kamar::query()->with('kontrakAktif')->orderBy('id')->get();

    expect($daftar->pluck('status_hunian')->all())->toBe(['terisi', 'kosong']);
});

it('menghitung sisaHari terhadap hari ini di zona waktu aplikasi', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 23:30:00', 'Asia/Jakarta'));

    $kontrak = Kontrak::factory()->create(['tanggal_berakhir' => '2026-11-23']);

    expect($kontrak->sisaHari())->toBe(20)
        ->and($kontrak->sisaHari(Carbon::parse('2026-11-23')))->toBe(0)
        ->and($kontrak->sisaHari(Carbon::parse('2026-11-25')))->toBe(-2);
});

it('mengembalikan hanya kontrak aktif yang berakhir dalam 30 hari lewat akanBerakhir(30)', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00', 'Asia/Jakarta'));

    $dalamBatas = Kontrak::factory()->aktif()->create(['tanggal_berakhir' => '2026-11-23']);
    $tepatBatas = Kontrak::factory()->aktif()->create(['tanggal_berakhir' => '2026-12-03']);
    Kontrak::factory()->aktif()->create(['tanggal_berakhir' => '2026-12-04']);
    Kontrak::factory()->aktif()->create(['tanggal_berakhir' => '2027-06-30']);
    Kontrak::factory()->berakhir()->create(['tanggal_berakhir' => '2026-11-20']);

    expect(Kontrak::akanBerakhir(30)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$dalamBatas->id, $tepatBatas->id])->sort()->values()->all());
});

it('menghasilkan nomor tiket berikutnya dari nomor terbesar', function () {
    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-0001');

    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0002']);

    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-0003');

    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0010']);
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0009']);

    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-0011');
});

it('menghitung nomor tiket secara numerik setelah KLH-9999', function () {
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-9999']);

    expect(TiketKeluhan::nomorBerikutnya())->toBe('KLH-10000');
});

it('menyatakan siap terbit tagihan hanya bila tanggal jatuh tempo terisi', function () {
    $pengaturan = PengaturanOperasional::factory()->create();

    expect($pengaturan->sudahSiapTerbitTagihan())->toBeFalse();

    $pengaturan->update(['tanggal_jatuh_tempo' => 10]);

    expect(PengaturanOperasional::aktif()->sudahSiapTerbitTagihan())->toBeTrue();
});

it('membuat baris pengaturan kosong bila belum ada', function () {
    expect(PengaturanOperasional::query()->count())->toBe(0);

    $pengaturan = PengaturanOperasional::aktif();

    expect(PengaturanOperasional::query()->count())->toBe(1)
        ->and($pengaturan->sudahSiapTerbitTagihan())->toBeFalse();
});

it('mengurutkan status tiket sesuai ATB-07', function () {
    expect(StatusTiket::Diajukan->berikutnya())->toBe(StatusTiket::Ditugaskan)
        ->and(StatusTiket::Ditugaskan->berikutnya())->toBe(StatusTiket::Dikerjakan)
        ->and(StatusTiket::Dikerjakan->berikutnya())->toBe(StatusTiket::Selesai)
        ->and(StatusTiket::Selesai->berikutnya())->toBeNull();
});
