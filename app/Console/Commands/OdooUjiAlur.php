<?php

namespace App\Console\Commands;

use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooException;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Uji langsung ke instance Odoo (KPK-50): membuktikan tiga bentuk catatan integrasi bisa dibuat
 * lewat JSON-2 dengan hak pengguna integrasi, dan tidak ganda saat diulang.
 * Semua referensi berawalan KPK-UJI- agar terpisah dari data sungguhan. Hanya untuk local.
 */
class OdooUjiAlur extends Command
{
    protected $signature = 'odoo:uji-alur {--ya : Konfirmasi bahwa catatan uji boleh dibuat di Odoo}';

    protected $description = 'Uji alur pembayaran, biaya perbaikan, dan pemeliharaan ke instance Odoo (hanya local)';

    private const REF_PEMBAYARAN = 'KPK-UJI-PAY-000001';

    private const REF_BIAYA = 'KPK-UJI-TKT-0001-B';

    private const REF_BIAYA_NOL = 'KPK-UJI-TKT-0002-B';

    private const REF_PEMELIHARAAN = 'KPK-UJI-TKT-0001-M';

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> [alur, langkah, hasil, ms] */
    private array $baris = [];

    /** @var array<string, string> alur => hasil akhir */
    private array $hasilAlur = [];

    /** @var list<float> */
    private array $semuaMs = [];

    public function handle(OdooClient $odoo): int
    {
        if (! app()->environment('local')) {
            $this->error('Ditolak: perintah ini hanya boleh dijalankan saat APP_ENV=local.');

            return self::FAILURE;
        }

        if (! $this->option('ya')) {
            $this->error('Perintah ini membuat catatan uji (KPK-UJI-) di Odoo. Jalankan dengan opsi --ya untuk melanjutkan.');

            return self::FAILURE;
        }

        $this->jalankanAlur('A. Pembayaran sewa (F-22)', fn () => $this->alurPembayaran($odoo));
        $this->jalankanAlur('B. Biaya perbaikan (F-23)', fn () => $this->alurBiayaPerbaikan($odoo));
        $this->jalankanAlur('C. Pemeliharaan (F-24)', fn () => $this->alurPemeliharaan($odoo));

        $this->table(['Alur', 'Langkah', 'Hasil', 'ms'], $this->baris);

        foreach ($this->hasilAlur as $alur => $hasil) {
            $this->line("{$alur}: {$hasil}");
        }

        if ($this->semuaMs !== []) {
            $this->line(sprintf(
                'Waktu panggilan: %d panggilan, rata-rata %d ms, terlama %d ms',
                count($this->semuaMs),
                array_sum($this->semuaMs) / count($this->semuaMs),
                max($this->semuaMs),
            ));
        }

        $this->dataIsu10($odoo);

        $gagal = collect($this->hasilAlur)->contains(fn (string $hasil) => str_starts_with($hasil, 'GAGAL'));

        return $gagal ? self::FAILURE : self::SUCCESS;
    }

    private function jalankanAlur(string $nama, Closure $alur): void
    {
        $this->alurSaatIni = $nama;

        try {
            $this->hasilAlur[$nama] = $alur();
        } catch (OdooException $e) {
            $this->catat('(berhenti)', 'GAGAL: '.$e->getMessage());
            $this->hasilAlur[$nama] = 'GAGAL: '.$e->getMessage();
        }
    }

    private string $alurSaatIni = '';

    private function catat(string $langkah, string $hasil, ?float $ms = null): void
    {
        $this->baris[] = [$this->alurSaatIni, $langkah, $hasil, $ms === null ? '-' : (string) round($ms)];
    }

    /**
     * Menjalankan satu panggilan, mencatat waktunya, dan mengembalikan hasilnya.
     *
     * @template T
     *
     * @param  Closure(): T  $panggilan
     * @return T
     */
    private function ukur(string $langkah, Closure $panggilan, ?Closure $ringkas = null): mixed
    {
        $mulai = hrtime(true);

        try {
            $hasil = $panggilan();
        } catch (OdooException $e) {
            $this->catat($langkah, 'GAGAL: '.$e->getMessage(), (hrtime(true) - $mulai) / 1e6);

            throw $e;
        }

        $ms = (hrtime(true) - $mulai) / 1e6;
        $this->semuaMs[] = $ms;
        $this->catat($langkah, $ringkas ? $ringkas($hasil) : 'OK', $ms);

        return $hasil;
    }

