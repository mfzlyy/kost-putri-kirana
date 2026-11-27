<?php

use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Models\SinkronisasiErp;
use App\Services\Odoo\IntegrasiErpService;
use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    // Job tidak dijalankan: di sini service dipanggil langsung.
    Queue::fake();
    $this->kunci = konfigurasiOdooUji();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Baris sinkronisasi menunggu untuk pembayaran diterima Rp1.250.000 periode November 2026, tanggal bayar 3 November.
 */
function sinkronPembayaranUji(): SinkronisasiErp
{
    $kontrak = buatKontrakUji('ZX9', 'Rina Maharani');

    return buatPembayaranDiterima($kontrak, '2026-11-01', '2026-11-03', '2026-11-04 09:00', 1250000, sinkron: 'menunggu')->sinkronisasi;
}

const ALUR_BAYAR = [
    'account.journal/search_read',
    'account.payment.register/create',
    'account.payment.register/action_create_payments',
    'account.move/read',
];

it('F-22: alur baru lengkap membuat partner, faktur, posting, pembayaran, lalu menandai terkirim', function () {
    fakeOdooPembayaran();

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->pesan_error)->toBeNull()
        ->and($baris->odoo_record_id)->toBe(41)
        ->and($baris->waktu_kirim->equalTo(now()))->toBeTrue()
        ->and(panggilanOdoo())->toBe([
            'res.partner/search_read',
            'res.partner/create',
            'account.move/search_read',
            'account.move/create',
            'account.move/action_post',
            'account.move/read',
            ...ALUR_BAYAR,
        ]);
});

it('F-22: payload faktur, pencarian, dan pembayaran mengikuti dokumen teknis dengan angka berupa number', function () {
    fakeOdooPembayaran();
    $baris = sinkronPembayaranUji();
    $ref = $baris->referensi_unik;

    kirimKeOdoo($baris);

    expect($ref)->toStartWith('KPK-PAY-')
        ->and(bodyOdoo('account.move/search_read'))->toBe([
            'domain' => [['move_type', '=', 'out_invoice'], ['ref', '=', $ref]],
            'fields' => ['id', 'state', 'payment_state', 'amount_total', 'invoice_date', 'partner_id'],
            'limit' => 2,
        ])
        ->and(bodyOdoo('account.move/create'))->toBe([
            'vals_list' => [[
                'move_type' => 'out_invoice',
                'partner_id' => 7,
                'ref' => $ref,
                'invoice_date' => '2026-11-01',
                'invoice_date_due' => '2026-11-10',
                'invoice_line_ids' => [[0, 0, [
                    'name' => 'Sewa kos periode 2026-11',
                    'quantity' => 1,
                    'price_unit' => 1250000,
                    'tax_ids' => [[6, 0, []]],
                ]]],
            ]],
        ])
        ->and(bodyOdoo('account.move/action_post'))->toBe(['ids' => [41]])
        ->and(bodyOdoo('account.journal/search_read'))->toBe([
            'domain' => [['type', '=', 'bank']],
            'fields' => ['id'],
            'limit' => 1,
            'order' => 'id asc',
        ])
        ->and(bodyOdoo('account.payment.register/create'))->toBe([
            'vals_list' => [[
                'payment_date' => '2026-11-03',
                'amount' => 1250000,
                'journal_id' => 6,
                'communication' => $ref,
            ]],
            'context' => ['active_model' => 'account.move', 'active_ids' => [41]],
        ])
        ->and(bodyOdoo('account.payment.register/action_create_payments'))->toBe([
            'ids' => [3],
            'context' => ['active_model' => 'account.move', 'active_ids' => [41], 'dont_redirect_to_payments' => true],
        ]);
});

it('partner yang sudah ada dipakai ulang tanpa membuat partner baru', function () {
    fakeOdooPembayaran(['res.partner/search_read' => Http::response([['id' => 19]])]);

    kirimKeOdoo(sinkronPembayaranUji());

    expect(panggilanOdoo())->not->toContain('res.partner/create')
        ->and(bodyOdoo('account.move/create')['vals_list'][0]['partner_id'])->toBe(19);
});

