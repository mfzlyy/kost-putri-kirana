<?php

use App\Enums\StatusTagihan;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

/*
| Penandaan tunggakan di halaman Tagihan (F-15, ATB-05, TB-03).
| Jatuh tempo semua tagihan November: 10 November 2026.
*/

beforeEach(fn () => PengaturanOperasional::factory()->lengkap(10, 30)->create());

afterEach(fn () => Carbon::setTestNow());

function sekarangWib(string $waktu): void
{
    Carbon::setTestNow(Carbon::parse($waktu, 'Asia/Jakarta'));
}

function tagihanNovember(string $kamar, StatusTagihan $status, int $nominal = 1250000): Tagihan
{
    return tagihanUji(buatKontrakUji($kamar, "Penghuni {$kamar}"), '2026-11-01', '2026-11-10', $status, $nominal);
}

function halamanNovember(string $periode = '2026-11', array $tambahan = [])
{
    return Livewire::withQueryParams(['periode' => $periode] + $tambahan)
        ->actingAs(User::factory()->pengelola()->create())
        ->test('pages::tagihan.index');
}

function barisUntuk($komponen, Tagihan $tagihan): array
{
    return $komponen->instance()->semuaBaris->firstWhere('id', $tagihan->id);
}

it('F-15 butir 1: belum dibayar pada 11 November tampil Telat dengan "10 Nov, Lewat 1 Hari"', function () {
    sekarangWib('2026-11-11 00:05');
    $tagihan = tagihanNovember('01', StatusTagihan::BelumDibayar);

    $komponen = halamanNovember();

    expect(barisUntuk($komponen, $tagihan))->toMatchArray(['telat' => true, 'hariLewat' => 1])
        ->and($komponen->instance()->hitungan['telat'])->toBe(1);

    $komponen->assertSee('10 Nov, Lewat 1 Hari');
});

it('F-15 butir 1: pada hari jatuh tempo (10 November) belum bukan tunggakan', function () {
    sekarangWib('2026-11-10 12:00');
    $tagihan = tagihanNovember('01', StatusTagihan::BelumDibayar);

    $komponen = halamanNovember();

    expect(barisUntuk($komponen, $tagihan)['telat'])->toBeFalse()
        ->and($komponen->instance()->hitungan['telat'])->toBe(0);

    $komponen->assertDontSee(', Lewat');
});

it('F-15 butir 2: bukti menunggu yang diunggah 10 November tidak ditandai telat', function () {
    sekarangWib('2026-11-15 12:00');
    $tagihan = tagihanNovember('01', StatusTagihan::MenungguVerifikasi);
    Pembayaran::factory()->menunggu()->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-11-10 23:00', 'Asia/Jakarta'),
    ]);

    $komponen = halamanNovember();

    expect(barisUntuk($komponen, $tagihan))->toMatchArray(['telat' => false, 'hariLewat' => 0])
        ->and($komponen->instance()->ringkasan['jumlahTunggakan'])->toBe(0);
});

it('F-15 butir 3: bukti menunggu yang diunggah 12 November tetap telat sampai diterima', function () {
    sekarangWib('2026-11-15 12:00');
    $tagihan = tagihanNovember('01', StatusTagihan::MenungguVerifikasi);
    Pembayaran::factory()->menunggu()->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-11-12 08:00', 'Asia/Jakarta'),
    ]);

    $komponen = halamanNovember();

    expect(barisUntuk($komponen, $tagihan))->toMatchArray(['telat' => true, 'hariLewat' => 5]);

    // Telat tetap dihitung di chip status asalnya (Menunggu dicek).
    expect($komponen->instance()->hitungan)->toMatchArray(['telat' => 1, 'menunggu' => 1]);
});

it('F-15 butir 4: bukti tepat waktu yang ditolak dicek 15 November membuat tagihan telat 5 hari', function () {
    sekarangWib('2026-11-15 12:00');
    $tagihan = tagihanNovember('01', StatusTagihan::BelumDibayar);
    Pembayaran::factory()->ditolak()->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-11-09 08:00', 'Asia/Jakarta'),
        'diverifikasi_pada' => Carbon::parse('2026-11-15 10:00', 'Asia/Jakarta'),
    ]);

    $komponen = halamanNovember();

    expect(barisUntuk($komponen, $tagihan))->toMatchArray(['telat' => true, 'hariLewat' => 5]);

    $komponen->assertSee('10 Nov, Lewat 5 Hari')->assertSee('Ditolak, 15 Nov');
});

it('F-15 butir 6: tagihan lunas tidak ditandai telat', function () {
    sekarangWib('2026-11-20 12:00');
    $tagihan = tagihanNovember('01', StatusTagihan::Lunas);
    Pembayaran::factory()->diterima()->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-11-14 08:00', 'Asia/Jakarta'),
    ]);

    $komponen = halamanNovember();

    expect(barisUntuk($komponen, $tagihan))->toMatchArray(['telat' => false, 'hariLewat' => 0])
        ->and($komponen->instance()->ringkasan['tunggakan'])->toBe(0);
});

