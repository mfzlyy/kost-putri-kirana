<?php

namespace App\Services\Odoo;

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTiket;
use App\Enums\StatusVerifikasi;
use App\Exceptions\PengirimanErpGagal;
use App\Models\Pembayaran;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\TiketKeluhan;
use App\Models\Vendor;
use App\Support\Format;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mengirim satu baris sinkronisasi_erp ke Odoo. Semua panggilan lewat OdooClient.
 *
 * Setiap panggilan JSON-2 adalah transaksi Odoo tersendiri, jadi pengiriman bisa berhenti di tengah.
 * Karena itu setiap langkah membaca keadaan di Odoo lebih dulu dan melanjutkan dari sana, tanpa membuat
 * catatan ganda. Yang diubah di web hanya baris sinkronisasi_erp dan vendor.odoo_partner_id; pembayaran,
 * tagihan, dan tiket tidak disentuh.
 */
class IntegrasiErpService
{
    public const PESAN_TIDAK_TERHUBUNG = 'Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala.';

    public const PESAN_API_KEY_DITOLAK = 'API key Odoo ditolak. Periksa ODOO_API_KEY dan masa berlakunya.';

    public const PESAN_TIKET_BELUM_SELESAI = 'Tiket belum berstatus selesai.';

    public const PESAN_TANPA_VENDOR = 'Tiket tidak punya vendor.';

    public const PESAN_BELUM_DITAUTKAN = 'Fasilitas belum ditautkan ke peralatan Odoo.';

    public const PESAN_TANPA_TAHAP_SELESAI = 'Tahap perawatan selesai tidak ditemukan di Odoo.';

    /**
     * Event (dengan ID baris) saat baris dilewati karena proses lain sedang mengirimnya atau sedang membuat
     * partner yang sama.
     */
    public const PERISTIWA_DILEWATI = 'sinkronisasi-erp.dilewati';

    private const BATAS_PESAN = 500;

    /**
     * Umur kunci per baris, lebih lama dari satu pengiriman terburuk (10 panggilan, masing-masing 30 detik).
     * Kunci dilepas segera setelah selesai; umur ini hanya berlaku bila prosesnya mati di tengah jalan.
     */
    private const DETIK_KUNCI = 330;

    /**
     * Umur kunci partner: cukup untuk satu pencarian dan satu pembuatan (masing-masing paling lama 30 detik).
     */
    private const DETIK_KUNCI_PARTNER = 60;

    /**
     * ID jurnal bank, diingat selama instance ini hidup (satu proses); sengaja tidak disimpan di cache.
     */
    private ?int $idJurnalBank = null;

    public function __construct(private readonly OdooClient $odoo) {}

    /**
     * Mengirim baris yang berstatus menunggu atau gagal. Galat Odoo dan galat bisnis dicatat di baris itu dan
     * tidak dilempar ulang; galat lain dibiarkan lolos supaya ditangani job pengirim.
     *
     * Satu baris hanya dikirim oleh satu proses pada satu waktu, dari jalur mana pun: bila kuncinya sedang
     * dipegang, tidak ada panggilan Odoo dan baris tidak diubah.
     */
    public function kirim(SinkronisasiErp $sinkronisasi): void
    {
        $kunci = Cache::lock("sinkronisasi-erp:{$sinkronisasi->getKey()}", self::DETIK_KUNCI);

        if (! $kunci->get()) {
            event(self::PERISTIWA_DILEWATI, [$sinkronisasi->getKey()]);

            return;
        }

        try {
            $this->kirimTerkunci($sinkronisasi);
        } finally {
            $kunci->release();
        }
    }