    /**
     * Mencari partner berdasarkan ref; membuatnya bila belum ada. Mengembalikan ID partner.
     *
     * @param  array<string, mixed>  $nilai
     */
    private function partner(OdooClient $odoo, string $ref, array $nilai): int
    {
        $ada = $this->ukur("cari partner {$ref}", fn () => $odoo->searchRead('res.partner', [['ref', '=', $ref]], ['name', 'ref'], limit: 1));

        if ($ada !== []) {
            $this->catat("partner {$ref}", "sudah ada (id {$ada[0]['id']}), tidak dibuat ulang");

            return (int) $ada[0]['id'];
        }

        return $this->ukur(
            "buat partner {$ref}",
            fn () => $odoo->create('res.partner', ['ref' => $ref] + $nilai),
            fn (int $id) => "OK, id {$id}",
        );
    }

    /**
     * Baris faktur satu item tanpa pajak (tax_ids = command set []).
     *
     * @return list<array{0: int, 1: int, 2: array<string, mixed>}>
     */
    private function barisFaktur(string $nama, int|float $harga): array
    {
        return [[0, 0, [
            'name' => $nama,
            'quantity' => 1,
            'price_unit' => $harga,
            'tax_ids' => [[6, 0, []]],
        ]]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cariFaktur(OdooClient $odoo, string $jenis, string $ref): ?array
    {
        $ada = $this->ukur(
            "cek idempotensi {$ref}",
            fn () => $odoo->searchRead('account.move', [['move_type', '=', $jenis], ['ref', '=', $ref]], ['state', 'payment_state', 'amount_total'], limit: 1),
            fn (array $hasil) => $hasil === [] ? 'belum ada' : "sudah ada (id {$hasil[0]['id']})",
        );

        return $ada[0] ?? null;
    }

    private function alurPembayaran(OdooClient $odoo): string
    {
        $partner = $this->partner($odoo, 'PNH-9001', ['name' => 'Penghuni #9001', 'customer_rank' => 1]);
        $faktur = $this->cariFaktur($odoo, 'out_invoice', self::REF_PEMBAYARAN);
        $dibuat = false;

        if ($faktur === null) {
            $dibuat = true;

            $id = $this->ukur(
                'create faktur pelanggan',
                fn () => $odoo->create('account.move', [
                    'move_type' => 'out_invoice',
                    'partner_id' => $partner,
                    'ref' => self::REF_PEMBAYARAN,
                    'invoice_date' => '2026-11-01',
                    'invoice_date_due' => '2026-11-10',
                    'invoice_line_ids' => $this->barisFaktur('Sewa kos periode 2026-11', 1400000),
                ]),
                fn (int $id) => "OK, id {$id}",
            );

            $this->ukur('action_post', fn () => $odoo->aksi('account.move', 'action_post', [$id]));

            $jurnalBank = $this->ukur(
                'cari jurnal bank',
                fn () => $odoo->searchRead('account.journal', [['type', '=', 'bank']], ['name'], limit: 1, order: 'id'),
                fn (array $hasil) => $hasil === [] ? 'tidak ada jurnal bank' : "OK, id {$hasil[0]['id']}",
            );

            $konteks = ['active_model' => 'account.move', 'active_ids' => [$id]];

            $wizard = $this->ukur(
                'create account.payment.register',
                fn () => $odoo->create('account.payment.register', [
                    'payment_date' => '2026-11-03',
                    'journal_id' => $jurnalBank[0]['id'],
                    'communication' => self::REF_PEMBAYARAN,
                ], $konteks),
                fn (int $id) => "OK, id {$id}",
            );

            $this->ukur(
                'action_create_payments',
                fn () => $odoo->aksi('account.payment.register', 'action_create_payments', [$wizard], $konteks + ['dont_redirect_to_payments' => true]),
            );
        } else {
            $id = (int) $faktur['id'];
        }

        $hasil = Arr::first($this->ukur(
            'baca faktur',
            fn () => $odoo->read('account.move', [$id], ['state', 'payment_state', 'amount_total', 'amount_residual', 'ref']),
            fn (array $baris) => sprintf(
                'state=%s payment_state=%s total=%s sisa=%s',
                $baris[0]['state'], $baris[0]['payment_state'], $baris[0]['amount_total'], $baris[0]['amount_residual'],
            ),
        ));

        $sesuai = $hasil['state'] === 'posted' && $hasil['payment_state'] === 'paid'
            && (float) $hasil['amount_total'] === 1400000.0 && (float) $hasil['amount_residual'] === 0.0;

        return ($sesuai ? 'OK' : 'GAGAL: nilai tidak sesuai harapan').($dibuat ? ' (dibuat)' : ' (sudah ada, tidak dibuat ulang)')." - faktur id {$id}";
    }

    private function alurBiayaPerbaikan(OdooClient $odoo): string
    {
        $vendor = $this->partner($odoo, 'VDR-9001', ['name' => 'Vendor Uji KPK', 'supplier_rank' => 1]);
        $ringkasan = [];

        foreach ([
            [self::REF_BIAYA, 150000, 'Perbaikan AC tidak dingin - AC Kamar 03'],
            [self::REF_BIAYA_NOL, 0, 'Perbaikan tanpa biaya - AC Kamar 03'],
        ] as [$ref, $biaya, $uraian]) {
            $tagihan = $this->cariFaktur($odoo, 'in_invoice', $ref);
            $dibuat = false;

            if ($tagihan === null) {
                $dibuat = true;

                $id = $this->ukur(
                    "create tagihan vendor {$ref}",
                    fn () => $odoo->create('account.move', [
                        'move_type' => 'in_invoice',
                        'partner_id' => $vendor,
                        'ref' => $ref,
                        'invoice_date' => '2026-11-03',
                        'invoice_line_ids' => $this->barisFaktur($uraian, $biaya),
                    ]),
                    fn (int $id) => "OK, id {$id}",
                );

                try {
                    $this->ukur("action_post {$ref}", fn () => $odoo->aksi('account.move', 'action_post', [$id]));
                } catch (OdooException $e) {
                    if ($biaya !== 0) {
                        throw $e;
                    }
                    // Tagihan bernilai 0 ditolak: temuan untuk F-20 butir 2, bukan kegagalan alur.
                }
            } else {
                $id = (int) $tagihan['id'];
            }

            $baris = Arr::first($this->ukur(
                "baca tagihan {$ref}",
                fn () => $odoo->read('account.move', [$id], ['state', 'amount_total', 'partner_id']),
                fn (array $hasil) => sprintf('state=%s total=%s partner=%s', $hasil[0]['state'], $hasil[0]['amount_total'], json_encode($hasil[0]['partner_id'])),
            ));

            $ringkasan[] = "{$ref} id {$id} state={$baris['state']}".($dibuat ? ' (dibuat)' : ' (sudah ada)');

            if ($biaya > 0 && $baris['state'] !== 'posted') {
                return "GAGAL: {$ref} tidak berstatus posted";
            }
        }

        return 'OK - '.implode('; ', $ringkasan);
    }

    private function alurPemeliharaan(OdooClient $odoo): string
    {
        $peralatan = $this->ukur(
            'cari peralatan',
            fn () => $odoo->searchRead('maintenance.equipment', [['name', '=', 'KPK-UJI AC Kamar 03']], ['name'], limit: 1),
            fn (array $hasil) => $hasil === [] ? 'belum ada' : "sudah ada (id {$hasil[0]['id']})",
        );

        $idPeralatan = $peralatan[0]['id'] ?? $this->ukur(
            'create maintenance.equipment',
            fn () => $odoo->create('maintenance.equipment', ['name' => 'KPK-UJI AC Kamar 03']),
            fn (int $id) => "OK, id {$id}",
        );

        $ada = $this->ukur(
            'cek idempotensi '.self::REF_PEMELIHARAAN,
            fn () => $odoo->searchRead('maintenance.request', [['name', '=like', self::REF_PEMELIHARAAN.'%']], ['name'], limit: 1),
            fn (array $hasil) => $hasil === [] ? 'belum ada' : "sudah ada (id {$hasil[0]['id']})",
        );

        $dibuat = $ada === [];

        if ($dibuat) {
            $id = $this->ukur(
                'create maintenance.request',
                fn () => $odoo->create('maintenance.request', [
                    'name' => self::REF_PEMELIHARAAN.' AC tidak dingin',
                    'equipment_id' => $idPeralatan,
                    'maintenance_type' => 'corrective',
                    'request_date' => '2026-11-03',
                    'description' => 'Freon diisi ulang. Biaya Rp150.000.',
                ]),
                fn (int $id) => "OK, id {$id}",
            );

            $tahapSelesai = $this->ukur(
                'cari tahap selesai (done=true)',
                fn () => $odoo->searchRead('maintenance.stage', [['done', '=', true]], ['name'], limit: 1, order: 'sequence, id'),
                fn (array $hasil) => $hasil === [] ? 'tidak ada tahap selesai' : "OK, id {$hasil[0]['id']} ({$hasil[0]['name']})",
            );

            if ($tahapSelesai === []) {
                return 'GAGAL: tidak ada maintenance.stage dengan done = true';
            }

            $this->ukur('write stage_id', fn () => $odoo->write('maintenance.request', [$id], ['stage_id' => $tahapSelesai[0]['id']]));
        } else {
            $id = (int) $ada[0]['id'];
        }

        $baris = Arr::first($this->ukur(
            'baca permintaan',
            fn () => $odoo->read('maintenance.request', [$id], ['name', 'equipment_id', 'stage_id', 'maintenance_team_id']),
            fn (array $hasil) => sprintf(
                'name=%s stage=%s equipment=%s tim=%s',
                $hasil[0]['name'], json_encode($hasil[0]['stage_id']), json_encode($hasil[0]['equipment_id']), json_encode($hasil[0]['maintenance_team_id']),
            ),
        ));

        $tahap = is_array($baris['stage_id']) ? $baris['stage_id'][0] : null;
        $selesai = $tahap !== null && $this->ukur(
            'cek tahap selesai',
            fn () => $odoo->read('maintenance.stage', [$tahap], ['done']),
            fn (array $hasil) => 'done='.json_encode($hasil[0]['done']),
        )[0]['done'] === true;

        return ($selesai ? 'OK' : 'GAGAL: permintaan tidak berada di tahap selesai')
            .($dibuat ? ' (dibuat)' : ' (sudah ada, tidak dibuat ulang)')." - permintaan id {$id}";
    }

    /**
     * Bahan keputusan ISU-10: angka November 2026 dan daftar menu laporan yang bisa dibuka.
     */
    private function dataIsu10(OdooClient $odoo): void
    {
        $this->newLine();
        $this->line('Data ISU-10 (November 2026)');

        try {
            $jumlah = fn (string $jenis) => collect($odoo->searchRead('account.move', [
                ['move_type', '=', $jenis],
                ['state', '=', 'posted'],
                ['invoice_date', '>=', '2026-11-01'],
                ['invoice_date', '<=', '2026-11-30'],
            ], ['amount_total']))->sum('amount_total');

            $pemasukan = $jumlah('out_invoice');
            $pengeluaran = $jumlah('in_invoice');

            $this->line(sprintf('Pemasukan (faktur pelanggan posted): %s', number_format($pemasukan, 2, ',', '.')));
            $this->line(sprintf('Pengeluaran (tagihan vendor posted): %s', number_format($pengeluaran, 2, ',', '.')));
            $this->line(sprintf('Selisih: %s', number_format($pemasukan - $pengeluaran, 2, ',', '.')));
        } catch (OdooException $e) {
            $this->warn('Tidak dapat menghitung: '.$e->getMessage());
        }

        try {
            // complete_name tidak tersimpan di basis data sehingga tidak bisa dicari langsung:
            // cari dulu menu bernama "Reporting", lalu baca seluruh turunannya.
            $induk = collect($odoo->searchRead('ir.ui.menu', [['name', '=', 'Reporting']], ['name']))->pluck('id')->all();

            $menu = collect($induk === [] ? [] : $odoo->searchRead('ir.ui.menu', [['id', 'child_of', $induk]], ['complete_name'], order: 'id'))
                ->pluck('complete_name')
                ->sort()
                ->values();

            $this->line('Menu laporan yang bisa dibuka pengguna integrasi ('.$menu->count().'):');
            $menu->each(fn (string $nama) => $this->line("  - {$nama}"));
        } catch (OdooException $e) {
            $this->warn('Daftar menu tidak dapat dibaca: '.$e->getMessage());
        }
    }
}