it('F-15: tunggakan Oktober tampil di grup "Telat Sebelumnya" saat melihat November', function () {
    sekarangWib('2026-11-12 09:00');
    $oktober = tagihanUji(buatKontrakUji('01', 'Penghuni 01'), '2026-10-01', '2026-10-10', StatusTagihan::BelumDibayar);
    $november = tagihanNovember('02', StatusTagihan::BelumDibayar);

    $komponen = halamanNovember();

    expect($komponen->instance()->grupLama->pluck('id')->all())->toBe([$oktober->id])
        ->and($komponen->instance()->grupPeriode->pluck('id')->all())->toBe([$november->id]);

    $komponen->assertSeeInOrder(['Telat Sebelumnya', 'Penghuni 01', 'Oktober', '10 Okt, Lewat 33 Hari', 'November', 'Penghuni 02']);
});

it('F-15 butir 5 dan 7: jumlah dan total nominal tunggakan sama dengan penjumlahan manual, dari semua periode', function () {
    sekarangWib('2026-11-20 09:00');

    // Telat: November 01 (1.250.000), 02 (1.250.000), 03 menunggu diunggah 20 Nov (1.400.000);
    //        Oktober 06 (1.500.000); September 07 (1.300.000)  => 5 tagihan, Rp6.700.000.
    // Bukan telat: November 04 menunggu diunggah 9 Nov, 05 lunas, 08 yang jatuh temponya belum lewat.
    tagihanNovember('01', StatusTagihan::BelumDibayar);
    tagihanNovember('02', StatusTagihan::BelumDibayar);
    $menungguTelat = tagihanNovember('03', StatusTagihan::MenungguVerifikasi, 1400000);
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $menungguTelat->id, 'diunggah_pada' => Carbon::parse('2026-11-20 08:00', 'Asia/Jakarta')]);

    $menungguTepat = tagihanNovember('04', StatusTagihan::MenungguVerifikasi, 1400000);
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $menungguTepat->id, 'diunggah_pada' => Carbon::parse('2026-11-09 08:00', 'Asia/Jakarta')]);

    $lunas = tagihanNovember('05', StatusTagihan::Lunas);
    Pembayaran::factory()->diterima()->create(['tagihan_id' => $lunas->id]);

    tagihanUji(buatKontrakUji('08', 'Penghuni 08'), '2026-11-01', '2026-11-25', StatusTagihan::BelumDibayar, 1250000);
    tagihanUji(buatKontrakUji('06', 'Penghuni 06'), '2026-10-01', '2026-10-10', StatusTagihan::BelumDibayar, 1500000);
    tagihanUji(buatKontrakUji('07', 'Penghuni 07'), '2026-09-01', '2026-09-10', StatusTagihan::BelumDibayar, 1300000);

    foreach (['2026-11', '2026-10', '2026-09'] as $periode) {
        $ringkasan = halamanNovember($periode)->instance()->ringkasan;

        expect($ringkasan['jumlahTunggakan'])->toBe(5)
            ->and($ringkasan['tunggakan'])->toBe(6700000);
    }

    $komponen = halamanNovember();
    $komponen->assertSee('Rp6.700.000')->assertDontSee('semua periode');
    expect($komponen->html())->toMatch('#data-test="ringkasan-tunggakan-keterangan"[^>]*>5 tagihan</div>#');

    // Daftar November: dua tunggakan lama + tujuh tagihan November; chip Telat = 5.
    expect($komponen->instance()->hitungan['telat'])->toBe(5)
        ->and($komponen->instance()->grupLama)->toHaveCount(2)
        ->and($komponen->instance()->grupPeriode)->toHaveCount(6);

    // Melihat Oktober: tunggakan September ikut, tunggakan November tidak masuk daftar tetapi tetap di ringkasan.
    $oktober = halamanNovember('2026-10');
    expect($oktober->instance()->grupLama->pluck('kamar')->all())->toBe(['07'])
        ->and($oktober->instance()->grupPeriode->pluck('kamar')->all())->toBe(['06']);
});

it('penanda tunggakan hilang setelah tagihan lunas', function () {
    sekarangWib('2026-11-12 09:00');
    $tagihan = tagihanNovember('01', StatusTagihan::BelumDibayar);

    expect(barisUntuk(halamanNovember(), $tagihan)['telat'])->toBeTrue();

    $tagihan->forceFill(['status_tagihan' => StatusTagihan::Lunas])->save();

    expect(barisUntuk(halamanNovember(), $tagihan)['telat'])->toBeFalse();
});
