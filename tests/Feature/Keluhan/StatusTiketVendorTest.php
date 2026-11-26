<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTiket;
use App\Exceptions\StatusTiketBerubah;
use App\Models\Fasilitas;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    // Pengiriman ke Odoo diuji di tests/Feature/Integrasi; di sini antreannya saja.
    Queue::fake();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Tiket milik $vendor pada status $status, dengan riwayat sampai status itu. Nomor mengikuti $nomor.
 *
 * @return array{0: TiketKeluhan, 1: Vendor}
 */
function tiketStatusUji(StatusTiket $status, string $nomor = 'KLH-0001', ?Fasilitas $fasilitas = null): array
{
    $vendor = Vendor::factory()->create();
    $tiket = TiketKeluhan::factory()->create(array_filter([
        'nomor_tiket' => $nomor,
        'vendor_id' => $status === StatusTiket::Diajukan ? null : $vendor->id,
        'fasilitas_id' => $fasilitas?->id,
        'status_tiket' => $status,
        'biaya_perbaikan' => $status === StatusTiket::Selesai ? 90000 : null,
    ], fn ($nilai) => $nilai !== null));

    return [$tiket, $vendor];
}

it('F-19: mulaiKerjakan mengubah ditugaskan menjadi dikerjakan dan mencatat satu riwayat oleh vendor', function () {
    [$tiket, $vendor] = tiketStatusUji(StatusTiket::Ditugaskan);

    $tiket->mulaiKerjakan($vendor->user);

    expect($tiket->status_tiket)->toBe(StatusTiket::Dikerjakan)
        ->and($tiket->fresh()->status_tiket)->toBe(StatusTiket::Dikerjakan);

    $riwayat = $tiket->riwayatStatus()->get();
    expect($riwayat)->toHaveCount(1)
        ->and($riwayat[0]->status_sebelum)->toBe(StatusTiket::Ditugaskan)
        ->and($riwayat[0]->status_sesudah)->toBe(StatusTiket::Dikerjakan)
        ->and($riwayat[0]->diubah_oleh)->toBe($vendor->user->id)
        ->and($riwayat[0]->waktu_perubahan->equalTo(now()))->toBeTrue();
});

it('F-19: mulaiKerjakan dari status lain ditolak tanpa perubahan dan tanpa riwayat', function (StatusTiket $status) {
    [$tiket, $vendor] = tiketStatusUji($status);

    expect(fn () => $tiket->mulaiKerjakan($vendor->user))
        ->toThrow(StatusTiketBerubah::class, 'Status tiket ini sudah berubah. Halaman sudah diperbarui.');

    expect($tiket->fresh()->status_tiket)->toBe($status)
        ->and($tiket->riwayatStatus()->count())->toBe(0);
})->with([StatusTiket::Diajukan, StatusTiket::Dikerjakan, StatusTiket::Selesai]);

it('F-19: salinan tiket yang usang tetap ditolak karena tiket dibaca ulang dengan kunci', function () {
    [$tiket, $vendor] = tiketStatusUji(StatusTiket::Ditugaskan);
    $usang = TiketKeluhan::find($tiket->id);

    $tiket->mulaiKerjakan($vendor->user);

    expect(fn () => $usang->mulaiKerjakan($vendor->user))->toThrow(StatusTiketBerubah::class)
        ->and($tiket->riwayatStatus()->count())->toBe(1);
});

it('F-20: selesaikan mengubah dikerjakan menjadi selesai, menyimpan biaya, riwayat, dan satu antrean biaya perbaikan', function () {
    [$tiket, $vendor] = tiketStatusUji(StatusTiket::Dikerjakan);

    $tiket->selesaikan(150000, $vendor->user);

    $segar = $tiket->fresh();
    expect($segar->status_tiket)->toBe(StatusTiket::Selesai)
        ->and((int) $segar->biaya_perbaikan)->toBe(150000);

    $riwayat = $tiket->riwayatStatus()->get();
    expect($riwayat)->toHaveCount(1)
        ->and($riwayat[0]->status_sebelum)->toBe(StatusTiket::Dikerjakan)
        ->and($riwayat[0]->status_sesudah)->toBe(StatusTiket::Selesai)
        ->and($riwayat[0]->diubah_oleh)->toBe($vendor->user->id);

    $antrean = SinkronisasiErp::query()->where('tiket_id', $tiket->id)->get();
    expect($antrean)->toHaveCount(1)
        ->and($antrean[0]->jenis_transaksi)->toBe(JenisTransaksiErp::BiayaPerbaikan)
        ->and($antrean[0]->referensi_unik)->toBe('KPK-TKT-0001-B')
        ->and($antrean[0]->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu)
        ->and($antrean[0]->pembayaran_id)->toBeNull();
});

