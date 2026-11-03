<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;

it('menghubungkan User ke profil penghuni dan vendor', function () {
    $penghuni = Penghuni::factory()->create();
    $vendor = Vendor::factory()->create();

    expect($penghuni->user)->toBeInstanceOf(User::class)
        ->and($penghuni->user->penghuni->is($penghuni))->toBeTrue()
        ->and($vendor->user->vendor->is($vendor))->toBeTrue()
        ->and($penghuni->user->vendor)->toBeNull();
});

it('menghubungkan Penghuni ke kontrak, kontrak aktif, dan tiket keluhan', function () {
    $penghuni = Penghuni::factory()->create();
    $lama = Kontrak::factory()->berakhir()->create(['penghuni_id' => $penghuni]);
    $aktif = Kontrak::factory()->aktif()->create(['penghuni_id' => $penghuni]);
    $tiket = TiketKeluhan::factory()->create(['penghuni_id' => $penghuni]);

    expect($penghuni->kontrak)->toHaveCount(2)
        ->and($penghuni->kontrakAktif->is($aktif))->toBeTrue()
        ->and($penghuni->kontrakAktif->is($lama))->toBeFalse()
        ->and($penghuni->tiketKeluhan->pluck('id')->all())->toBe([$tiket->id]);
});

it('menghubungkan Vendor ke tiket keluhan', function () {
    $vendor = Vendor::factory()->create();
    $tiket = TiketKeluhan::factory()->create(['vendor_id' => $vendor]);

    expect($vendor->tiketKeluhan->pluck('id')->all())->toBe([$tiket->id])
        ->and($tiket->vendor->is($vendor))->toBeTrue();
});

it('menghubungkan PengaturanOperasional ke pengguna pembaruinya dan memakai kunci tetap 1', function () {
    $pengelola = User::factory()->pengelola()->create();
    PengaturanOperasional::factory()->create(['diperbarui_oleh' => $pengelola->id]);

    $pengaturan = PengaturanOperasional::aktif();

    expect($pengaturan->id)->toBe(1)
        ->and($pengaturan->incrementing)->toBeFalse()
        ->and($pengaturan->diperbaruiOleh->is($pengelola))->toBeTrue();
});

it('menghubungkan Kamar ke kontrak, kontrak aktif, dan fasilitas', function () {
    $kamar = Kamar::factory()->create();
    Kontrak::factory()->berakhir()->create(['kamar_id' => $kamar]);
    $aktif = Kontrak::factory()->aktif()->create(['kamar_id' => $kamar]);
    $ac = Fasilitas::factory()->create(['kamar_id' => $kamar, 'nama_fasilitas' => 'AC']);

    expect($kamar->kontrak)->toHaveCount(2)
        ->and($kamar->kontrakAktif->is($aktif))->toBeTrue()
        ->and($kamar->fasilitas->pluck('id')->all())->toBe([$ac->id]);
});

it('menghubungkan Kontrak ke penghuni, kamar, dan tagihan', function () {
    $kontrak = Kontrak::factory()->create();
    $tagihan = Tagihan::factory()->create(['kontrak_id' => $kontrak]);

    expect($kontrak->penghuni)->toBeInstanceOf(Penghuni::class)
        ->and($kontrak->kamar)->toBeInstanceOf(Kamar::class)
        ->and($kontrak->tagihan->pluck('id')->all())->toBe([$tagihan->id])
        ->and((string) $kontrak->tarif_bulanan)->toBe((string) $kontrak->kamar->tarif_bulanan);
});

it('menghubungkan Tagihan ke kontrak dan pembayaran', function () {
    $tagihan = Tagihan::factory()->create();
    $pembayaran = Pembayaran::factory()->ditolak()->create(['tagihan_id' => $tagihan]);

    expect($tagihan->kontrak)->toBeInstanceOf(Kontrak::class)
        ->and($tagihan->pembayaran->pluck('id')->all())->toBe([$pembayaran->id])
        ->and($tagihan->periode->day)->toBe(1);
});

it('menghubungkan Pembayaran ke tagihan dan pengguna pemverifikasi', function () {
    $pembayaran = Pembayaran::factory()->diterima()->create();

    expect($pembayaran->tagihan)->toBeInstanceOf(Tagihan::class)
        ->and($pembayaran->diverifikasiOleh)->toBeInstanceOf(User::class)
        ->and($pembayaran->diverifikasiOleh->isPengelola())->toBeTrue();
});

it('menghubungkan Fasilitas ke kamar (boleh kosong) dan tiket keluhan', function () {
    $umum = Fasilitas::factory()->areaBersama()->create();
    $kamar = Fasilitas::factory()->create();
    $tiket = TiketKeluhan::factory()->create(['fasilitas_id' => $umum]);

    expect($umum->kamar)->toBeNull()
        ->and($kamar->kamar)->toBeInstanceOf(Kamar::class)
        ->and($umum->tiketKeluhan->pluck('id')->all())->toBe([$tiket->id]);
});