    private function kirimTerkunci(SinkronisasiErp $sinkronisasi): void
    {
        // Dibaca setelah kunci didapat: proses sebelumnya mungkin baru saja menyelesaikan baris ini.
        $baris = $sinkronisasi->fresh();

        if ($baris === null || ! in_array($baris->status_sinkronisasi, [StatusSinkronisasi::Menunggu, StatusSinkronisasi::Gagal], true)) {
            return;
        }

        try {
            $idCatatan = match ($baris->jenis_transaksi) {
                JenisTransaksiErp::Pembayaran => $this->kirimPembayaran($baris),
                JenisTransaksiErp::BiayaPerbaikan => $this->kirimBiayaPerbaikan($baris),
                JenisTransaksiErp::Pemeliharaan => $this->kirimPemeliharaan($baris),
            };
        } catch (OdooException $e) {
            $this->tandaiGagal($baris, $this->pesanDariOdoo($e));

            return;
        } catch (PengirimanErpGagal $e) {
            $this->tandaiGagal($baris, $e->getMessage());

            return;
        }

        // null: kunci partner dipegang proses lain. Baris tidak diubah dan bisa dikirim lagi nanti.
        if ($idCatatan === null) {
            event(self::PERISTIWA_DILEWATI, [$baris->getKey()]);

            return;
        }

        $this->tandaiTerkirim($baris, $idCatatan);
    }

    /**
     * F-22: pembayaran diterima menjadi faktur pelanggan yang lunas. Mengembalikan ID faktur di Odoo, atau
     * null bila dilewati karena kunci partner.
     *
     * @throws OdooException
     * @throws PengirimanErpGagal
     */
    private function kirimPembayaran(SinkronisasiErp $sinkronisasi): ?int
    {
        $sinkronisasi->load('pembayaran.tagihan.kontrak.penghuni');
        $pembayaran = $sinkronisasi->pembayaran;

        if ($pembayaran === null || $pembayaran->status_verifikasi !== StatusVerifikasi::Diterima) {
            throw new PengirimanErpGagal('Pembayaran tidak berstatus diterima.');
        }

        $tagihan = $pembayaran->tagihan;

        // Keduanya numeric(12,2) dengan cast decimal:2, jadi perbandingan teksnya tepat.
        if ($pembayaran->nominal_bayar !== $tagihan->nominal) {
            throw new PengirimanErpGagal('Nominal pembayaran berbeda dengan nominal tagihan.');
        }

        $ref = $sinkronisasi->referensi_unik;
        $idPartner = $this->partnerPenghuni($tagihan->kontrak->penghuni->id);

        if ($idPartner === null) {
            return null;
        }

        $faktur = $this->cariDokumen(
            'out_invoice',
            $ref,
            ['id', 'state', 'payment_state', 'amount_total', 'invoice_date', 'partner_id'],
            'Faktur',
        );

        if ($faktur === null) {
            $idFaktur = $this->odoo->create('account.move', [
                'move_type' => 'out_invoice',
                'partner_id' => $idPartner,
                'ref' => $ref,
                // Tanggal faktur = periode, supaya laporan bulanan mengelompokkan menurut periode tagihan.
                'invoice_date' => $tagihan->periode->toDateString(),
                'invoice_date_due' => $tagihan->jatuh_tempo->toDateString(),
                'invoice_line_ids' => $this->barisTunggal('Sewa kos periode '.$tagihan->periode->format('Y-m'), $tagihan->nominal),
            ]);
            $faktur = ['id' => $idFaktur, 'state' => 'draft', 'payment_state' => 'not_paid'];
        } else {
            $this->pastikanFakturCocok($faktur, $tagihan, $ref);
        }

        $idFaktur = (int) $faktur['id'];

        // action_post pada faktur yang sudah posted ditolak Odoo, jadi hanya untuk draft.
        if ($faktur['state'] === 'draft') {
            $this->odoo->aksi('account.move', 'action_post', [$idFaktur]);
            $faktur = $this->bacaFaktur($idFaktur);
        }

        if ($faktur['payment_state'] === 'partial') {
            throw new PengirimanErpGagal("Faktur {$ref} baru dibayar sebagian di Odoo. Periksa manual.");
        }

        if ($faktur['payment_state'] === 'not_paid') {
            $this->bayarFaktur($idFaktur, $pembayaran, $ref);
        }

        $akhir = $this->bacaFaktur($idFaktur);

        if ($akhir['state'] !== 'posted' || ! in_array($akhir['payment_state'], ['paid', 'in_payment'], true)) {
            throw new PengirimanErpGagal("Faktur {$ref} sudah dibuat tetapi belum lunas di Odoo (status {$akhir['payment_state']}).");
        }

        return $idFaktur;
    }

