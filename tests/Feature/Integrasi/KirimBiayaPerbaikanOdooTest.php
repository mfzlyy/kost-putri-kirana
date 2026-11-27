<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTiket;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use App\Models\Vendor;
use App\Services\Odoo\IntegrasiErpService;
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

function barisBiaya(?TiketKeluhan $tiket = null): SinkronisasiErp
{
    return barisTiket($tiket ?? tiketErpUji(), JenisTransaksiErp::BiayaPerbaikan);
}

const ALUR_TAGIHAN_BARU = [
    'account.move/search_read',
    'account.move/create',
    'account.move/action_post',
    'account.move/read',
];

it('F-23: alur baru membuat partner vendor, menyimpannya, membuat dan memposting tagihan, lalu menandai terkirim', function () {
    fakeOdooBiaya();
    $tiket = tiketErpUji();

    $baris = kirimKeOdoo(barisBiaya($tiket));

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(61)
        ->and($baris->pesan_error)->toBeNull()
        ->and($baris->waktu_kirim->equalTo(now()))->toBeTrue()
        ->and($tiket->vendor->fresh()->odoo_partner_id)->toBe(8)
        ->and(panggilanOdoo())->toBe(['res.partner/search_read', 'res.partner/create', ...ALUR_TAGIHAN_BARU]);
});

it('F-23: payload partner, pencarian, dan tagihan vendor mengikuti dokumen teknis; tagihan tidak dibayar', function () {
    fakeOdooBiaya();
    $tiket = tiketErpUji();
    $vendorId = $tiket->vendor_id;

    kirimKeOdoo(barisBiaya($tiket));

    expect(bodyOdoo('res.partner/search_read'))->toBe([
        'domain' => [['ref', '=', "VDR-{$vendorId}"]],
        'fields' => ['id'],
        'limit' => 2,
    ])
        ->and(bodyOdoo('res.partner/create'))->toBe([
            'vals_list' => [['ref' => "VDR-{$vendorId}", 'name' => 'CV Teknik Uji', 'supplier_rank' => 1]],
        ])
        ->and(bodyOdoo('account.move/search_read'))->toBe([
            'domain' => [['move_type', '=', 'in_invoice'], ['ref', '=', 'KPK-TKT-0001-B']],
            'fields' => ['id', 'state', 'amount_total', 'invoice_date', 'partner_id'],
            'limit' => 2,
        ])
        ->and(bodyOdoo('account.move/create'))->toBe([
            'vals_list' => [[
                'move_type' => 'in_invoice',
                'partner_id' => 8,
                'ref' => 'KPK-TKT-0001-B',
                'invoice_date' => '2026-11-05',
                'invoice_line_ids' => [[0, 0, [
                    'name' => 'Perbaikan Tidak dingin - AC Kamar ZX9',
                    'quantity' => 1,
                    'price_unit' => 150000,
                    'tax_ids' => [[6, 0, []]],
                ]]],
            ]],
        ])
        ->and(bodyOdoo('account.move/action_post'))->toBe(['ids' => [61]])
        ->and(panggilanOdoo())->not->toContain('account.payment.register/create');
});

it('NFR-02: tagihan vendor tidak memuat data penghuni, deskripsi keluhan, atau instruksi', function () {
    fakeOdooBiaya();
    $tiket = tiketErpUji();

    expect(kirimKeOdoo(barisBiaya($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim);

    pastikanTanpaDataPenghuni($tiket);
});

it('tanggal tagihan adalah tanggal selesai tiket menurut Asia/Jakarta, bukan tanggal kirim atau UTC', function (string $selesai, string $tanggal) {
    fakeOdooBiaya();
    Carbon::setTestNow(Carbon::parse($selesai, 'Asia/Jakarta'));
    $baris = barisBiaya();
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));

    kirimKeOdoo($baris);

    expect(bodyOdoo('account.move/create')['vals_list'][0]['invoice_date'])->toBe($tanggal)
        ->and($baris->tiket->tanggalSelesai()->toDateString())->toBe($tanggal);
})->with([
    'pukul 23.30 WIB' => ['2026-11-05 23:30', '2026-11-05'],
    // Masih 5 November di UTC.
    'pukul 00.30 WIB' => ['2026-11-06 00:30', '2026-11-06'],
]);