it('menghubungkan TiketKeluhan ke penghuni, fasilitas, vendor, riwayat berurutan, dan sinkronisasi', function () {
    $tiket = TiketKeluhan::factory()->selesai()->create();
    $pengubah = User::factory()->pengelola()->create();
    $dasar = CarbonImmutable::parse('2026-11-01 08:00:00', 'Asia/Jakarta');

    // Sengaja dibuat tidak berurutan agar urutan menurut waktu_perubahan terbukti.
    RiwayatStatusTiket::factory()->perubahan(StatusTiket::Ditugaskan, StatusTiket::Dikerjakan)
        ->create(['tiket_id' => $tiket, 'diubah_oleh' => $pengubah, 'waktu_perubahan' => $dasar->addDays(2)]);
    RiwayatStatusTiket::factory()->perubahan(null, StatusTiket::Diajukan)
        ->create(['tiket_id' => $tiket, 'diubah_oleh' => $pengubah, 'waktu_perubahan' => $dasar]);
    RiwayatStatusTiket::factory()->perubahan(StatusTiket::Diajukan, StatusTiket::Ditugaskan)
        ->create(['tiket_id' => $tiket, 'diubah_oleh' => $pengubah, 'waktu_perubahan' => $dasar->addDay()]);

    $sinkronisasi = SinkronisasiErp::factory()
        ->untukTiket($tiket, JenisTransaksiErp::BiayaPerbaikan)
        ->create();

    expect($tiket->penghuni)->toBeInstanceOf(Penghuni::class)
        ->and($tiket->fasilitas)->toBeInstanceOf(Fasilitas::class)
        ->and($tiket->vendor)->toBeInstanceOf(Vendor::class)
        ->and($tiket->riwayatStatus->pluck('status_sesudah')->all())
        ->toBe([StatusTiket::Diajukan, StatusTiket::Ditugaskan, StatusTiket::Dikerjakan])
        ->and($tiket->sinkronisasi->pluck('id')->all())->toBe([$sinkronisasi->id]);
});

it('menghubungkan RiwayatStatusTiket ke tiket dan pengguna pengubah', function () {
    $riwayat = RiwayatStatusTiket::factory()->create();

    expect($riwayat->tiket)->toBeInstanceOf(TiketKeluhan::class)
        ->and($riwayat->diubahOleh)->toBeInstanceOf(User::class)
        ->and($riwayat->status_sebelum)->toBeNull()
        ->and($riwayat->status_sesudah)->toBe(StatusTiket::Diajukan);
});

it('menghubungkan SinkronisasiErp ke pembayaran atau tiket', function () {
    $untukPembayaran = SinkronisasiErp::factory()->create();
    $tiket = TiketKeluhan::factory()->selesai()->create();
    $untukTiket = SinkronisasiErp::factory()
        ->untukTiket($tiket, JenisTransaksiErp::Pemeliharaan)
        ->create();

    expect($untukPembayaran->pembayaran)->toBeInstanceOf(Pembayaran::class)
        ->and($untukPembayaran->tiket)->toBeNull()
        ->and($untukPembayaran->referensi_unik)->toStartWith('KPK-PAY-')
        ->and($untukTiket->tiket->is($tiket))->toBeTrue()
        ->and($untukTiket->pembayaran)->toBeNull()
        ->and($untukTiket->referensi_unik)->toBe('KPK-TKT-'.substr($tiket->nomor_tiket, 4).'-M');
});

it('menyaring SinkronisasiErp menurut status lewat scope gagal() dan menunggu()', function () {
    $menunggu = SinkronisasiErp::factory()->create();
    $gagal = SinkronisasiErp::factory()->gagal()->create();
    SinkronisasiErp::factory()->terkirim()->create();

    expect(SinkronisasiErp::gagal()->pluck('id')->all())->toBe([$gagal->id])
        ->and(SinkronisasiErp::menunggu()->pluck('id')->all())->toBe([$menunggu->id]);
});

it('menyaring Pembayaran menunggu lewat scope menunggu()', function () {
    $menunggu = Pembayaran::factory()->menunggu()->create();
    Pembayaran::factory()->diterima()->create();
    Pembayaran::factory()->ditolak()->create();

    expect(Pembayaran::menunggu()->pluck('id')->all())->toBe([$menunggu->id]);
});

it('menyaring Tagihan milik penghuni dan TiketKeluhan yang ditugaskan ke vendor', function () {
    $kontrakA = Kontrak::factory()->create();
    $kontrakB = Kontrak::factory()->create();
    $tagihanA = Tagihan::factory()->create(['kontrak_id' => $kontrakA]);
    Tagihan::factory()->create(['kontrak_id' => $kontrakB]);

    $vendor = Vendor::factory()->create();
    $tiketVendor = TiketKeluhan::factory()->ditugaskan()->create(['vendor_id' => $vendor]);
    TiketKeluhan::factory()->ditugaskan()->create();
    TiketKeluhan::factory()->create();

    expect(Tagihan::milikPenghuni($kontrakA->penghuni)->pluck('id')->all())->toBe([$tagihanA->id])
        ->and(TiketKeluhan::ditugaskanKe($vendor)->pluck('id')->all())->toBe([$tiketVendor->id]);
});
