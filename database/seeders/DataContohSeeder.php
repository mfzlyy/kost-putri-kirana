<?php

namespace Database\Seeders;

use App\Enums\LokasiKeluhan;
use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusTagihan;
use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\Tagihan;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data demo fiktif untuk uji manual; hanya berjalan di APP_ENV=local.
 *
 * [ASUMSI] Daftar fasilitas acuan resmi dari pemilik (DEP-P01) belum ada, jadi fasilitas
 * contoh di sini akan diganti setelah daftar itu diterima. Semua nama, nomor, dan tarif fiktif.
 * Akun penghuni dan vendor contoh memakai password acak, sehingga belum dapat dipakai login
 * sampai pengelola mengatur ulang passwordnya lewat F-02.
 */
class DataContohSeeder extends Seeder
{
    private const TARIF_KAMAR = [
        '01' => 1200000,
        '02' => 1250000,
        '03' => 1300000,
        '04' => 1350000,
        '05' => 1400000,
        '06' => 1500000,
        '07' => 1450000,
        '08' => 1500000,
    ];

    private const TANGGAL_JATUH_TEMPO = 10;

    private const BATAS_PERINGATAN_HARI = 30;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DataContohSeeder dilewati: hanya berjalan saat APP_ENV=local.');

            return;
        }

        if (Kamar::query()->exists()) {
            $this->command?->info('DataContohSeeder dilewati: data kamar sudah ada.');

            return;
        }

        $pengelola = User::query()->where('peran', PeranPengguna::Pengelola->value)->orderBy('id')->first()
            ?? throw new RuntimeException('Akun pengelola belum ada. Jalankan AkunAwalSeeder terlebih dahulu.');

        DB::transaction(function () use ($pengelola) {
            PengaturanOperasional::aktif()->update([
                'tanggal_jatuh_tempo' => self::TANGGAL_JATUH_TEMPO,
                'batas_peringatan_kontrak_hari' => self::BATAS_PERINGATAN_HARI,
                'diperbarui_oleh' => $pengelola->id,
            ]);

            $kamar = $this->buatKamar();
            $fasilitas = $this->buatFasilitas($kamar);
            $penghuni = $this->buatPenghuniDanKontrak($kamar);
            $vendor = $this->buatVendor();

            $this->buatTagihan($penghuni, $pengelola);
            $this->buatTiket($penghuni, $fasilitas, $vendor, $pengelola);
        });
    }

    /**
     * @return array<string, Kamar>
     */
    private function buatKamar(): array
    {
        $kamar = [];

        foreach (self::TARIF_KAMAR as $nomor => $tarif) {
            $kamar[$nomor] = Kamar::factory()->create([
                'nomor_kamar' => $nomor,
                'tarif_bulanan' => $tarif,
            ]);
        }

        return $kamar;
    }

    /**
     * @param  array<string, Kamar>  $kamar
     * @return array<string, Fasilitas>
     */
    private function buatFasilitas(array $kamar): array
    {
        $fasilitas = [];

        foreach ($kamar as $nomor => $unit) {
            foreach (['AC', 'Kasur'] as $nama) {
                $fasilitas["{$nama} {$nomor}"] = Fasilitas::factory()->create([
                    'nama_fasilitas' => $nama,
                    'kamar_id' => $unit->id,
                    'lokasi' => "Kamar {$nomor}",
                ]);
            }
        }

        foreach (['Mesin cuci', 'Dapur bersama', 'Pompa air'] as $nama) {
            $fasilitas[$nama] = Fasilitas::factory()->areaBersama()->create([
                'nama_fasilitas' => $nama,
            ]);
        }

        return $fasilitas;
    }

    /**
     * Enam penghuni aktif di kamar 01 s.d. 06 (kontrak kamar 02 berakhir 20 hari lagi untuk F-08),
     * serta satu penghuni lama di kamar 07 dengan kontrak berakhir sebagai riwayat.
     *
     * @param  array<string, Kamar>  $kamar
     * @return array<string, Penghuni> penghuni aktif menurut nomor kamar
     */
    private function buatPenghuniDanKontrak(array $kamar): array
    {
        $penghuni = [];
        $bulanMulai = ['01' => 6, '02' => 4, '03' => 5, '04' => 3, '05' => 2, '06' => 4];

        foreach ($bulanMulai as $nomor => $bulanLalu) {
            $penghuni[$nomor] = $this->buatPenghuni();

            // Kontrak berdurasi satu tahun; kamar 02 dibuat berakhir 20 hari lagi (F-08).
            $berakhir = $nomor === '02'
                ? CarbonImmutable::now()->addDays(20)
                : CarbonImmutable::now()->subMonths($bulanLalu)->startOfMonth()->addYear()->subDay();
            $mulai = $berakhir->subYear()->addDay();

            Kontrak::factory()->aktif()->create([
                'penghuni_id' => $penghuni[$nomor]->id,
                'kamar_id' => $kamar[$nomor]->id,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_berakhir' => $berakhir->toDateString(),
                'tarif_bulanan' => $kamar[$nomor]->tarif_bulanan,
            ]);
        }

        $lama = $this->buatPenghuni(aktif: false);

        Kontrak::factory()->berakhir()->create([
            'penghuni_id' => $lama->id,
            'kamar_id' => $kamar['07']->id,
            'tanggal_mulai' => now()->subMonths(15)->startOfMonth()->toDateString(),
            'tanggal_berakhir' => now()->subMonths(3)->endOfMonth()->toDateString(),
            'tarif_bulanan' => $kamar['07']->tarif_bulanan,
        ]);

        return $penghuni;
    }

    private function buatPenghuni(bool $aktif = true): Penghuni
    {
        $user = User::factory()->penghuni()->create([
            'password_hash' => Str::random(40),
            'status_akun' => $aktif ? StatusAkun::Aktif : StatusAkun::Nonaktif,
        ]);

        $factory = $aktif ? Penghuni::factory() : Penghuni::factory()->nonaktif();

        return $factory->create(['user_id' => $user->id]);
    }

    /**
     * @return array{listrik_air: Vendor, perabot: Vendor}
     */
    private function buatVendor(): array
    {
        $buat = function (string $nama, string $layanan): Vendor {
            $user = User::factory()->vendor()->create([
                'nama' => $nama,
                'password_hash' => Str::random(40),
            ]);

            return Vendor::factory()->create([
                'user_id' => $user->id,
                'nama_vendor' => $nama,
                'jenis_layanan' => $layanan,
            ]);
        };

        return [
            'listrik_air' => $buat('CV Teknik Aliran Jaya', 'Listrik dan air'),
            'perabot' => $buat('UD Perabot Sentosa', 'Perabot'),
        ];
    }

    /**
     * Enam tagihan untuk enam kontrak aktif:
     *  - kamar 01, 02, 03: lunas, pembayaran diterima;
     *  - kamar 04: menunggu verifikasi, bukti diunggah sebelum jatuh tempo (bukan tunggakan);
     *  - kamar 05: belum dibayar dan sudah lewat jatuh tempo (satu-satunya tunggakan);
     *  - kamar 06: belum dibayar, bukti ditolak. Dibuat untuk periode bulan ini dengan jatuh tempo
     *    belum lewat; bila sudah lewat, aturan ATB-05 menjadikannya tunggakan kedua.
     *
     * @param  array<string, Penghuni>  $penghuni
     */
    private function buatTagihan(array $penghuni, User $pengelola): void
    {
        $periodeLalu = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $jatuhTempoLalu = $periodeLalu->day(self::TANGGAL_JATUH_TEMPO);

        foreach (['01', '02', '03'] as $indeks => $nomor) {
            $tagihan = $this->buatSatuTagihan($penghuni[$nomor], $periodeLalu, $jatuhTempoLalu, StatusTagihan::Lunas);
            $hariBayar = $periodeLalu->addDays(3 + $indeks)->setTime(10, 0);

            Pembayaran::factory()->diterima()->create([
                'tagihan_id' => $tagihan->id,
                'tanggal_bayar' => $hariBayar->toDateString(),
                'nominal_bayar' => $tagihan->nominal,
                'diunggah_pada' => $hariBayar,
                'diverifikasi_oleh' => $pengelola->id,
                'diverifikasi_pada' => $hariBayar->addDay()->setTime(9, 0),
            ]);
        }

        $menunggu = $this->buatSatuTagihan($penghuni['04'], $periodeLalu, $jatuhTempoLalu, StatusTagihan::MenungguVerifikasi);
        $diunggah = $periodeLalu->addDays(7)->setTime(20, 0);

        Pembayaran::factory()->menunggu()->create([
            'tagihan_id' => $menunggu->id,
            'tanggal_bayar' => $diunggah->toDateString(),
            'nominal_bayar' => $menunggu->nominal,
            'diunggah_pada' => $diunggah,
        ]);

        $this->buatSatuTagihan($penghuni['05'], $periodeLalu, $jatuhTempoLalu, StatusTagihan::BelumDibayar);

        $periodeIni = CarbonImmutable::now()->startOfMonth();
        $ditolak = $this->buatSatuTagihan(
            $penghuni['06'],
            $periodeIni,
            CarbonImmutable::now()->addDays(3)->startOfDay(),
            StatusTagihan::BelumDibayar,
        );
        $diunggahKemarin = CarbonImmutable::now()->subDay()->setTime(14, 0);

        Pembayaran::factory()->ditolak('Nominal pada bukti tidak sesuai dengan tagihan.')->create([
            'tagihan_id' => $ditolak->id,
            'tanggal_bayar' => $diunggahKemarin->toDateString(),
            'nominal_bayar' => $ditolak->nominal,
            'diunggah_pada' => $diunggahKemarin,
            'diverifikasi_oleh' => $pengelola->id,
            'diverifikasi_pada' => $diunggahKemarin->addHours(2),
        ]);
    }

    private function buatSatuTagihan(
        Penghuni $penghuni,
        CarbonImmutable $periode,
        CarbonImmutable $jatuhTempo,
        StatusTagihan $status,
    ): Tagihan {
        $kontrak = $penghuni->kontrakAktif()->firstOrFail();

        return Tagihan::factory()->create([
            'kontrak_id' => $kontrak->id,
            'periode' => $periode->toDateString(),
            'nominal' => $kontrak->tarif_bulanan,
            'jatuh_tempo' => $jatuhTempo->toDateString(),
            'status_tagihan' => $status,
        ]);
    }

    /**
     * Dua tiket: satu baru diajukan, satu selesai dengan riwayat empat langkah.
     *
     * @param  array<string, Penghuni>  $penghuni
     * @param  array<string, Fasilitas>  $fasilitas
     * @param  array{listrik_air: Vendor, perabot: Vendor}  $vendor
     */
    private function buatTiket(array $penghuni, array $fasilitas, array $vendor, User $pengelola): void
    {
        $lapor = CarbonImmutable::now()->subDays(14)->setTime(9, 15);
        $pelapor = $penghuni['01'];
        $vendorUser = $vendor['listrik_air']->user;

        $selesai = TiketKeluhan::factory()->selesai()->create([
            'nomor_tiket' => TiketKeluhan::nomorBerikutnya(),
            'penghuni_id' => $pelapor->id,
            'fasilitas_id' => $fasilitas['Pompa air']->id,
            'vendor_id' => $vendor['listrik_air']->id,
            'lokasi' => LokasiKeluhan::AreaBersama,
            'deskripsi' => 'Pompa air berbunyi keras dan air tidak naik ke kamar mandi.',
            'jenis_kerusakan' => 'Tidak berfungsi',
            'catatan_instruksi' => 'Periksa kapasitor dan selang hisap.',
            'biaya_perbaikan' => 350000,
            'tanggal_lapor' => $lapor,
        ]);

        $langkah = [
            [null, StatusTiket::Diajukan, $pelapor->user_id, $lapor],
            [StatusTiket::Diajukan, StatusTiket::Ditugaskan, $pengelola->id, $lapor->addDay()],
            [StatusTiket::Ditugaskan, StatusTiket::Dikerjakan, $vendorUser->id, $lapor->addDays(2)],
            [StatusTiket::Dikerjakan, StatusTiket::Selesai, $vendorUser->id, $lapor->addDays(4)],
        ];

        foreach ($langkah as [$sebelum, $sesudah, $pengubah, $waktu]) {
            RiwayatStatusTiket::factory()->perubahan($sebelum, $sesudah)->create([
                'tiket_id' => $selesai->id,
                'diubah_oleh' => $pengubah,
                'waktu_perubahan' => $waktu,
            ]);
        }

        $laporBaru = CarbonImmutable::now()->subDay()->setTime(19, 30);
        $pelaporBaru = $penghuni['02'];

        $diajukan = TiketKeluhan::factory()->create([
            'nomor_tiket' => TiketKeluhan::nomorBerikutnya(),
            'penghuni_id' => $pelaporBaru->id,
            'fasilitas_id' => $fasilitas['AC 02']->id,
            'lokasi' => LokasiKeluhan::Kamar,
            'deskripsi' => 'AC menyala tetapi tidak dingin dan menetes air.',
            'jenis_kerusakan' => 'Tidak berfungsi',
            'tanggal_lapor' => $laporBaru,
        ]);

        RiwayatStatusTiket::factory()->perubahan(null, StatusTiket::Diajukan)->create([
            'tiket_id' => $diajukan->id,
            'diubah_oleh' => $pelaporBaru->user_id,
            'waktu_perubahan' => $laporBaru,
        ]);
    }
}
