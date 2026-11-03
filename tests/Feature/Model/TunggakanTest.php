<?php

use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/*
| Aturan tunggakan ATB-05. Jatuh tempo semua tagihan: 10 November 2026.
*/

afterEach(fn () => Carbon::setTestNow());

function waktuWib(string $waktu): Carbon
{
    return Carbon::parse($waktu, 'Asia/Jakarta');
}

function tagihanJatuhTempo10Nov(string $status = 'belumDibayar'): Tagihan
{
    return Tagihan::factory()->{$status}()->create([
        'kontrak_id' => Kontrak::factory()->create(),
        'periode' => '2026-11-01',
        'jatuh_tempo' => '2026-11-10',
    ]);
}

function unggahBukti(Tagihan $tagihan, string $waktuUnggah, string $status = 'menunggu'): Pembayaran
{
    return Pembayaran::factory()->{$status}()->create([
        'tagihan_id' => $tagihan,
        'diunggah_pada' => waktuWib($waktuUnggah),
    ]);
}

function tunggakanPada(Tagihan $tagihan, CarbonInterface $tanggal): bool
{
    return Tagihan::tunggakan($tanggal)->whereKey($tagihan->id)->exists();
}

it('ATB-05: tagihan belum dibayar pada hari jatuh tempo (10 November) bukan tunggakan', function () {
    Carbon::setTestNow(waktuWib('2026-11-10 12:00'));
    $tagihan = tagihanJatuhTempo10Nov();

    expect($tagihan->isTunggakan())->toBeFalse()
        ->and(tunggakanPada($tagihan, now()))->toBeFalse()
        ->and($tagihan->hari_terlambat)->toBe(0);
});

it('ATB-05: tagihan belum dibayar sehari setelah jatuh tempo (11 November) menjadi tunggakan 1 hari', function () {
    Carbon::setTestNow(waktuWib('2026-11-11 00:05'));
    $tagihan = tagihanJatuhTempo10Nov();

    expect($tagihan->isTunggakan())->toBeTrue()
        ->and(tunggakanPada($tagihan, now()))->toBeTrue()
        ->and($tagihan->hari_terlambat)->toBe(1);
});

it('ATB-05: bukti menunggu yang diunggah 10 November pukul 23.00 WIB bukan tunggakan saat dicek 15 November', function () {
    Carbon::setTestNow(waktuWib('2026-11-15 08:00'));
    $tagihan = tagihanJatuhTempo10Nov('menungguVerifikasi');
    unggahBukti($tagihan, '2026-11-10 23:00');

    expect($tagihan->isTunggakan())->toBeFalse()
        ->and(tunggakanPada($tagihan, now()))->toBeFalse()
        ->and($tagihan->hari_terlambat)->toBe(0);
});

it('ATB-05: bukti menunggu yang diunggah 12 November tetap tunggakan saat dicek 15 November', function () {
    Carbon::setTestNow(waktuWib('2026-11-15 08:00'));
    $tagihan = tagihanJatuhTempo10Nov('menungguVerifikasi');
    unggahBukti($tagihan, '2026-11-12 09:00');

    expect($tagihan->isTunggakan())->toBeTrue()
        ->and(tunggakanPada($tagihan, now()))->toBeTrue()
        ->and($tagihan->hari_terlambat)->toBe(5);
});

it('ATB-05: bukti yang diunggah 9 November lalu ditolak tetap tunggakan 5 hari saat dicek 15 November', function () {
    Carbon::setTestNow(waktuWib('2026-11-15 08:00'));
    $tagihan = tagihanJatuhTempo10Nov();
    unggahBukti($tagihan, '2026-11-09 10:00', 'ditolak');

    expect($tagihan->isTunggakan())->toBeTrue()
        ->and(tunggakanPada($tagihan, now()))->toBeTrue()
        ->and($tagihan->hari_terlambat)->toBe(5);
});

it('ATB-05: tagihan lunas bukan tunggakan saat dicek 30 November', function () {
    Carbon::setTestNow(waktuWib('2026-11-30 08:00'));
    $tagihan = tagihanJatuhTempo10Nov('lunas');
    unggahBukti($tagihan, '2026-11-08 10:00', 'diterima');

    expect($tagihan->isTunggakan())->toBeFalse()
        ->and(tunggakanPada($tagihan, now()))->toBeFalse()
        ->and($tagihan->hari_terlambat)->toBe(0);
});

it('ATB-05: scope tunggakan() dan isTunggakan() memberi hasil sama untuk keenam kasus', function () {
    $kasus = [
        // [status tagihan, [waktu unggah, status bukti] atau null, tanggal cek, diharapkan]
        'jatuh tempo, belum dibayar' => ['belumDibayar', null, '2026-11-10 12:00', false],
        'sehari terlambat' => ['belumDibayar', null, '2026-11-11 12:00', true],
        'bukti menunggu tepat waktu' => ['menungguVerifikasi', ['2026-11-10 23:00', 'menunggu'], '2026-11-15 12:00', false],
        'bukti menunggu terlambat' => ['menungguVerifikasi', ['2026-11-12 09:00', 'menunggu'], '2026-11-15 12:00', true],
        'bukti tepat waktu ditolak' => ['belumDibayar', ['2026-11-09 10:00', 'ditolak'], '2026-11-15 12:00', true],
        'lunas' => ['lunas', ['2026-11-08 10:00', 'diterima'], '2026-11-30 12:00', false],
    ];

    foreach ($kasus as $nama => [$status, $bukti, $tanggalCek, $diharapkan]) {
        $tagihan = tagihanJatuhTempo10Nov($status);

        if ($bukti !== null) {
            unggahBukti($tagihan, ...$bukti);
        }

        $tanggal = waktuWib($tanggalCek);

        expect($tagihan->isTunggakan($tanggal))->toBe($diharapkan, "isTunggakan: {$nama}")
            ->and(tunggakanPada($tagihan, $tanggal))->toBe($diharapkan, "scope: {$nama}");
    }
});

it('ATB-05: batas tanggal memakai kalender WIB, bukan UTC', function () {
    Carbon::setTestNow(waktuWib('2026-11-15 08:00'));
    $tagihan = tagihanJatuhTempo10Nov('menungguVerifikasi');
    // 11 Nov 03.00 WIB = 10 Nov 20.00 UTC; menurut kalender WIB sudah lewat jatuh tempo.
    unggahBukti($tagihan, '2026-11-11 03:00');

    expect($tagihan->isTunggakan())->toBeTrue()
        ->and(tunggakanPada($tagihan, now()))->toBeTrue();
});

it('ATB-05: tunggakan tidak disimpan sebagai kolom', function () {
    expect(Schema::hasColumn('tagihan', 'tunggakan'))->toBeFalse()
        ->and(Schema::hasColumn('tagihan', 'is_tunggakan'))->toBeFalse();
});
