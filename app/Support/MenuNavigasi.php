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
                self::item('Riwayat transaksi', 'riwayat-transaksi.index', 'clipboard-document-list'),
                self::item('Tiket keluhan', 'tiket-keluhan.index', 'wrench-screwdriver'),
                self::item('Pengaturan', 'pengaturan.index', 'cog-6-tooth'),
            ],
            PeranPengguna::Pengelola => [
                self::item('Verifikasi pembayaran', 'verifikasi-pembayaran.index', 'check-badge'),
                self::item('Tagihan', 'tagihan.index', 'banknotes'),
                self::item('Kamar', 'kamar.index', 'home-modern'),
                self::item('Penghuni', 'penghuni.index', 'users'),
                self::item('Kontrak', 'kontrak.index', 'document-text'),
                self::item('Riwayat transaksi', 'riwayat-transaksi.index', 'clipboard-document-list'),
                self::item('Tiket keluhan', 'tiket-keluhan.index', 'wrench-screwdriver'),
                self::item('Sinkronisasi ERP', 'sinkronisasi-erp.index', 'arrow-path'),
                self::item('Akun pengguna', 'akun.index', 'user-circle'),
                self::item('Pengaturan', 'pengaturan.index', 'cog-6-tooth'),
            ],
            PeranPengguna::Penghuni => [
                self::item('Tagihan saya', 'tagihan-saya.index', 'banknotes'),
                self::item('Riwayat transaksi', 'riwayat-transaksi.index', 'clipboard-document-list'),
                self::item('Keluhan saya', 'keluhan-saya.index', 'wrench-screwdriver'),
                self::item('Profil saya', 'profil-saya.index', 'identification'),
            ],
            PeranPengguna::Vendor => [
                self::item('Permintaan kerja', 'permintaan-kerja.index', 'wrench-screwdriver'),
            ],
        };
    }

    /**
     * Tab bawah layar ponsel untuk penghuni: rute yang sama dengan menu penghuni, dengan label pendek dan ikon sendiri.
     *
     * @return list<array{label: string, route: string, ikon: string}>
     */
    public static function tabPenghuni(): array
    {
        $label = [
            'tagihan-saya.index' => ['Tagihan', 'document-text'],
            'riwayat-transaksi.index' => ['Riwayat', 'clock'],
            'keluhan-saya.index' => ['Keluhan', 'wrench-screwdriver'],
            'profil-saya.index' => ['Profil', 'user-circle'],
        ];

        return array_map(
            fn (array $item) => self::item($label[$item['route']][0], $item['route'], $label[$item['route']][1]),
            self::untuk(PeranPengguna::Penghuni),
        );
    }

    /**
     * @return array{label: string, route: string, ikon: string}
     */
    private static function item(string $label, string $route, string $ikon): array
    {
        return ['label' => $label, 'route' => $route, 'ikon' => $ikon];
    }
}