it('tanggal selesai dikonversi dari zona lain, dan null bila tiket belum pernah selesai', function () {
    $tiket = tiketErpUji();
    $tiket->riwayatStatus()->where('status_sesudah', 'selesai')->update(['waktu_perubahan' => '2026-11-05 17:30:00+00']);

    expect($tiket->fresh()->tanggalSelesai()->toDateString())->toBe('2026-11-06')
        ->and(TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0009'])->tanggalSelesai())->toBeNull();
});

it('baris tagihan area bersama menyebut Area Bersama', function () {
    fakeOdooBiaya();
    $tiket = tiketErpUji();
    $tiket->fasilitas->update(['nama_fasilitas' => 'Pompa air', 'kamar_id' => null, 'lokasi' => 'Area bersama']);

    kirimKeOdoo(barisBiaya($tiket));

    expect(bodyOdoo('account.move/create')['vals_list'][0]['invoice_line_ids'][0][2]['name'])
        ->toBe('Perbaikan Tidak dingin - Pompa air Area Bersama');
});

// ---- Partner vendor ----

it('ID partner tersimpan yang masih sah dipakai tanpa mencari atau membuat partner', function () {
    fakeOdooBiaya();
    $tiket = tiketErpUji();
    $tiket->vendor->forceFill(['odoo_partner_id' => 8])->save();

    expect(kirimKeOdoo(barisBiaya($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(panggilanOdoo())->toBe(['res.partner/read', ...ALUR_TAGIHAN_BARU])
        ->and(bodyOdoo('res.partner/read'))->toBe(['ids' => [8], 'fields' => ['ref']]);
});

it('ID partner tersimpan yang tidak sah diabaikan: dicari lewat ref dan nilai di web diperbarui', function (array $balasanRead) {
    $tiket = tiketErpUji();
    $tiket->vendor->forceFill(['odoo_partner_id' => 99])->save();
    fakeOdooBiaya([
        'res.partner/read' => Http::response($balasanRead),
        'res.partner/search_read' => Http::response([['id' => 12]]),
    ]);

    expect(kirimKeOdoo(barisBiaya($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($tiket->vendor->fresh()->odoo_partner_id)->toBe(12)
        ->and(panggilanOdoo())->toBe(['res.partner/read', 'res.partner/search_read', ...ALUR_TAGIHAN_BARU])
        ->and(bodyOdoo('account.move/create')['vals_list'][0]['partner_id'])->toBe(12);
})->with([
    'partner sudah dihapus' => [[]],
    'partner milik ref lain' => [[['id' => 99, 'ref' => 'PNH-3']]],
    'partner tanpa ref' => [[['id' => 99, 'ref' => false]]],
]);

it('ID partner tersimpan yang tidak sah dan ref belum ada di Odoo: partner dibuat dan disimpan', function () {
    $tiket = tiketErpUji();
    $tiket->vendor->forceFill(['odoo_partner_id' => 99])->save();
    fakeOdooBiaya(['res.partner/read' => Http::response([])]);

    kirimKeOdoo(barisBiaya($tiket));

    expect($tiket->vendor->fresh()->odoo_partner_id)->toBe(8)
        ->and(panggilanOdoo())->toContain('res.partner/create');
});

it('dua partner ber-ref sama: gagal tanpa membuat partner atau tagihan', function () {
    fakeOdooBiaya(['res.partner/search_read' => Http::response([['id' => 8], ['id' => 9]])]);
    $tiket = tiketErpUji();

    $baris = kirimKeOdoo(barisBiaya($tiket));

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe("Ada lebih dari satu partner VDR-{$tiket->vendor_id} di Odoo. Periksa manual.")
        ->and($tiket->vendor->fresh()->odoo_partner_id)->toBeNull()
        ->and(panggilanOdoo())->toBe(['res.partner/search_read']);
});

it('kunci partner vendor dipegang proses lain: baris dilewati tanpa membuat partner, lalu terkirim setelah dilepas', function () {
    fakeOdooBiaya();
    Event::fake([IntegrasiErpService::PERISTIWA_DILEWATI]);
    $tiket = tiketErpUji();
    $baris = barisBiaya($tiket);
    $sebelum = (array) DB::table('sinkronisasi_erp')->find($baris->id);
    $kunci = Cache::lock("odoo-partner-vendor:{$tiket->vendor_id}", 60);
    expect($kunci->get())->toBeTrue();

    kirimKeOdoo($baris);

    Http::assertNothingSent();
    Event::assertDispatched(IntegrasiErpService::PERISTIWA_DILEWATI);
    expect((array) DB::table('sinkronisasi_erp')->find($baris->id))->toBe($sebelum);

    $kunci->release();

    expect(kirimKeOdoo($baris)->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(Cache::lock("odoo-partner-vendor:{$tiket->vendor_id}", 1)->get())->toBeTrue();
});

it('dua kiriman vendor yang sama bersamaan: hanya satu yang membuat partner, yang lain tetap menunggu', function () {
    $vendor = Vendor::factory()->create();
    $pertama = barisBiaya(tiketErpUji(nomor: 'KLH-0001', vendor: $vendor));
    $kedua = barisBiaya(tiketErpUji(nomor: 'KLH-0002', vendor: $vendor));
    $keadaanKedua = null;

    fakeOdooBiaya([
        'res.partner/search_read' => function () use ($kedua, &$keadaanKedua) {
            // Kiriman kedua datang selagi kunci partner dipegang kiriman pertama.
            $keadaanKedua = kirimKeOdoo($kedua)->status_sinkronisasi;

            return Http::response([]);
        },
    ]);

    expect(kirimKeOdoo($pertama)->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($keadaanKedua)->toBe(StatusSinkronisasi::Menunggu)
        ->and(array_count_values(panggilanOdoo())['res.partner/create'])->toBe(1)
        ->and(array_count_values(panggilanOdoo())['account.move/create'])->toBe(1);
});

it('kunci partner dilepas juga saat pencarian partner gagal', function () {
    fakeOdooBiaya(['res.partner/search_read' => Http::response('Bad Gateway', 502)]);
    $tiket = tiketErpUji();

    expect(kirimKeOdoo(barisBiaya($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and(Cache::lock("odoo-partner-vendor:{$tiket->vendor_id}", 1)->get())->toBeTrue();
});

// ---- Tagihan yang sudah ada ----

it('idempoten: tagihan yang cocok dan sudah posted tidak dibuat atau diposting lagi', function () {
    fakeOdooBiaya(['account.move/search_read' => Http::response(tagihanVendorOdoo())]);

    $baris = kirimKeOdoo(barisBiaya());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(61)
        ->and(panggilanOdoo())->toBe([
            'res.partner/search_read', 'res.partner/create', 'account.move/search_read', 'res.partner/read',
        ]);
});

it('tagihan draft yang cocok hanya diposting', function () {
    fakeOdooBiaya(['account.move/search_read' => Http::response(tagihanVendorOdoo('draft'))]);

    expect(kirimKeOdoo(barisBiaya())->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(panggilanOdoo())->not->toContain('account.move/create')
        ->and(bodyOdoo('account.move/action_post'))->toBe(['ids' => [61]]);
});

it('tagihan ber-referensi sama tetapi datanya berbeda ditandai gagal tanpa diposting', function (array $timpa, ?string $refPartner, string $beda, string $state) {
    $baris = barisBiaya();
    $ref = $refPartner ?? "VDR-{$baris->tiket->vendor_id}";
    // ID partner tersimpan, supaya res.partner/read hanya dipakai untuk partner pada tagihan.
    $baris->tiket->vendor->forceFill(['odoo_partner_id' => 8])->save();
    fakeOdooBiaya([
        'account.move/search_read' => Http::response(tagihanVendorOdoo($state, $timpa)),
        'res.partner/read' => fn ($request) => Http::response([[
            'id' => $request['ids'][0],
            'ref' => $request['ids'][0] === 8 ? "VDR-{$baris->tiket->vendor_id}" : $ref,
        ]]),
    ]);

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe("Tagihan vendor KPK-TKT-0001-B di Odoo tidak cocok dengan data web ({$beda}). Periksa manual di Odoo.")
        ->and($hasil->odoo_record_id)->toBeNull()
        ->and(array_values(array_diff(panggilanOdoo(), ['res.partner/read', 'account.move/search_read'])))->toBe([]);
})->with([
    'nominal berbeda' => [['amount_total' => 175000.0], null, 'nominal'],
    'tanggal berbeda' => [['invoice_date' => '2026-11-04'], null, 'tanggal'],
    'partner vendor lain' => [['partner_id' => [77, 'Lain']], 'VDR-999999', 'vendor'],
    'tanpa partner' => [['partner_id' => false], null, 'vendor'],
    'semuanya berbeda' => [['partner_id' => [77, 'Lain'], 'invoice_date' => false, 'amount_total' => 0.0], 'PNH-1', 'vendor, tanggal, nominal'],
])->with(['draft', 'posted']);

it('tagihan ganda atau dibatalkan ditandai gagal tanpa membuat atau memposting', function (array $daftar, string $pesan) {
    fakeOdooBiaya(['account.move/search_read' => Http::response($daftar)]);

    $baris = kirimKeOdoo(barisBiaya());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe($pesan)
        ->and(panggilanOdoo())->not->toContain('account.move/create', 'account.move/action_post');
})->with([
    'ganda' => [
        fn () => [...tagihanVendorOdoo(), ...tagihanVendorOdoo('draft', ['id' => 62])],
        'Ada lebih dari satu tagihan vendor KPK-TKT-0001-B di Odoo. Periksa manual.',
    ],
    'dibatalkan' => [fn () => tagihanVendorOdoo('cancel'), 'Tagihan vendor KPK-TKT-0001-B dibatalkan di Odoo. Periksa manual.'],
]);

it('gagal bila tagihan belum posted setelah action_post', function () {
    fakeOdooBiaya(['account.move/read' => Http::response([['id' => 61, 'state' => 'draft']])]);

    $baris = kirimKeOdoo(barisBiaya());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Tagihan vendor KPK-TKT-0001-B sudah dibuat tetapi belum posted di Odoo.');
});

it('biaya 0 tetap dikirim sebagai tagihan vendor bernilai 0 yang diposting', function () {
    fakeOdooBiaya();

    $baris = kirimKeOdoo(barisBiaya(tiketErpUji(biaya: 0)));

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(61)
        ->and(bodyOdoo('account.move/create')['vals_list'][0]['invoice_line_ids'][0][2]['price_unit'])->toBe(0)
        ->and(panggilanOdoo())->toContain('account.move/action_post');
});

// ---- Data web tidak lengkap ----

it('gagal tanpa memanggil Odoo bila tiket tidak selesai, tanpa riwayat selesai, atau tanpa vendor', function (Closure $rusak, string $pesan) {
    $tiket = tiketErpUji();
    $baris = barisBiaya($tiket);
    $rusak($tiket);

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe($pesan);
    Http::assertNothingSent();
})->with([
    // Parameter bertipe Closure, jadi Pest meneruskan closure ini apa adanya (tidak dijalankan sebagai dataset malas).
    'tiket tidak selesai' => [
        fn (TiketKeluhan $tiket) => DB::table('tiket_keluhan')->where('id', $tiket->id)->update(['status_tiket' => 'dikerjakan']),
        'Tiket belum berstatus selesai.',
    ],
    'tanpa riwayat selesai' => [
        fn (TiketKeluhan $tiket) => $tiket->riwayatStatus()->delete(),
        'Tiket belum berstatus selesai.',
    ],
    'tanpa vendor' => [
        fn (TiketKeluhan $tiket) => DB::table('tiket_keluhan')->where('id', $tiket->id)->update(['vendor_id' => null]),
        'Tiket tidak punya vendor.',
    ],
]);

it('F-25: Odoo mati menandai gagal tanpa membatalkan status selesai di web', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect')]);
    $tiket = tiketErpUji();

    $baris = kirimKeOdoo(barisBiaya($tiket));

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala.')
        ->and($tiket->fresh()->status_tiket)->toBe(StatusTiket::Selesai)
        ->and((int) $tiket->fresh()->biaya_perbaikan)->toBe(150000);
});

it('baris gagal yang dikirim ulang melanjutkan tagihan draft tanpa membuat tagihan kedua', function () {
    $baris = barisBiaya();
    $dibuat = false;
    fakeOdooBiaya([
        'account.move/search_read' => function () use (&$dibuat) {
            return Http::response($dibuat ? tagihanVendorOdoo('draft') : []);
        },
        'account.move/create' => function () use (&$dibuat) {
            $dibuat = true;

            return Http::response([61]);
        },
        'account.move/action_post' => Http::sequence()->push('Bad Gateway', 502)->push('false'),
    ]);

    expect(kirimKeOdoo($baris)->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal);

    $ulang = kirimKeOdoo($baris);

    expect($ulang->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($ulang->pesan_error)->toBeNull()
        ->and(array_count_values(panggilanOdoo())['account.move/create'])->toBe(1);
});

it('log keberhasilan hanya memuat referensi dan ID catatan', function () {
    Log::spy();
    fakeOdooBiaya();

    kirimKeOdoo(barisBiaya());

    Log::shouldHaveReceived('info')->once()->with('Sinkronisasi ERP terkirim.', [
        'referensi_unik' => 'KPK-TKT-0001-B',
        'odoo_record_id' => 61,
    ]);
});
