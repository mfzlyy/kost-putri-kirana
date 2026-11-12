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

it('normalisasiTelepon membuang pemisah dan mengganti awalan +62 atau 62 menjadi 0', function () {
    expect(Format::normalisasiTelepon('+62 812-3456-7801'))->toBe('081234567801')
        ->and(Format::normalisasiTelepon('0812 3456 7801'))->toBe('081234567801')
        ->and(Format::normalisasiTelepon('62812.3456.7801'))->toBe('081234567801')
        ->and(Format::normalisasiTelepon('(0812) 3456-7801'))->toBe('081234567801');
});

it('telepon dikelompokkan empat angka dari depan, kosong menjadi tanda hubung', function () {
    expect(Format::telepon('081234567801'))->toBe('0812 3456 7801')
        ->and(Format::telepon('0812345678012'))->toBe('0812 3456 7801 2')
        ->and(Format::telepon(null))->toBe('-')
        ->and(Format::telepon(''))->toBe('-');
});

it('ukuranBerkas menampilkan MB satu desimal dan KB bulat', function () {
    expect(Format::ukuranBerkas(1887436))->toBe('1,8 MB')
        ->and(Format::ukuranBerkas(5242880))->toBe('5,0 MB')
        ->and(Format::ukuranBerkas(320000))->toBe('313 KB')
        ->and(Format::ukuranBerkas(100))->toBe('1 KB');
});