    /**
     * F-23: biaya tiket selesai menjadi tagihan vendor berstatus posted. Tagihan tidak dibayar lewat integrasi.
     * Biaya 0 tetap dikirim karena Odoo menerima posting tagihan bernilai 0. Deskripsi keluhan dan instruksi
     * tidak dikirim. Mengembalikan ID tagihan di Odoo, atau null bila dilewati karena kunci partner.
     *
     * @throws OdooException
     * @throws PengirimanErpGagal
     */
    private function kirimBiayaPerbaikan(SinkronisasiErp $sinkronisasi): ?int
    {
        $sinkronisasi->load('tiket.vendor', 'tiket.fasilitas.kamar', 'tiket.riwayatStatus');
        $tiket = $sinkronisasi->tiket;
        $tanggal = $this->tanggalSelesaiWajib($tiket);

        if ($tiket->biaya_perbaikan === null) {
            throw new PengirimanErpGagal(self::PESAN_TIKET_BELUM_SELESAI);
        }

        $vendor = $tiket->vendor ?? throw new PengirimanErpGagal(self::PESAN_TANPA_VENDOR);

        $ref = $sinkronisasi->referensi_unik;
        $idPartner = $this->partnerVendor($vendor);

        if ($idPartner === null) {
            return null;
        }

        $tagihan = $this->cariDokumen(
            'in_invoice',
            $ref,
            ['id', 'state', 'amount_total', 'invoice_date', 'partner_id'],
            'Tagihan vendor',
        );

        if ($tagihan === null) {
            $uraian = Str::squish("Perbaikan {$tiket->jenis_kerusakan} - {$tiket->fasilitas->namaPeralatanErp()}");

            $idTagihan = $this->odoo->create('account.move', [
                'move_type' => 'in_invoice',
                'partner_id' => $idPartner,
                'ref' => $ref,
                'invoice_date' => $tanggal,
                'invoice_line_ids' => $this->barisTunggal($uraian, $tiket->biaya_perbaikan),
            ]);
            $tagihan = ['id' => $idTagihan, 'state' => 'draft'];
        } else {
            $this->pastikanCocok("Tagihan vendor {$ref}", [
                'vendor' => $this->refPartner($tagihan['partner_id']) !== "VDR-{$vendor->id}",
                'tanggal' => $tagihan['invoice_date'] !== $tanggal,
                'nominal' => $this->nominalBeda($tagihan['amount_total'], $tiket->biaya_perbaikan),
            ]);
        }

        $idTagihan = (int) $tagihan['id'];

        if ($tagihan['state'] === 'draft') {
            $this->odoo->aksi('account.move', 'action_post', [$idTagihan]);
            $tagihan = $this->odoo->read('account.move', [$idTagihan], ['state'])[0];
        }

        if ($tagihan['state'] !== 'posted') {
            throw new PengirimanErpGagal("Tagihan vendor {$ref} sudah dibuat tetapi belum posted di Odoo.");
        }

        return $idTagihan;
    }

