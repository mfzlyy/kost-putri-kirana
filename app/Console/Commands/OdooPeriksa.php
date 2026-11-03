<?php

namespace App\Console\Commands;

use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Pengecekan rutin kesiapan Odoo (hanya membaca): koneksi, hak pengguna, model dan field,
 * jurnal, mata uang, dan masa berlaku API key.
 */
class OdooPeriksa extends Command
{
    protected $signature = 'odoo:periksa';

    protected $description = 'Periksa koneksi, model, field, jurnal, dan masa berlaku API key Odoo (hanya membaca)';

    private const OK = 'OK';

    private const PERINGATAN = 'PERINGATAN';

    private const GAGAL = 'GAGAL';

    private const BATAS_HARI_KEDALUWARSA = 14;

    /**
     * Model dan field yang dibutuhkan integrasi (PRD Bagian 8).
     *
     * @var array<string, list<string>>
     */
    public const MODEL_DIBUTUHKAN = [
        'res.partner' => ['name', 'ref', 'customer_rank', 'supplier_rank'],
        'account.move' => ['move_type', 'partner_id', 'ref', 'invoice_date', 'invoice_date_due', 'invoice_line_ids', 'state', 'payment_state', 'amount_total'],
        'account.move.line' => ['name', 'quantity', 'price_unit', 'tax_ids'],
        'account.payment.register' => ['payment_date', 'amount', 'journal_id', 'communication'],
        'account.journal' => ['type', 'name'],
        'maintenance.equipment' => ['name'],
        'maintenance.request' => ['name', 'equipment_id', 'maintenance_type', 'request_date', 'description', 'stage_id'],
        'maintenance.stage' => ['name', 'done'],
    ];

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $baris = [];

    public function handle(OdooClient $odoo): int
    {
        $this->periksaKoneksiDanPengguna($odoo);
        $this->periksaModel($odoo);
        $this->periksaJurnal($odoo);
        $this->periksaApiKey();

        $this->table(['Cek', 'Status', 'Keterangan'], $this->baris);

        $adaGagal = collect($this->baris)->contains(fn (array $baris) => $baris[1] === self::GAGAL);
        $adaPeringatan = collect($this->baris)->contains(fn (array $baris) => $baris[1] === self::PERINGATAN);

        if ($adaGagal) {
            $this->error('Ada pemeriksaan yang GAGAL.');
        } elseif ($adaPeringatan) {
            $this->warn('Semua pemeriksaan lolos, tetapi ada PERINGATAN.');
        } else {
            $this->info('Semua pemeriksaan OK.');
        }

        // Exit code 0 hanya bila tidak ada GAGAL. Peringatan masa berlaku API key juga mengembalikan 1
        // (lihat periksaApiKey) agar dapat dipakai pada pengecekan rutin.
        return ($adaGagal || $this->peringatanApiKey) ? self::FAILURE : self::SUCCESS;
    }

    private bool $peringatanApiKey = false;

    private function catat(string $cek, string $status, string $keterangan): void
    {
        $this->baris[] = [$cek, $status, $keterangan];
    }

    private function periksaKoneksiDanPengguna(OdooClient $odoo): void
    {
        try {
            $konteks = $odoo->panggil('res.users', 'context_get');
        } catch (OdooException $e) {
            $this->catat('Koneksi', self::GAGAL, $e->getMessage());

            return;
        }

        $uid = (int) ($konteks['uid'] ?? 0);

        $this->catat('Koneksi', self::OK, sprintf(
            'uid=%d lang=%s tz=%s',
            $uid,
            $konteks['lang'] ?? '-',
            $konteks['tz'] ?? '-',
        ));

        $this->periksaPengguna($odoo, $uid);
    }

    private function periksaPengguna(OdooClient $odoo, int $uid): void
    {
        try {
            $pengguna = Arr::first($odoo->read('res.users', [$uid], ['name', 'login', 'share', 'company_id', 'all_group_ids'])) ?? [];
        } catch (OdooException $e) {
            $this->catat('Pengguna', self::PERINGATAN, 'tidak dapat dibaca: '.$e->getMessage());

            return;
        }

        $this->catat('Pengguna', self::OK, sprintf(
            '%s (%s), pengguna internal=%s',
            $pengguna['name'] ?? '-',
            $pengguna['login'] ?? '-',
            ($pengguna['share'] ?? true) ? 'tidak' : 'ya',
        ));

        $this->periksaGrup($odoo, (array) ($pengguna['all_group_ids'] ?? []));
        $this->periksaMataUang($odoo, $pengguna['company_id'] ?? null);
    }

