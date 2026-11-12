<?php

namespace App\Support\Migrasi;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusKontrak;
use App\Enums\StatusPenghuni;
use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Memuat baris valid ke basis data (F-09 butir 1). Pemanggil yang membuka dan menutup transaksi, supaya gagal di
 * tengah berarti tidak ada yang tersimpan. Ini pemuatan data historis, jadi status ditulis langsung dengan forceFill
 * di kelas ini dan bukan lewat method model (terima, akhiri, dan lain-lain). Tidak ada baris sinkronisasi_erp:
 * riwayat lama tidak dikirim ke Odoo.
 */
final class PemuatMigrasi
{
    /** Jalur penanda bukti bayar; halaman bukti menampilkan "Bukti tidak tersedia (data migrasi)." untuk nilai ini. */
    public const BUKTI_TANPA_BERKAS = 'migrasi/tanpa-bukti';

    public const DOMAIN_EMAIL = 'migrasi.kirana.test';

    public function muat(HasilValidasi $hasil, User $pengelola): void
    {
        $idKamar = [];
        foreach ($hasil->kamar as $kode => $baris) {
            $idKamar[$kode] = Kamar::query()->create([
                'nomor_kamar' => $baris['nomor_kamar'],
                'tarif_bulanan' => $baris['tarif_bulanan'],
                'keterangan' => $baris['keterangan'],
            ])->id;
        }

        $idPenghuni = [];
        foreach ($hasil->penghuni as $kode => $baris) {
            $aktif = $baris['status'] === 'aktif';

            $user = User::query()->create([
                'nama' => $baris['nama'],
                'email' => 'penghuni.'.mb_strtolower($kode).'@'.self::DOMAIN_EMAIL,
                'password_hash' => Hash::make(Str::random(40)),
                'peran' => PeranPengguna::Penghuni,
                'status_akun' => $aktif ? StatusAkun::Aktif : StatusAkun::Nonaktif,
            ]);

            $penghuni = new Penghuni;
            $penghuni->forceFill([
                'user_id' => $user->id,
                'no_identitas_enc' => $baris['no_identitas'],
                'no_telepon' => $baris['no_telepon'],
                'status_penghuni' => $aktif ? StatusPenghuni::Aktif : StatusPenghuni::Nonaktif,
            ])->save();

            $idPenghuni[$kode] = $penghuni->id;
        }

        $idKontrak = [];
        foreach ($hasil->kontrak as $kode => $baris) {
            $kontrak = new Kontrak;
            $kontrak->forceFill([
                'penghuni_id' => $idPenghuni[$baris['kode_penghuni']],
                'kamar_id' => $idKamar[$baris['nomor_kamar']],
                'tanggal_mulai' => $baris['tanggal_mulai']->toDateString(),
                'tanggal_berakhir' => $baris['tanggal_berakhir']->toDateString(),
                'tarif_bulanan' => $baris['tarif_bulanan'],
                'status_kontrak' => StatusKontrak::from($baris['status']),
            ])->save();

            $idKontrak[$kode] = $kontrak->id;
        }

        foreach ($hasil->pembayaran as $baris) {
            $lunas = $baris['tanggal_bayar'] !== null;

            $tagihan = new Tagihan;
            $tagihan->forceFill([
                'kontrak_id' => $idKontrak[$baris['kode_kontrak']],
                'periode' => $baris['periode']->toDateString(),
                'nominal' => $baris['nominal'],
                'jatuh_tempo' => $baris['jatuh_tempo']->toDateString(),
                'status_tagihan' => $lunas ? StatusTagihan::Lunas : StatusTagihan::BelumDibayar,
            ])->save();

            if (! $lunas) {
                continue;
            }

            $diunggahPada = CarbonImmutable::parse($baris['tanggal_bayar']->toDateString().' 12:00:00', 'Asia/Jakarta');

            $pembayaran = new Pembayaran;
            $pembayaran->forceFill([
                'tagihan_id' => $tagihan->id,
                'tanggal_bayar' => $baris['tanggal_bayar']->toDateString(),
                'nominal_bayar' => $baris['nominal'],
                'bukti_bayar_path' => self::BUKTI_TANPA_BERKAS,
                'diunggah_pada' => $diunggahPada,
                'status_verifikasi' => StatusVerifikasi::Diterima,
                'diverifikasi_oleh' => $pengelola->id,
                'diverifikasi_pada' => $diunggahPada,
            ])->save();
        }
    }
}
