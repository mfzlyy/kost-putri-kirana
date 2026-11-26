<?php

namespace App\Services\Odoo;

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusVerifikasi;
use App\Exceptions\PengirimanErpGagal;
use App\Models\Pembayaran;
use App\Models\SinkronisasiErp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mengirim satu baris sinkronisasi_erp ke Odoo. Semua panggilan lewat OdooClient.
 *
 * Setiap panggilan JSON-2 adalah transaksi Odoo tersendiri, jadi pengiriman bisa berhenti di tengah.
 * Karena itu setiap langkah membaca keadaan di Odoo lebih dulu dan melanjutkan dari sana, tanpa membuat
 * catatan ganda. Hanya baris sinkronisasi_erp yang diubah; pembayaran dan tagihan tidak disentuh.
 */
class IntegrasiErpService
{
    public const PESAN_TIDAK_TERHUBUNG = 'Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala.';

    public const PESAN_API_KEY_DITOLAK = 'API key Odoo ditolak. Periksa ODOO_API_KEY dan masa berlakunya.';

    private const BATAS_PESAN = 500;

    /**
     * ID jurnal bank, diingat selama instance ini hidup (satu proses); sengaja tidak disimpan di cache.
     */
    private ?int $idJurnalBank = null;

    public function __construct(private readonly OdooClient $odoo) {}

    /**
     * Mengirim baris yang berstatus menunggu atau gagal. Galat Odoo dan galat bisnis dicatat di baris itu dan
     * tidak dilempar ulang; galat lain dibiarkan lolos supaya ditangani job pengirim.
     */
    public function kirim(SinkronisasiErp $sinkronisasi): void
    {
        $baris = $sinkronisasi->fresh();

        if ($baris === null || ! in_array($baris->status_sinkronisasi, [StatusSinkronisasi::Menunggu, StatusSinkronisasi::Gagal], true)) {
            return;
        }

        try {
            $idCatatan = match ($baris->jenis_transaksi) {
                JenisTransaksiErp::Pembayaran => $this->kirimPembayaran($baris),
                // Belum didukung (F-23, F-24): baris dibiarkan apa adanya.
                JenisTransaksiErp::BiayaPerbaikan, JenisTransaksiErp::Pemeliharaan => null,
            };
        } catch (OdooException $e) {
            $this->tandaiGagal($baris, $this->pesanDariOdoo($e));

            return;
        } catch (PengirimanErpGagal $e) {
            $this->tandaiGagal($baris, $e->getMessage());

            return;
        }

        if ($idCatatan !== null) {
            $this->tandaiTerkirim($baris, $idCatatan);
        }
    }

    /**
     * F-22: pembayaran diterima menjadi faktur pelanggan yang lunas. Mengembalikan ID faktur di Odoo.
     *
     * @throws OdooException
     * @throws PengirimanErpGagal
     */
    private function kirimPembayaran(SinkronisasiErp $sinkronisasi): int
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
        $faktur = $this->cariFaktur($ref);

        if ($faktur === null) {
            $idFaktur = $this->odoo->create('account.move', [
                'move_type' => 'out_invoice',
                'partner_id' => $idPartner,
                'ref' => $ref,
                // Tanggal faktur = periode, supaya laporan bulanan mengelompokkan menurut periode tagihan.
                'invoice_date' => $tagihan->periode->toDateString(),
                'invoice_date_due' => $tagihan->jatuh_tempo->toDateString(),
                'invoice_line_ids' => [[0, 0, [
                    'name' => 'Sewa kos periode '.$tagihan->periode->format('Y-m'),
                    'quantity' => 1,
                    'price_unit' => (float) $tagihan->nominal,
                    // Odoo memasang pajak bawaan 12%; daftar pajak dikosongkan dengan perintah set kosong.
                    'tax_ids' => [[6, 0, []]],
                ]]],
            ]);
            $faktur = ['id' => $idFaktur, 'state' => 'draft', 'payment_state' => 'not_paid'];
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
     * Partner penghuni dicari lewat ref, dibuat bila belum ada. Hanya ID penghuni yang dikirim (NFR-02).
     */
    private function partnerPenghuni(int $penghuniId): int
    {
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
    }

    /**
     * Faktur pelanggan dengan referensi $ref, atau null bila belum ada.
     *
     * @return array<string, mixed>|null
     *
     * @throws PengirimanErpGagal bila fakturnya ganda atau dibatalkan
     */
    private function cariFaktur(string $ref): ?array
    {
        // limit 2 sudah cukup untuk mengenali faktur ganda.
        $ada = $this->odoo->searchRead(
            'account.move',
            [['move_type', '=', 'out_invoice'], ['ref', '=', $ref]],
            ['id', 'state', 'payment_state', 'amount_total'],
            limit: 2,
        );

        if (count($ada) > 1) {
            throw new PengirimanErpGagal("Ada lebih dari satu faktur {$ref} di Odoo. Periksa manual.");
        }

        $faktur = $ada[0] ?? null;

        if ($faktur !== null && $faktur['state'] === 'cancel') {
            throw new PengirimanErpGagal("Faktur {$ref} dibatalkan di Odoo. Periksa manual.");
        }

        return $faktur;
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
            'id_faktur' => $idCatatan,
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
