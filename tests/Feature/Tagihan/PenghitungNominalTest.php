<?php

use App\Exceptions\KonfigurasiTagihanTidakValid;
use App\Models\Kontrak;
use App\Support\Tagihan\PenghitungNominal;
use Carbon\CarbonImmutable;

function kontrakTanpaSimpan(string $mulai, string $berakhir, int $tarif): Kontrak
{
    return (new Kontrak)->forceFill([
        'tanggal_mulai' => $mulai,
        'tanggal_berakhir' => $berakhir,
        'tarif_bulanan' => $tarif,
    ]);
}

it('strategi penuh menagih tarif kontrak walau kontrak tidak penuh sebulan', function () {
    $penghitung = new PenghitungNominal('penuh');
    $kontrak = kontrakTanpaSimpan('2026-11-21', '2027-11-20', 1500000);

    expect($penghitung->untuk($kontrak, CarbonImmutable::parse('2026-11-01')))->toBe(1500000);
});

it('strategi prorata: tarif 1.500.000 mulai 21 November (10 dari 30 hari) menjadi 500.000', function () {
    $penghitung = new PenghitungNominal('prorata');
    $kontrak = kontrakTanpaSimpan('2026-11-21', '2027-11-20', 1500000);

    expect($penghitung->hariTerisi($kontrak, CarbonImmutable::parse('2026-11-01')))->toBe(10)
        ->and($penghitung->untuk($kontrak, CarbonImmutable::parse('2026-11-01')))->toBe(500000)
        ->and($penghitung->penuhSebulan($kontrak, CarbonImmutable::parse('2026-11-01')))->toBeFalse();
});

it('strategi prorata: pembulatan di akhir, tarif 1.250.000 mulai 17 Maret (15 dari 31 hari) menjadi 604.839', function () {
    $penghitung = new PenghitungNominal('prorata');
    $kontrak = kontrakTanpaSimpan('2026-03-17', '2027-03-16', 1250000);

    expect($penghitung->hariTerisi($kontrak, CarbonImmutable::parse('2026-03-01')))->toBe(15)
        ->and($penghitung->untuk($kontrak, CarbonImmutable::parse('2026-03-01')))->toBe(604839);
});

it('strategi prorata: kontrak penuh sebulan tetap sama dengan tarif', function () {
    $penghitung = new PenghitungNominal('prorata');
    $kontrak = kontrakTanpaSimpan('2026-01-01', '2027-01-01', 1250000);

    foreach (['2026-02-01', '2026-03-01', '2026-11-01'] as $periode) {
        expect($penghitung->untuk($kontrak, CarbonImmutable::parse($periode)))->toBe(1250000)
            ->and($penghitung->penuhSebulan($kontrak, CarbonImmutable::parse($periode)))->toBeTrue();
    }
});

it('strategi prorata: kontrak yang berakhir di tengah bulan dihitung sampai tanggal berakhirnya (termasuk)', function () {
    $penghitung = new PenghitungNominal('prorata');
    $kontrak = kontrakTanpaSimpan('2026-01-01', '2026-11-20', 1500000);

    expect($penghitung->hariTerisi($kontrak, CarbonImmutable::parse('2026-11-01')))->toBe(20)
        ->and($penghitung->untuk($kontrak, CarbonImmutable::parse('2026-11-01')))->toBe(1000000)
        ->and($penghitung->hariTerisi($kontrak, CarbonImmutable::parse('2026-12-01')))->toBe(0);
});

it('config tidak dikenal memunculkan exception konfigurasi yang jelas saat dipakai', function () {
    config(['kirana.tagihan.parsial' => 'setengah']);

    $kontrak = kontrakTanpaSimpan('2026-01-01', '2027-01-01', 1250000);

    expect(fn () => (new PenghitungNominal)->untuk($kontrak, CarbonImmutable::parse('2026-11-01')))
        ->toThrow(KonfigurasiTagihanTidakValid::class, 'TAGIHAN_PARSIAL "setengah" tidak dikenal');
});

it('strategi bawaan adalah penuh', function () {
    expect(config('kirana.tagihan.parsial'))->toBe('penuh')
        ->and((new PenghitungNominal)->strategi())->toBe('penuh');
});

it('jatuh tempo mengikuti tanggal pengaturan; kontrak yang mulai setelahnya jatuh tempo pada tanggal mulai', function () {
    $penghitung = new PenghitungNominal;
    $periode = CarbonImmutable::parse('2026-11-01');

    $lama = kontrakTanpaSimpan('2026-01-01', '2027-01-01', 1250000);
    $barus = kontrakTanpaSimpan('2026-11-20', '2027-11-19', 1250000);
    $tepat = kontrakTanpaSimpan('2026-11-10', '2027-11-09', 1250000);

    expect($penghitung->jatuhTempoUntuk($lama, $periode, 10)->toDateString())->toBe('2026-11-10')
        ->and($penghitung->jatuhTempoUntuk($barus, $periode, 10)->toDateString())->toBe('2026-11-20')
        ->and($penghitung->jatuhTempoUntuk($tepat, $periode, 10)->toDateString())->toBe('2026-11-10');
});
