<?php

namespace App\Support;

use App\Enums\PeranPengguna;

/**
 * Satu-satunya sumber menu sidebar per peran (F-03 butir 1).
 * Menu hanya alat bantu tampilan; akses ditegakkan oleh middleware peran dan Policy.
 */
class MenuNavigasi
{
    /**
     * @return list<array{label: string, route: string, ikon: string}>
     */
    public static function untuk(PeranPengguna $peran): array
    {
        return match ($peran) {
            PeranPengguna::Pemilik => [
                self::item('Dashboard', 'dashboard', 'chart-bar'),
                self::item('Kamar', 'kamar.index', 'home-modern'),
                self::item('Penghuni', 'penghuni.index', 'users'),
                self::item('Kontrak', 'kontrak.index', 'document-text'),
                self::item('Tagihan', 'tagihan.index', 'banknotes'),
                self::item('Riwayat Transaksi', 'riwayat-transaksi.index', 'clipboard-document-list'),
                self::item('Tiket Keluhan', 'tiket-keluhan.index', 'wrench-screwdriver'),
                self::item('Pengaturan', 'pengaturan.index', 'cog-6-tooth'),
            ],
            PeranPengguna::Pengelola => [
                self::item('Verifikasi Pembayaran', 'verifikasi-pembayaran.index', 'check-badge'),
                self::item('Tagihan', 'tagihan.index', 'banknotes'),
                self::item('Kamar', 'kamar.index', 'home-modern'),
                self::item('Penghuni', 'penghuni.index', 'users'),
                self::item('Kontrak', 'kontrak.index', 'document-text'),
                self::item('Riwayat Transaksi', 'riwayat-transaksi.index', 'clipboard-document-list'),
                self::item('Tiket Keluhan', 'tiket-keluhan.index', 'wrench-screwdriver'),
                self::item('Sinkronisasi ERP', 'sinkronisasi-erp.index', 'arrow-path'),
                self::item('Akun Pengguna', 'akun.index', 'user-circle'),
                self::item('Pengaturan', 'pengaturan.index', 'cog-6-tooth'),
            ],
            PeranPengguna::Penghuni => [
                self::item('Tagihan Saya', 'tagihan-saya.index', 'banknotes'),
                self::item('Riwayat Transaksi', 'riwayat-transaksi.index', 'clipboard-document-list'),
                self::item('Keluhan Saya', 'keluhan-saya.index', 'wrench-screwdriver'),
                self::item('Profil Saya', 'profil-saya.index', 'identification'),
            ],
            PeranPengguna::Vendor => [
                self::item('Permintaan Kerja', 'permintaan-kerja.index', 'wrench-screwdriver'),
            ],
        };
    }

    /**
     * @return array{label: string, route: string, ikon: string}
     */
    private static function item(string $label, string $route, string $ikon): array
    {
        return ['label' => $label, 'route' => $route, 'ikon' => $ikon];
    }
}