    /**
     * F-24: tiket selesai pada fasilitas yang tertaut menjadi permintaan perawatan di tahap selesai.
     * Mengembalikan ID permintaan di Odoo.
     *
     * @throws OdooException
     * @throws PengirimanErpGagal
     */
    private function kirimPemeliharaan(SinkronisasiErp $sinkronisasi): int
    {
        $sinkronisasi->load('tiket.vendor', 'tiket.fasilitas', 'tiket.riwayatStatus');
        $tiket = $sinkronisasi->tiket;
        $tanggal = $this->tanggalSelesaiWajib($tiket);
        $idPeralatan = $tiket->fasilitas->odoo_equipment_id ?? throw new PengirimanErpGagal(self::PESAN_BELUM_DITAUTKAN);

        $ref = $sinkronisasi->referensi_unik;
        $permintaan = $this->cariPermintaan($ref);

        if ($permintaan === null) {
            $permintaan = ['id' => $this->buatPermintaan($tiket, $ref, $idPeralatan, $tanggal), 'stage_id' => false];
        } else {
            $peralatan = $permintaan['equipment_id'];

            $this->pastikanCocok("Permintaan perawatan {$ref}", [
                'peralatan' => ! is_array($peralatan) || (int) $peralatan[0] !== $idPeralatan,
                'tanggal' => $permintaan['request_date'] !== $tanggal,
            ]);
        }

        $idPermintaan = (int) $permintaan['id'];
        $idTahap = $this->tahapSelesai();

        if ($this->diTahapSelesai($permintaan['stage_id'], $idTahap)) {
            return $idPermintaan;
        }

        $this->odoo->write('maintenance.request', [$idPermintaan], ['stage_id' => $idTahap]);
        $akhir = $this->odoo->read('maintenance.request', [$idPermintaan], ['stage_id'])[0];

        if (! $this->diTahapSelesai($akhir['stage_id'], $idTahap)) {
            throw new PengirimanErpGagal("Permintaan perawatan {$ref} sudah dibuat tetapi belum di tahap selesai di Odoo.");
        }

        return $idPermintaan;
    }

    /**
     * Tanggal selesai tiket (Y-m-d) untuk tagihan vendor dan permintaan perawatan.
     *
     * @throws PengirimanErpGagal bila tiket belum selesai atau riwayat selesainya tidak ada
     */
    private function tanggalSelesaiWajib(TiketKeluhan $tiket): string
    {
        $tanggal = $tiket->status_tiket === StatusTiket::Selesai ? $tiket->tanggalSelesai() : null;

        return $tanggal?->toDateString() ?? throw new PengirimanErpGagal(self::PESAN_TIKET_BELUM_SELESAI);
    }

    /**
     * Partner penghuni dicari lewat ref, dibuat bila belum ada. Hanya ID penghuni yang dikirim (NFR-02).
     * null bila kuncinya dipegang proses lain.
     */
    private function partnerPenghuni(int $penghuniId): ?int
    {
        return $this->denganKunciPartner("odoo-partner-penghuni:{$penghuniId}", function () use ($penghuniId) {
            $ref = "PNH-{$penghuniId}";
            $ada = $this->odoo->searchRead('res.partner', [['ref', '=', $ref]], ['id'], limit: 1);

            if ($ada !== []) {
                return (int) $ada[0]['id'];
            }

            return $this->odoo->create('res.partner', [
                'name' => "Penghuni #{$penghuniId}",
                'ref' => $ref,
                'customer_rank' => 1,
            ]);
        });
    }

    /**
     * Satu partner Odoo per vendor (ref VDR-<id>). ID yang tersimpan di web dipakai hanya bila partnernya masih
     * ada dan ref-nya sama; selain itu dicari lewat ref, dibuat bila belum ada, lalu disimpan ke
     * vendor.odoo_partner_id. Nama partner yang sudah ada tidak diubah. null bila kuncinya dipegang proses lain.
     *
     * @throws PengirimanErpGagal bila ada lebih dari satu partner dengan ref itu
     */
    private function partnerVendor(Vendor $vendor): ?int
    {
        $ref = "VDR-{$vendor->id}";
        $tersimpan = $vendor->odoo_partner_id;

        if ($tersimpan !== null && $this->refPartnerId($tersimpan) === $ref) {
            return $tersimpan;
        }

        return $this->denganKunciPartner("odoo-partner-vendor:{$vendor->id}", function () use ($vendor, $ref) {
            $ada = $this->odoo->searchRead('res.partner', [['ref', '=', $ref]], ['id'], limit: 2);

            if (count($ada) > 1) {
                throw new PengirimanErpGagal("Ada lebih dari satu partner {$ref} di Odoo. Periksa manual.");
            }

            $id = $ada !== [] ? (int) $ada[0]['id'] : $this->odoo->create('res.partner', [
                'ref' => $ref,
                'name' => $vendor->nama_vendor,
                'supplier_rank' => 1,
            ]);

            if ($vendor->odoo_partner_id !== $id) {
                $vendor->forceFill(['odoo_partner_id' => $id])->save();
            }

            return $id;
        });
    }