it('NFR-02: hanya ID penghuni yang dikirim, tanpa nama, email, identitas, atau nomor kamar', function () {
    fakeOdooPembayaran();
    $baris = sinkronPembayaranUji();
    $kontrak = $baris->pembayaran->tagihan->kontrak;
    $penghuni = $kontrak->penghuni;

    kirimKeOdoo($baris);

    expect(bodyOdoo('res.partner/create'))->toBe([
        'vals_list' => [['name' => "Penghuni #{$penghuni->id}", 'ref' => "PNH-{$penghuni->id}", 'customer_rank' => 1]],
    ]);

    $terlarang = [
        'Rina', 'Maharani', $penghuni->user->email, $penghuni->no_identitas_enc, $penghuni->no_telepon,
        $kontrak->kamar->nomor_kamar, 'bukti',
    ];

    foreach (Http::recorded() as [$request]) {
        foreach ($terlarang as $isi) {
            expect($isi)->toBeString()->not->toBeEmpty()
                ->and($request->body())->not->toContain($isi);
        }
    }
});

it('idempoten: faktur yang sudah posted dan lunas tidak dibuat atau dibayar lagi', function (string $paymentState) {
    fakeOdooPembayaran([
        'res.partner/search_read' => Http::response([['id' => 7]]),
        'account.move/search_read' => Http::response(fakturOdoo($paymentState)),
        'account.move/read' => Http::response(fakturOdoo($paymentState)),
    ]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(41)
        ->and(panggilanOdoo())->toBe(['res.partner/search_read', 'account.move/search_read', 'res.partner/read', 'account.move/read']);
})->with(['paid', 'in_payment']);

it('melanjutkan faktur draft yang cocok: hanya posting lalu pembayaran, tanpa faktur baru', function () {
    fakeOdooPembayaran([
        'res.partner/search_read' => Http::response([['id' => 7]]),
        'account.move/search_read' => Http::response(fakturOdoo('not_paid', 'draft', 55)),
    ]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(55)
        ->and(panggilanOdoo())->toBe([
            'res.partner/search_read',
            'account.move/search_read',
            'res.partner/read',
            'account.move/action_post',
            'account.move/read',
            ...ALUR_BAYAR,
        ])
        ->and(bodyOdoo('res.partner/read'))->toBe(['ids' => [7], 'fields' => ['ref']])
        ->and(bodyOdoo('account.move/action_post'))->toBe(['ids' => [55]]);
});

it('melanjutkan faktur posted belum dibayar: hanya pembayaran', function () {
    fakeOdooPembayaran([
        'res.partner/search_read' => Http::response([['id' => 7]]),
        'account.move/search_read' => Http::response(fakturOdoo('not_paid', id: 55)),
        'account.move/read' => Http::response(fakturOdoo('paid', id: 55)),
    ]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(panggilanOdoo())->toBe(['res.partner/search_read', 'account.move/search_read', 'res.partner/read', ...ALUR_BAYAR])
        ->and(bodyOdoo('account.payment.register/create')['context']['active_ids'])->toBe([55]);
});

it('keadaan yang tidak bisa dilanjutkan ditandai gagal tanpa membuat atau mengubah apa pun di Odoo', function (array $faktur, string $pesan) {
    fakeOdooPembayaran(['account.move/search_read' => Http::response($faktur)]);
    $baris = sinkronPembayaranUji();

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe(str_replace('{REF}', $baris->referensi_unik, $pesan))
        ->and($hasil->odoo_record_id)->toBeNull()
        ->and($hasil->waktu_kirim->equalTo(now()))->toBeTrue()
        ->and(panggilanOdoo())->not->toContain('account.move/create', 'account.move/action_post', 'account.payment.register/create');
})->with([
    'baru dibayar sebagian' => [fn () => fakturOdoo('partial'), 'Faktur {REF} baru dibayar sebagian di Odoo. Periksa manual.'],
    'dibatalkan' => [fn () => fakturOdoo('not_paid', 'cancel'), 'Faktur {REF} dibatalkan di Odoo. Periksa manual.'],
    'ganda' => [fn () => [...fakturOdoo('paid'), ...fakturOdoo('not_paid', id: 42)], 'Ada lebih dari satu faktur {REF} di Odoo. Periksa manual.'],
]);

// ---- Faktur yang ditemukan harus cocok dengan data web ----

it('faktur ber-referensi sama tetapi datanya berbeda ditandai gagal tanpa diposting atau dibayar', function (array $timpa, ?string $refPartner, string $beda, string $state, string $paymentState) {
    $baris = sinkronPembayaranUji();
    $penghuniId = $baris->pembayaran->tagihan->kontrak->penghuni_id;
    fakeOdooPembayaran([
        'account.move/search_read' => Http::response(fakturOdoo($paymentState, $state, timpa: $timpa)),
        'res.partner/read' => Http::response([['id' => 7, 'ref' => $refPartner ?? "PNH-{$penghuniId}"]]),
    ]);

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe("Faktur {$baris->referensi_unik} di Odoo tidak cocok dengan data web ({$beda}). Periksa manual di Odoo.")
        ->and($hasil->odoo_record_id)->toBeNull()
        // Hanya membaca: tidak ada faktur baru, posting, pembayaran, atau perubahan di Odoo.
        ->and(array_values(array_diff(panggilanOdoo(), ['res.partner/search_read', 'res.partner/create', 'account.move/search_read', 'res.partner/read'])))->toBe([]);
})->with([
    'nominal berbeda' => [['amount_total' => 1400000.0], null, 'nominal'],
    'nominal beda satu rupiah' => [['amount_total' => 1250001.0], null, 'nominal'],
    'periode berbeda' => [['invoice_date' => '2026-10-01'], null, 'periode'],
    'tanggal faktur kosong' => [['invoice_date' => false], null, 'periode'],
    'partner penghuni lain' => [[], 'PNH-999999', 'penghuni'],
    'partner tanpa ref' => [[], '', 'penghuni'],
    'faktur tanpa partner' => [['partner_id' => false], null, 'penghuni'],
    'semuanya berbeda' => [['amount_total' => 900000.0, 'invoice_date' => '2025-01-01'], 'VDR-1', 'penghuni, periode, nominal'],
])->with([
    'draft' => ['draft', 'not_paid'],
    'posted belum dibayar' => ['posted', 'not_paid'],
    'posted lunas' => ['posted', 'paid'],
]);

it('nominal faktur dibandingkan sebagai rupiah bulat', function () {
    fakeOdooPembayaran([
        'account.move/search_read' => Http::response(fakturOdoo('paid', timpa: ['amount_total' => 1249999.9999999])),
        'account.move/read' => Http::response(fakturOdoo('paid')),
    ]);

    expect(kirimKeOdoo(sinkronPembayaranUji())->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim);
});

// ---- Kunci per baris ----

it('baris yang kuncinya dipegang proses lain dilewati, lalu terkirim setelah kunci dilepas', function (StatusSinkronisasi $awal) {
    fakeOdooPembayaran();
    $baris = sinkronPembayaranUji();
    $baris->forceFill(['status_sinkronisasi' => $awal])->save();
    $sebelum = (array) DB::table('sinkronisasi_erp')->find($baris->id);
    $kunci = Cache::lock("sinkronisasi-erp:{$baris->id}", 330);
    expect($kunci->get())->toBeTrue();

    kirimKeOdoo($baris);

    Http::assertNothingSent();
    expect((array) DB::table('sinkronisasi_erp')->find($baris->id))->toBe($sebelum);

    $kunci->release();

    expect(kirimKeOdoo($baris)->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim);
})->with([StatusSinkronisasi::Menunggu, StatusSinkronisasi::Gagal]);

it('selama pengiriman berjalan, pengiriman kedua untuk baris yang sama tidak memanggil Odoo', function () {
    $baris = sinkronPembayaranUji();
    $kunciBebas = null;
    fakeOdooPembayaran([
        'res.partner/search_read' => function () use ($baris, &$kunciBebas) {
            $kunciBebas = Cache::lock("sinkronisasi-erp:{$baris->id}", 1)->get();
            app(IntegrasiErpService::class)->kirim($baris);

            return Http::response([]);
        },
    ]);

    expect(kirimKeOdoo($baris)->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($kunciBebas)->toBeFalse()
        ->and(array_count_values(panggilanOdoo())['res.partner/search_read'])->toBe(1)
        ->and(array_count_values(panggilanOdoo())['account.move/create'])->toBe(1);
});

it('kunci dilepas setelah berhasil, gagal, maupun galat tak terduga', function (Closure $balasan) {
    Http::fake(['*' => $balasan()]);
    $baris = sinkronPembayaranUji();

    rescue(fn () => app(IntegrasiErpService::class)->kirim($baris), report: false);

    expect(Cache::lock("sinkronisasi-erp:{$baris->id}", 1)->get())->toBeTrue();
})->with([
    'galat Odoo' => [fn () => Http::response('Bad Gateway', 502)],
    'galat tak terduga' => [fn () => Http::response('<html>bukan JSON</html>')],
]);

it('kunci dilepas setelah baris terkirim', function () {
    fakeOdooPembayaran();
    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(Cache::lock("sinkronisasi-erp:{$baris->id}", 1)->get())->toBeTrue();
});
it('gagal bila jurnal bank tidak ada', function () {
    fakeOdooPembayaran(['account.journal/search_read' => Http::response([])]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Jurnal bank tidak ditemukan di Odoo.')
        ->and(panggilanOdoo())->not->toContain('account.payment.register/create');
});

it('gagal bila faktur belum lunas setelah pembayaran', function () {
    fakeOdooPembayaran([
        'account.move/read' => Http::sequence()->push(fakturOdoo('not_paid'))->push(fakturOdoo('not_paid')),
    ]);
    $baris = sinkronPembayaranUji();

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe("Faktur {$baris->referensi_unik} sudah dibuat tetapi belum lunas di Odoo (status not_paid).")
        ->and($hasil->odoo_record_id)->toBeNull();
});

it('galat Odoo di tengah alur dicatat sebagai gagal tanpa traceback atau API key', function () {
    fakeOdooPembayaran(['account.move/action_post' => Http::response([
        'name' => 'builtins.ValueError',
        'message' => "Invalid field, token {$this->kunci}",
        'debug' => 'Traceback (most recent call last): rahasia',
    ], 500)]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Odoo menolak: builtins.ValueError: Invalid field, token [disembunyikan]')
        ->and($baris->pesan_error)->not->toContain($this->kunci)
        ->and($baris->pesan_error)->not->toContain('Traceback');
});

it('koneksi gagal atau waktu habis menghasilkan pesan Odoo tidak dapat dihubungi', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds')]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala.');
});

it('HTTP 401 menghasilkan pesan API key ditolak', function () {
    Http::fake(['*' => Http::response(['name' => 'werkzeug.exceptions.Unauthorized', 'message' => 'Invalid apikey'], 401)]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('API key Odoo ditolak. Periksa ODOO_API_KEY dan masa berlakunya.');
});

it('konfigurasi kosong menghasilkan pesan konfigurasi tanpa permintaan HTTP', function () {
    config(['services.odoo.api_key' => null]);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Konfigurasi Odoo belum lengkap.');
    Http::assertNothingSent();
});

it('kegagalan Odoo tidak mengubah status pembayaran dan tagihan', function () {
    Http::fake(['*' => Http::response('Bad Gateway', 502)]);
    $baris = sinkronPembayaranUji();
    $pembayaran = $baris->pembayaran;
    $sebelum = [
        (array) DB::table('pembayaran')->find($pembayaran->id),
        (array) DB::table('tagihan')->find($pembayaran->tagihan_id),
    ];

    expect(kirimKeOdoo($baris)->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal);

    expect([
        (array) DB::table('pembayaran')->find($pembayaran->id),
        (array) DB::table('tagihan')->find($pembayaran->tagihan_id),
    ])->toBe($sebelum)
        ->and($pembayaran->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Diterima)
        ->and($pembayaran->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::Lunas);
});

it('baris gagal bisa dikirim lagi dan pesan galatnya dihapus saat berhasil', function () {
    fakeOdooPembayaran();
    $baris = sinkronPembayaranUji();
    $baris->forceFill(['status_sinkronisasi' => StatusSinkronisasi::Gagal, 'pesan_error' => 'Galat lama.'])->save();

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($hasil->pesan_error)->toBeNull();
});

it('menolak nominal pembayaran yang berbeda dari tagihan tanpa memanggil Odoo', function () {
    $baris = sinkronPembayaranUji();
    DB::table('pembayaran')->where('id', $baris->pembayaran_id)->update(['nominal_bayar' => 1200000]);

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe('Nominal pembayaran berbeda dengan nominal tagihan.');
    Http::assertNothingSent();
});

it('menolak pembayaran yang tidak berstatus diterima tanpa memanggil Odoo', function () {
    $baris = sinkronPembayaranUji();
    DB::table('pembayaran')->where('id', $baris->pembayaran_id)->update(['status_verifikasi' => 'menunggu']);

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe('Pembayaran tidak berstatus diterima.');
    Http::assertNothingSent();
});

it('baris terkirim tidak memanggil Odoo, juga bila objek di memori masih menunggu', function () {
    $basi = sinkronPembayaranUji();
    DB::table('sinkronisasi_erp')->where('id', $basi->id)->update(['status_sinkronisasi' => 'terkirim', 'odoo_record_id' => 9]);

    $hasil = kirimKeOdoo($basi);

    expect($hasil->odoo_record_id)->toBe(9)->and($hasil->waktu_kirim)->toBeNull();
    Http::assertNothingSent();
});

it('kunci partner penghuni dipegang proses lain: baris dilewati tanpa membuat partner, lalu terkirim setelah dilepas', function () {
    fakeOdooPembayaran();
    Event::fake([IntegrasiErpService::PERISTIWA_DILEWATI]);
    $baris = sinkronPembayaranUji();
    $penghuniId = $baris->pembayaran->tagihan->kontrak->penghuni_id;
    $sebelum = (array) DB::table('sinkronisasi_erp')->find($baris->id);
    $kunci = Cache::lock("odoo-partner-penghuni:{$penghuniId}", 60);
    expect($kunci->get())->toBeTrue();

    kirimKeOdoo($baris);

    Http::assertNothingSent();
    Event::assertDispatched(IntegrasiErpService::PERISTIWA_DILEWATI);
    expect((array) DB::table('sinkronisasi_erp')->find($baris->id))->toBe($sebelum)
        // Kunci baris dilepas walaupun barisnya dilewati.
        ->and(Cache::lock("sinkronisasi-erp:{$baris->id}", 1)->get())->toBeTrue();

    $kunci->release();
    Cache::lock("sinkronisasi-erp:{$baris->id}")->forceRelease();

    expect(kirimKeOdoo($baris)->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(Cache::lock("odoo-partner-penghuni:{$penghuniId}", 1)->get())->toBeTrue();
});

it('pesan_error yang panjang dipotong paling banyak 500 karakter', function () {
    // OdooClient sudah memotong pesannya; klien tiruan ini memastikan service punya batas sendiri.
    $klien = Mockery::mock(OdooClient::class);
    $klien->shouldReceive('searchRead')->andThrow(new OdooException(
        'Odoo menjawab HTTP 422: odoo.exceptions.UserError: '.str_repeat('é panjang ', 200),
        422,
    ));
    app()->instance(OdooClient::class, $klien);

    $baris = kirimKeOdoo(sinkronPembayaranUji());

    expect(mb_strlen($baris->pesan_error))->toBe(500)
        ->and($baris->pesan_error)->toStartWith('Odoo menolak: odoo.exceptions.UserError: é panjang');
});

it('ID jurnal bank hanya dicari sekali selama satu instance service', function () {
    fakeOdooPembayaran([
        'account.move/read' => Http::sequence()
            ->push(fakturOdoo('not_paid'))->push(fakturOdoo('paid'))
            ->push(fakturOdoo('not_paid'))->push(fakturOdoo('paid')),
    ]);
    $pertama = sinkronPembayaranUji();
    $kedua = buatPembayaranDiterima(buatKontrakUji('ZX8', 'Maya Sari'), '2026-11-01', '2026-11-02', '2026-11-04 09:00', sinkron: 'menunggu')->sinkronisasi;
    $service = app(IntegrasiErpService::class);

    $service->kirim($pertama);
    $service->kirim($kedua);

    expect(array_count_values(panggilanOdoo())['account.journal/search_read'])->toBe(1)
        ->and(SinkronisasiErp::query()->menunggu()->count())->toBe(0);
});

it('mencatat log berisi referensi saja saat berhasil dan referensi serta pesan saat gagal', function () {
    Log::spy();
    fakeOdooPembayaran(['account.journal/search_read' => Http::response([])]);
    $baris = sinkronPembayaranUji();

    kirimKeOdoo($baris);

    Log::shouldHaveReceived('warning')->once()->with('Sinkronisasi ERP gagal.', [
        'referensi_unik' => $baris->referensi_unik,
        'pesan_error' => 'Jurnal bank tidak ditemukan di Odoo.',
    ]);
    Log::shouldNotHaveReceived('info');
});

it('mencatat log info berisi referensi dan ID faktur saat berhasil', function () {
    Log::spy();
    fakeOdooPembayaran();
    $baris = sinkronPembayaranUji();

    kirimKeOdoo($baris);

    Log::shouldHaveReceived('info')->once()->with('Sinkronisasi ERP terkirim.', [
        'referensi_unik' => $baris->referensi_unik,
        'odoo_record_id' => 41,
    ]);
    Log::shouldNotHaveReceived('warning');
});
