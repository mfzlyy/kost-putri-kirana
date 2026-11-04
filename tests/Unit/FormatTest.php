<?php

use App\Support\Format;

it('rupiah memformat nominal tanpa spasi dan tanpa desimal', function () {
    expect(Format::rupiah(1400000))->toBe('Rp1.400.000')
        ->and(Format::rupiah('1250000.00'))->toBe('Rp1.250.000')
        ->and(Format::rupiah(0))->toBe('Rp0')
        ->and(Format::rupiah(1500000.0))->toBe('Rp1.500.000');
});

it('rupiah mengembalikan tanda hubung untuk nilai kosong atau bukan angka', function () {
    expect(Format::rupiah(null))->toBe('-')
        ->and(Format::rupiah(''))->toBe('-')
        ->and(Format::rupiah('abc'))->toBe('-');
});

it('parseRupiah membaca berbagai isian menjadi bilangan bulat', function () {
    expect(Format::parseRupiah('1.400.000'))->toBe(1400000)
        ->and(Format::parseRupiah('Rp 1.400.000'))->toBe(1400000)
        ->and(Format::parseRupiah('1400000'))->toBe(1400000);
});

it('parseRupiah mengembalikan null untuk teks tanpa angka', function () {
    expect(Format::parseRupiah('abc'))->toBeNull()
        ->and(Format::parseRupiah(''))->toBeNull()
        ->and(Format::parseRupiah('Rp'))->toBeNull();
});