    /**
     * @param  list<int>  $idGrup
     */
    private function periksaGrup(OdooClient $odoo, array $idGrup): void
    {
        if ($idGrup === []) {
            $this->catat('Grup hak akses', self::PERINGATAN, 'tidak dapat dibaca');

            return;
        }

        try {
            $nama = collect($odoo->read('res.groups', $idGrup, ['full_name']))
                ->pluck('full_name')
                ->filter()
                ->sort()
                ->values();
        } catch (OdooException) {
            $this->catat('Grup hak akses', self::PERINGATAN, 'tidak dapat dibaca (pengguna integrasi tidak berhak membaca res.groups)');

            return;
        }

        $this->catat('Grup hak akses', self::OK, $nama->count().' grup: '.$nama->implode('; '));
    }

    private function periksaMataUang(OdooClient $odoo, mixed $perusahaan): void
    {
        $idPerusahaan = is_array($perusahaan) ? (int) ($perusahaan[0] ?? 0) : 0;

        if ($idPerusahaan === 0) {
            $this->catat('Mata uang', self::GAGAL, 'perusahaan pengguna tidak diketahui');

            return;
        }

        try {
            $data = Arr::first($odoo->read('res.company', [$idPerusahaan], ['name', 'currency_id'])) ?? [];
        } catch (OdooException $e) {
            $this->catat('Mata uang', self::GAGAL, $e->getMessage());

            return;
        }

        $mataUang = is_array($data['currency_id'] ?? null) ? ($data['currency_id'][1] ?? '-') : '-';

        $this->catat(
            'Mata uang',
            $mataUang === 'IDR' ? self::OK : self::GAGAL,
            sprintf('%s: %s', $data['name'] ?? '-', $mataUang),
        );
    }

    private function periksaModel(OdooClient $odoo): void
    {
        foreach (self::MODEL_DIBUTUHKAN as $model => $wajib) {
            try {
                $field = $odoo->fieldsGet($model, ['type', 'required', 'relation', 'selection']);
            } catch (OdooException $e) {
                $this->catat("Model {$model}", self::GAGAL, 'model tidak tersedia: '.$e->getMessage());

                continue;
            }

            $hilang = array_values(array_diff($wajib, array_keys($field)));

            $this->catat(
                "Model {$model}",
                $hilang === [] ? self::OK : self::GAGAL,
                $hilang === [] ? count($wajib).' field tersedia' : 'field tidak ada: '.implode(', ', $hilang),
            );
        }
    }

    private function periksaJurnal(OdooClient $odoo): void
    {
        try {
            $jurnal = collect($odoo->searchRead(
                'account.journal',
                [['type', 'in', ['sale', 'purchase', 'bank', 'cash']]],
                ['name', 'type'],
                order: 'type, id',
            ))->groupBy('type');
        } catch (OdooException $e) {
            $this->catat('Jurnal', self::GAGAL, $e->getMessage());

            return;
        }

        foreach (['sale' => true, 'purchase' => true, 'bank' => true, 'cash' => false] as $tipe => $wajib) {
            $daftar = $jurnal->get($tipe, collect());

            $this->catat(
                "Jurnal {$tipe}",
                $daftar->isNotEmpty() ? self::OK : ($wajib ? self::GAGAL : self::PERINGATAN),
                $daftar->isEmpty()
                    ? 'tidak ada'
                    : $daftar->map(fn (array $j) => "#{$j['id']} {$j['name']}")->implode('; '),
            );
        }
    }

    private function periksaApiKey(): void
    {
        $tanggal = config('services.odoo.api_key_expires_at');

        if (blank($tanggal)) {
            $this->peringatanApiKey = true;
            $this->catat('Masa berlaku API key', self::PERINGATAN, 'ODOO_API_KEY_EXPIRES_AT kosong');

            return;
        }

        try {
            $kedaluwarsa = CarbonImmutable::parse((string) $tanggal)->startOfDay();
        } catch (\Throwable) {
            $this->peringatanApiKey = true;
            $this->catat('Masa berlaku API key', self::PERINGATAN, 'format ODOO_API_KEY_EXPIRES_AT tidak valid (pakai YYYY-MM-DD)');

            return;
        }

        $sisa = (int) now()->startOfDay()->diffInDays($kedaluwarsa, false);

        if ($sisa < self::BATAS_HARI_KEDALUWARSA) {
            $this->peringatanApiKey = true;
            $this->catat(
                'Masa berlaku API key',
                self::PERINGATAN,
                $sisa < 0 ? 'sudah kedaluwarsa' : "sisa {$sisa} hari; buat API key baru sebelum {$kedaluwarsa->toDateString()}",
            );

            return;
        }

        $this->catat('Masa berlaku API key', self::OK, "sisa {$sisa} hari (sampai {$kedaluwarsa->toDateString()})");
    }
}