it('F-24: fasilitas dengan odoo_equipment_id menambah satu antrean pemeliharaan; tanpa equipment tidak ada', function () {
    $dengan = Fasilitas::factory()->create(['odoo_equipment_id' => 42]);
    [$tiketDengan, $vendorDengan] = tiketStatusUji(StatusTiket::Dikerjakan, 'KLH-0001', $dengan);
    [$tiketTanpa, $vendorTanpa] = tiketStatusUji(StatusTiket::Dikerjakan, 'KLH-0002', Fasilitas::factory()->create());

    $tiketDengan->selesaikan(50000, $vendorDengan->user);
    $tiketTanpa->selesaikan(50000, $vendorTanpa->user);

    expect(SinkronisasiErp::query()->where('tiket_id', $tiketDengan->id)->orderBy('referensi_unik')->pluck('status_sinkronisasi', 'referensi_unik')->map->value->all())
        ->toBe(['KPK-TKT-0001-B' => 'menunggu', 'KPK-TKT-0001-M' => 'menunggu']);

    expect(SinkronisasiErp::query()->where('tiket_id', $tiketDengan->id)->where('jenis_transaksi', 'pemeliharaan')->count())->toBe(1)
        ->and(SinkronisasiErp::query()->where('tiket_id', $tiketTanpa->id)->pluck('referensi_unik')->all())->toBe(['KPK-TKT-0002-B'])
        ->and(SinkronisasiErp::query()->where('jenis_transaksi', 'pemeliharaan')->where('tiket_id', $tiketTanpa->id)->exists())->toBeFalse();
});

it('F-20: selesaikan dari status selain dikerjakan ditolak tanpa perubahan dan tanpa antrean baru', function (StatusTiket $status) {
    [$tiket, $vendor] = tiketStatusUji($status);
    $biayaAwal = $tiket->biaya_perbaikan;

    expect(fn () => $tiket->selesaikan(10000, $vendor->user))
        ->toThrow(StatusTiketBerubah::class, 'Status tiket ini sudah berubah. Halaman sudah diperbarui.');

    expect($tiket->fresh()->status_tiket)->toBe($status)
        ->and($tiket->fresh()->biaya_perbaikan)->toBe($biayaAwal)
        ->and($tiket->riwayatStatus()->count())->toBe(0)
        ->and(SinkronisasiErp::query()->count())->toBe(0);
})->with([StatusTiket::Diajukan, StatusTiket::Ditugaskan, StatusTiket::Selesai]);

it('F-20: biaya 0 diterima; biaya negatif ditolak tanpa perubahan', function () {
    [$nol, $vendorNol] = tiketStatusUji(StatusTiket::Dikerjakan, 'KLH-0001');
    $nol->selesaikan(0, $vendorNol->user);

    expect($nol->fresh()->status_tiket)->toBe(StatusTiket::Selesai)
        ->and((int) $nol->fresh()->biaya_perbaikan)->toBe(0);

    [$negatif, $vendorNegatif] = tiketStatusUji(StatusTiket::Dikerjakan, 'KLH-0002');

    expect(fn () => $negatif->selesaikan(-1, $vendorNegatif->user))->toThrow(InvalidArgumentException::class);

    expect($negatif->fresh()->status_tiket)->toBe(StatusTiket::Dikerjakan)
        ->and($negatif->riwayatStatus()->count())->toBe(0)
        ->and(SinkronisasiErp::query()->where('tiket_id', $negatif->id)->exists())->toBeFalse();
});

it('F-20: referensiErp mempertahankan angka nomor tiket', function () {
    expect((new TiketKeluhan(['nomor_tiket' => 'KLH-0001']))->referensiErp('B'))->toBe('KPK-TKT-0001-B')
        ->and((new TiketKeluhan(['nomor_tiket' => 'KLH-0001']))->referensiErp('M'))->toBe('KPK-TKT-0001-M')
        ->and((new TiketKeluhan(['nomor_tiket' => 'KLH-10000']))->referensiErp('B'))->toBe('KPK-TKT-10000-B');
});

it('F-19: status tidak bisa dilompati atau dimundurkan dan mengikuti StatusTiket::berikutnya', function () {
    expect(StatusTiket::Ditugaskan->berikutnya())->toBe(StatusTiket::Dikerjakan)
        ->and(StatusTiket::Dikerjakan->berikutnya())->toBe(StatusTiket::Selesai);

    [$tiket, $vendor] = tiketStatusUji(StatusTiket::Ditugaskan);

    // Melompat ke selesai ditolak; setelah selesai, mulai lagi (mundur) juga ditolak.
    expect(fn () => $tiket->selesaikan(1000, $vendor->user))->toThrow(StatusTiketBerubah::class);

    $tiket->mulaiKerjakan($vendor->user);
    $tiket->selesaikan(1000, $vendor->user);

    expect(fn () => $tiket->mulaiKerjakan($vendor->user))->toThrow(StatusTiketBerubah::class)
        ->and($tiket->fresh()->status_tiket)->toBe(StatusTiket::Selesai)
        ->and($tiket->riwayatStatus()->count())->toBe(2);
});

it('F-19: scope milikVendor hanya tiket vendor itu', function () {
    [$milik, $vendor] = tiketStatusUji(StatusTiket::Ditugaskan, 'KLH-0001');
    tiketStatusUji(StatusTiket::Ditugaskan, 'KLH-0002');

    expect(TiketKeluhan::milikVendor($vendor)->pluck('id')->all())->toBe([$milik->id]);
});