    /**
     * Cari-lalu-buat partner dijalankan satu proses pada satu waktu, supaya dua transaksi milik orang yang sama
     * tidak membuat partner ganda. Mengembalikan null tanpa menjalankan $kerja bila kuncinya sedang dipegang.
     *
     * @param  Closure(): int  $kerja
     */
    private function denganKunciPartner(string $nama, Closure $kerja): ?int
    {
        $kunci = Cache::lock($nama, self::DETIK_KUNCI_PARTNER);

        if (! $kunci->get()) {
            return null;
        }

        try {
            return $kerja();
        } finally {
            $kunci->release();
        }
    }

    /**
     * Faktur atau tagihan vendor dengan referensi $ref, atau null bila belum ada. $sebutan dipakai di pesan galat.
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>|null
     *
     * @throws PengirimanErpGagal bila catatannya ganda atau dibatalkan
     */
    private function cariDokumen(string $jenis, string $ref, array $fields, string $sebutan): ?array
    {
        // limit 2 sudah cukup untuk mengenali catatan ganda.
        $ada = $this->odoo->searchRead(
            'account.move',
            [['move_type', '=', $jenis], ['ref', '=', $ref]],
            $fields,
            limit: 2,
        );

        if (count($ada) > 1) {
            throw new PengirimanErpGagal('Ada lebih dari satu '.Str::lower($sebutan)." {$ref} di Odoo. Periksa manual.");
        }

        $dokumen = $ada[0] ?? null;

        if ($dokumen !== null && $dokumen['state'] === 'cancel') {
            throw new PengirimanErpGagal("{$sebutan} {$ref} dibatalkan di Odoo. Periksa manual.");
        }

        return $dokumen;
    }

    /**
     * @param  array<string, mixed>  $faktur  hasil search_read, termasuk invoice_date dan partner_id
     *
     * @throws PengirimanErpGagal bila penghuni, periode, atau nominalnya berbeda
     */
    private function pastikanFakturCocok(array $faktur, Tagihan $tagihan, string $ref): void
    {
        $this->pastikanCocok("Faktur {$ref}", [
            'penghuni' => $this->refPartner($faktur['partner_id']) !== "PNH-{$tagihan->kontrak->penghuni->id}",
            'periode' => $faktur['invoice_date'] !== $tagihan->periode->toDateString(),
            'nominal' => $this->nominalBeda($faktur['amount_total'], $tagihan->nominal),
        ]);
    }

    /**
     * Referensi saja tidak membuktikan catatan yang ditemukan milik transaksi ini: setelah basis data web
     * dibangun ulang, ID dan nomor tiket terulang dan referensinya sudah dipakai catatan lama. Catatan yang
     * tidak cocok tidak diposting, dibayar, atau diubah.
     *
     * @param  array<string, bool>  $perbedaan  nama bagian => true bila berbeda
     *
     * @throws PengirimanErpGagal
     */
    private function pastikanCocok(string $catatan, array $perbedaan): void
    {
        $beda = array_keys(array_filter($perbedaan));

        if ($beda !== []) {
            throw new PengirimanErpGagal(
                "{$catatan} di Odoo tidak cocok dengan data web (".implode(', ', $beda).'). '
                .'Periksa manual di Odoo.'
            );
        }
    }

    /**
     * Dibandingkan sebagai rupiah bulat: Odoo mengirim angka pecahan.
     */
    private function nominalBeda(mixed $diOdoo, string $diWeb): bool
    {
        return (int) round((float) $diOdoo) !== (int) round((float) $diWeb);
    }

