<?php

use App\Enums\StatusKontrak;
use App\Exceptions\KonfigurasiTagihanTidakValid;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return list<int> ID kontrak yang berhak ditagih, urut ID.
 */
function idKontrakTertagih(string $periode, ?string $strategi = 'penuh'): array
{
    return Kontrak::query()
        ->tertagihPadaPeriode(CarbonImmutable::parse($periode), $strategi)
        ->orderBy('kontrak.id')
        ->pluck('kontrak.id')
        ->all();
}

it('F-10 butir 1: kontrak aktif penuh sebulan ditagih, kontrak yang baru mulai bulan depan tidak', function () {
    $berjalan = buatKontrakUji('01', 'Anisa Putri', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-01-01']);
    $bulanDepan = buatKontrakUji('02', 'Rina Maharani', ['tanggal_mulai' => '2026-12-01', 'tanggal_berakhir' => '2027-12-01']);
    $sudahLewat = buatKontrakUji('03', 'Salsa Nabila', ['tanggal_mulai' => '2025-01-01', 'tanggal_berakhir' => '2026-10-31']);

    expect(idKontrakTertagih('2026-11-01'))->toBe([$berjalan->id])
        ->and(idKontrakTertagih('2026-12-01'))->toBe([$berjalan->id, $bulanDepan->id])
        ->and(idKontrakTertagih('2026-10-01'))->toBe([$berjalan->id, $sudahLewat->id]);
});

it('F-10 butir 4: kontrak berakhir karena Akhiri atau berakhir tanpa penerus tidak ditagih', function () {
    // Diakhiri lebih awal: tanggal berakhir dimajukan, jadi tidak lagi menyentuh November.
    $diakhiri = buatKontrakUji('01', 'Anisa Putri', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-01-01']);
    $diakhiri->akhiri(CarbonImmutable::parse('2026-10-20'));

    // Berakhir tanpa penerus, tetapi rentangnya masih menyentuh November.
    buatKontrakUji('02', 'Rina Maharani', [
        'tanggal_mulai' => '2026-01-01',
        'tanggal_berakhir' => '2026-11-20',
        'status_kontrak' => StatusKontrak::Berakhir,
    ]);

    expect(idKontrakTertagih('2026-11-01'))->toBe([])
        ->and(idKontrakTertagih('2026-11-01', 'prorata'))->toBe([])
        ->and(idKontrakTertagih('2026-10-01'))->toBe([]);
});

it('perpanjangan, strategi penuh: bulan pergantian hanya kontrak lama yang ditagih, bulan berikutnya kontrak baru', function () {
    $lama = buatKontrakUji('01', 'Anisa Putri', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-20']);
    $baru = $lama->perpanjang(CarbonImmutable::parse('2027-11-20'), 1500000);

    expect($lama->fresh()->status_kontrak)->toBe(StatusKontrak::Berakhir)
        ->and($baru->tanggal_mulai->toDateString())->toBe('2026-11-21')
        ->and(idKontrakTertagih('2026-11-01', 'penuh'))->toBe([$lama->id])
        ->and(idKontrakTertagih('2026-12-01', 'penuh'))->toBe([$baru->id])
        ->and(idKontrakTertagih('2026-10-01', 'penuh'))->toBe([$lama->id]);
});

it('perpanjangan, strategi prorata: bulan pergantian kedua kontrak ditagih sesuai harinya', function () {
    $lama = buatKontrakUji('01', 'Anisa Putri', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-20']);
    $baru = $lama->perpanjang(CarbonImmutable::parse('2027-11-20'), 1500000);

    expect(idKontrakTertagih('2026-11-01', 'prorata'))->toBe([$lama->id, $baru->id])
        ->and(idKontrakTertagih('2026-12-01', 'prorata'))->toBe([$baru->id]);
});

it('strategi bawaan dibaca dari config kirana.tagihan.parsial', function () {
    $lama = buatKontrakUji('01', 'Anisa Putri', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-11-20']);
    $baru = $lama->perpanjang(CarbonImmutable::parse('2027-11-20'), 1500000);

    config(['kirana.tagihan.parsial' => 'penuh']);
    expect(idKontrakTertagih('2026-11-01', null))->toBe([$lama->id]);

    config(['kirana.tagihan.parsial' => 'prorata']);
    expect(idKontrakTertagih('2026-11-01', null))->toBe([$lama->id, $baru->id]);

    config(['kirana.tagihan.parsial' => 'acak']);
    expect(fn () => idKontrakTertagih('2026-11-01', null))->toThrow(KonfigurasiTagihanTidakValid::class, 'acak');
});

it('F-10 butir 3: kontrak yang sudah punya tagihan periode itu dihitung dilewati, bukan dibuat ulang', function () {
    $sudah = buatKontrakUji('01', 'Anisa Putri');
    buatKontrakUji('02', 'Rina Maharani');
    Tagihan::factory()->create(['kontrak_id' => $sudah->id, 'periode' => '2026-11-01', 'jatuh_tempo' => '2026-11-10']);

    $pratinjau = Tagihan::pratinjauPeriode(CarbonImmutable::parse('2026-11-01'));

    expect($pratinjau->dibuat)->toBe(1)
        ->and($pratinjau->dilewati)->toBe(1)
        ->and($pratinjau->kontrakBerhak())->toBe(2)
        ->and(Tagihan::query()->count())->toBe(1);
});

it('jumlah query penyusunan rencana tidak bertambah saat jumlah kontrak bertambah', function () {
    $hitung = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Tagihan::pratinjauPeriode(CarbonImmutable::parse('2026-11-01'));
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    buatKontrakUji('01', 'Anisa Putri');
    $sedikit = $hitung();

    foreach (['02', '03', '04', '05', '06'] as $nomor) {
        buatKontrakUji($nomor, "Penghuni {$nomor}");
    }

    expect($hitung())->toBe($sedikit);
});
