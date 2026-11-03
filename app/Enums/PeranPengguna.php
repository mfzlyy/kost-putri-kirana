<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case Pemilik = 'pemilik';
    case Pengelola = 'pengelola';
    case Penghuni = 'penghuni';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Pemilik => 'Pemilik',
            self::Pengelola => 'Pengelola',
            self::Penghuni => 'Penghuni',
            self::Vendor => 'Vendor',
        };
    }

    /**
     * Nama route halaman utama setelah login (PRD Bagian 6).
     */
    public function halamanUtama(): string
    {
        return match ($this) {
            self::Pemilik => 'dashboard',
            self::Pengelola => 'verifikasi-pembayaran.index',
            self::Penghuni => 'tagihan-saya.index',
            self::Vendor => 'permintaan-kerja.index',
        };
    }
}