    /**
     * Isi invoice_line_ids berisi satu baris tanpa pajak.
     *
     * @return list<array{0: int, 1: int, 2: array<string, mixed>}>
     */
    private function barisTunggal(string $uraian, string $nominal): array
    {
        return [[0, 0, [
            'name' => $uraian,
            'quantity' => 1,
            'price_unit' => (float) $nominal,
            // Odoo memasang pajak bawaan 12%; daftar pajak dikosongkan dengan perintah set kosong.
            'tax_ids' => [[6, 0, []]],
        ]]];
    }

    /**
     * ref partner pada catatan. $partner adalah nilai many2one dari Odoo: [id, nama], atau false bila kosong.
     */
    private function refPartner(mixed $partner): ?string
    {
        return is_array($partner) ? $this->refPartnerId((int) $partner[0]) : null;
    }

    /**
     * ref partner ber-ID $id; null bila partnernya sudah tidak ada atau ref-nya kosong.
     */
    private function refPartnerId(int $id): ?string
    {
        $data = $this->odoo->read('res.partner', [$id], ['ref']);

        // Odoo mengirim false untuk field teks yang kosong.
        return ($data[0]['ref'] ?? false) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function bacaFaktur(int $idFaktur): array
    {
        return $this->odoo->read('account.move', [$idFaktur], ['state', 'payment_state', 'amount_total', 'amount_residual'])[0];
    }

    /**
     * Mencatat pembayaran faktur lewat wizard account.payment.register.
     */
    private function bayarFaktur(int $idFaktur, Pembayaran $pembayaran, string $ref): void
    {
        $idJurnal = $this->jurnalBank();
        $konteks = ['active_model' => 'account.move', 'active_ids' => [$idFaktur]];

        $idWizard = $this->odoo->create('account.payment.register', [
            'payment_date' => $pembayaran->tanggal_bayar->toDateString(),
            'amount' => (float) $pembayaran->nominal_bayar,
            'journal_id' => $idJurnal,
            'communication' => $ref,
        ], $konteks);

        $this->odoo->aksi(
            'account.payment.register',
            'action_create_payments',
            [$idWizard],
            $konteks + ['dont_redirect_to_payments' => true],
        );
    }

    private function jurnalBank(): int
    {
        if ($this->idJurnalBank !== null) {
            return $this->idJurnalBank;
        }

        $jurnal = $this->odoo->searchRead('account.journal', [['type', '=', 'bank']], ['id'], limit: 1, order: 'id asc');

        if ($jurnal === []) {
            throw new PengirimanErpGagal('Jurnal bank tidak ditemukan di Odoo.');
        }

        return $this->idJurnalBank = (int) $jurnal[0]['id'];
    }

    /**
     * Permintaan perawatan yang namanya diawali referensi dan spasi, atau null bila belum ada.
     *
     * @return array<string, mixed>|null
     *
     * @throws PengirimanErpGagal bila permintaannya ganda
     */
    private function cariPermintaan(string $ref): ?array
    {
        $ada = $this->odoo->searchRead(
            'maintenance.request',
            [['name', '=like', "{$ref} %"]],
            ['id', 'name', 'equipment_id', 'stage_id', 'request_date'],
            limit: 2,
        );

        if (count($ada) > 1) {
            throw new PengirimanErpGagal("Ada lebih dari satu permintaan perawatan {$ref} di Odoo. Periksa manual.");
        }

        return $ada[0] ?? null;
    }

    private function buatPermintaan(TiketKeluhan $tiket, string $ref, int $idPeralatan, string $tanggal): int
    {
        $keterangan = 'Biaya perbaikan '.Format::rupiah($tiket->biaya_perbaikan).'.';

        if ($tiket->vendor !== null) {
            $keterangan .= " Vendor: {$tiket->vendor->nama_vendor}.";
        }

        try {
            return $this->odoo->create('maintenance.request', [
                'name' => $ref.' '.(trim((string) $tiket->jenis_kerusakan) ?: 'Perbaikan'),
                'equipment_id' => $idPeralatan,
                'maintenance_type' => 'corrective',
                'request_date' => $tanggal,
                // Field HTML di Odoo, jadi teksnya di-escape.
                'description' => e($keterangan),
            ]);
        } catch (OdooException $e) {
            if ($this->peralatanSudahTidakAda($e, $idPeralatan)) {
                throw new PengirimanErpGagal(
                    "Peralatan Odoo #{$idPeralatan} untuk fasilitas ini tidak ditemukan. Jalankan php artisan erp:cocokkan-peralatan."
                );
            }

            throw $e;
        }
    }

    /**
     * Setelah Odoo menolak membuat permintaan: apakah sebabnya peralatan yang ditautkan sudah dihapus? Diperiksa
     * dengan membaca Odoo, bukan dengan menebak dari pesan galatnya.
     */
    private function peralatanSudahTidakAda(OdooException $e, int $idPeralatan): bool
    {
        // Tanpa status HTTP (koneksi gagal) atau API key ditolak: pertanyaannya tidak bisa dan tidak perlu dijawab.
        if ($e->statusHttp() === null || $e->statusHttp() === 401) {
            return false;
        }

        return $this->odoo->searchRead(
            'maintenance.equipment',
            [['id', '=', $idPeralatan]],
            ['id'],
            limit: 1,
            context: ['active_test' => false],
        ) === [];
    }

    /**
     * ID tahap selesai pertama (urut sequence) tempat permintaan dipindahkan.
     */
    private function tahapSelesai(): int
    {
        $tahap = $this->odoo->searchRead('maintenance.stage', [['done', '=', true]], ['id'], limit: 1, order: 'sequence asc, id asc');

        if ($tahap === []) {
            throw new PengirimanErpGagal(self::PESAN_TANPA_TAHAP_SELESAI);
        }

        return (int) $tahap[0]['id'];
    }

    /**
     * $tahap adalah nilai many2one stage_id. Tahap done mana pun dianggap selesai, bukan hanya $idTahapSelesai.
     */
    private function diTahapSelesai(mixed $tahap, int $idTahapSelesai): bool
    {
        if (! is_array($tahap)) {
            return false;
        }

        if ((int) $tahap[0] === $idTahapSelesai) {
            return true;
        }

        return ($this->odoo->read('maintenance.stage', [(int) $tahap[0]], ['done'])[0]['done'] ?? false) === true;
    }

    /**
     * Pesan yang bisa dibaca pengelola. Pesan OdooException sudah bebas traceback, API key, dan header.
     */
    private function pesanDariOdoo(OdooException $e): string
    {
        $status = $e->statusHttp();

        if ($status === 401) {
            return self::PESAN_API_KEY_DITOLAK;
        }

        if ($status !== null) {
            // Sisa pesan setelah awalan status HTTP adalah "{name}: {message}" dari Odoo.
            return 'Odoo menolak: '.Str::after($e->getMessage(), "Odoo menjawab HTTP {$status}: ");
        }

        // Tanpa status HTTP: konfigurasi belum lengkap, atau koneksi gagal / waktu habis.
        return str_starts_with($e->getMessage(), 'Konfigurasi Odoo')
            ? $e->getMessage()
            : self::PESAN_TIDAK_TERHUBUNG;
    }

    private function tandaiTerkirim(SinkronisasiErp $baris, int $idCatatan): void
    {
        $baris->forceFill([
            'status_sinkronisasi' => StatusSinkronisasi::Terkirim,
            'odoo_record_id' => $idCatatan,
            'pesan_error' => null,
            'waktu_kirim' => now(),
        ])->save();

        Log::info('Sinkronisasi ERP terkirim.', [
            'referensi_unik' => $baris->referensi_unik,
            'odoo_record_id' => $idCatatan,
        ]);
    }

    private function tandaiGagal(SinkronisasiErp $baris, string $pesan): void
    {
        $pesan = Str::substr($pesan, 0, self::BATAS_PESAN);

        $baris->forceFill([
            'status_sinkronisasi' => StatusSinkronisasi::Gagal,
            'pesan_error' => $pesan,
            'waktu_kirim' => now(),
        ])->save();

        Log::warning('Sinkronisasi ERP gagal.', [
            'referensi_unik' => $baris->referensi_unik,
            'pesan_error' => $pesan,
        ]);
    }
}
