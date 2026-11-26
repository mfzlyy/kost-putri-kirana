<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTiket;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    // Job tidak dijalankan: di sini service dipanggil langsung.
    Queue::fake();
    $this->kunci = konfigurasiOdooUji();
});

afterEach(fn () => Carbon::setTestNow());

function barisRawat(?TiketKeluhan $tiket = null): SinkronisasiErp
{
    return barisTiket($tiket ?? tiketErpUji(), JenisTransaksiErp::Pemeliharaan);
}

it('F-24: alur baru membuat permintaan, memindahkannya ke tahap selesai, lalu menandai terkirim', function () {
    fakeOdooPemeliharaan();

    $baris = kirimKeOdoo(barisRawat());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(5)
        ->and($baris->pesan_error)->toBeNull()
        ->and($baris->waktu_kirim->equalTo(now()))->toBeTrue()
        ->and(panggilanOdoo())->toBe([
            'maintenance.request/search_read',
            'maintenance.request/create',
            'maintenance.stage/search_read',
            'maintenance.request/write',
            'maintenance.request/read',
        ]);
});

it('F-24: payload mengikuti dokumen teknis', function () {
    fakeOdooPemeliharaan();

    kirimKeOdoo(barisRawat());

    expect(bodyOdoo('maintenance.request/search_read'))->toBe([
        'domain' => [['name', '=like', 'KPK-TKT-0001-M %']],
        'fields' => ['id', 'name', 'equipment_id', 'stage_id', 'request_date'],
        'limit' => 2,
    ])
        ->and(bodyOdoo('maintenance.request/create'))->toBe([
            'vals_list' => [[
                'name' => 'KPK-TKT-0001-M Tidak dingin',
                'equipment_id' => 42,
                'maintenance_type' => 'corrective',
                'request_date' => '2026-11-05',
                'description' => 'Biaya perbaikan Rp150.000. Vendor: CV Teknik Uji.',
            ]],
        ])
        ->and(bodyOdoo('maintenance.stage/search_read'))->toBe([
            'domain' => [['done', '=', true]],
            'fields' => ['id'],
            'limit' => 1,
            'order' => 'sequence asc, id asc',
        ])
        ->and(bodyOdoo('maintenance.request/write'))->toBe(['ids' => [5], 'vals' => ['stage_id' => 3]]);
});

it('NFR-02: permintaan perawatan tidak memuat data penghuni, deskripsi keluhan, atau instruksi', function () {
    fakeOdooPemeliharaan();
    $tiket = tiketErpUji();

    expect(kirimKeOdoo(barisRawat($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim);

    pastikanTanpaDataPenghuni($tiket);
});

it('tanggal permintaan adalah tanggal selesai tiket menurut Asia/Jakarta', function () {
    fakeOdooPemeliharaan();
    Carbon::setTestNow(Carbon::parse('2026-11-05 23:30', 'Asia/Jakarta'));
    $baris = barisRawat();
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));

    kirimKeOdoo($baris);

    expect(bodyOdoo('maintenance.request/create')['vals_list'][0]['request_date'])->toBe('2026-11-05');
});

it('nama vendor di keterangan di-escape karena field keterangan Odoo berupa HTML', function () {
    fakeOdooPemeliharaan();
    $tiket = tiketErpUji();
    $tiket->vendor->update(['nama_vendor' => 'CV <b>Air</b> & Listrik']);

    kirimKeOdoo(barisRawat($tiket));

    expect(bodyOdoo('maintenance.request/create')['vals_list'][0]['description'])
        ->toBe('Biaya perbaikan Rp150.000. Vendor: CV &lt;b&gt;Air&lt;/b&gt; &amp; Listrik.');
});

// ---- Permintaan yang sudah ada ----

it('idempoten: permintaan yang sudah di tahap selesai tidak dibuat atau diubah', function () {
    fakeOdooPemeliharaan(['maintenance.request/search_read' => Http::response(permintaanOdoo(3))]);

    $baris = kirimKeOdoo(barisRawat());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and($baris->odoo_record_id)->toBe(5)
        ->and(panggilanOdoo())->toBe(['maintenance.request/search_read', 'maintenance.stage/search_read']);
});

it('permintaan di tahap done yang lain juga dianggap selesai dan tidak dipindahkan', function () {
    fakeOdooPemeliharaan([
        'maintenance.request/search_read' => Http::response(permintaanOdoo(4)),
        'maintenance.stage/read' => Http::response([['id' => 4, 'done' => true]]),
    ]);

    expect(kirimKeOdoo(barisRawat())->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(panggilanOdoo())->not->toContain('maintenance.request/create', 'maintenance.request/write')
        ->and(bodyOdoo('maintenance.stage/read'))->toBe(['ids' => [4], 'fields' => ['done']]);
});

it('permintaan yang sudah ada tetapi belum selesai hanya dipindahkan tahapnya', function () {
    fakeOdooPemeliharaan([
        'maintenance.request/search_read' => Http::response(permintaanOdoo(1)),
        'maintenance.stage/read' => Http::response([['id' => 1, 'done' => false]]),
    ]);

    expect(kirimKeOdoo(barisRawat())->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(panggilanOdoo())->not->toContain('maintenance.request/create')
        ->and(bodyOdoo('maintenance.request/write'))->toBe(['ids' => [5], 'vals' => ['stage_id' => 3]]);
});

it('KPK-TKT-0001-M tidak menemukan permintaan milik referensi lain yang mirip', function () {
    $diOdoo = ['KPK-TKT-00010-M Bocor', 'KPK-TKT-0001-MX Bocor', 'KPK-UJI-TKT-0001-M AC tidak dingin'];
    fakeOdooPemeliharaan([
        // Meniru "=like" Odoo: % berarti sembarang teks, sisanya harus sama persis dari awal.
        'maintenance.request/search_read' => function (Request $request) use ($diOdoo) {
            $awalan = Str::before($request['domain'][0][2], '%');
            $cocok = array_values(array_filter($diOdoo, fn (string $nama) => str_starts_with($nama, $awalan)));

            return Http::response(array_map(fn (string $nama) => permintaanOdoo(timpa: ['name' => $nama])[0], $cocok));
        },
    ]);

    expect(kirimKeOdoo(barisRawat())->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and(panggilanOdoo())->toContain('maintenance.request/create');
});

it('permintaan ber-referensi sama tetapi datanya berbeda ditandai gagal tanpa diubah', function (array $timpa, string $beda) {
    fakeOdooPemeliharaan(['maintenance.request/search_read' => Http::response(permintaanOdoo(1, $timpa))]);

    $baris = kirimKeOdoo(barisRawat());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe("Permintaan perawatan KPK-TKT-0001-M di Odoo tidak cocok dengan data web ({$beda}). Kemungkinan sisa data uji sebelum reset basis data. Periksa manual.")
        ->and($baris->odoo_record_id)->toBeNull()
        ->and(panggilanOdoo())->toBe(['maintenance.request/search_read']);
})->with([
    'peralatan lain' => [['equipment_id' => [7, 'Lain']], 'peralatan'],
    'tanpa peralatan' => [['equipment_id' => false], 'peralatan'],
    'tanggal lain' => [['request_date' => '2026-11-03'], 'tanggal'],
    'keduanya' => [['equipment_id' => [7, 'Lain'], 'request_date' => false], 'peralatan, tanggal'],
]);

it('permintaan ganda ditandai gagal', function () {
    fakeOdooPemeliharaan(['maintenance.request/search_read' => Http::response([
        ...permintaanOdoo(), ...permintaanOdoo(timpa: ['id' => 6]),
    ])]);

    $baris = kirimKeOdoo(barisRawat());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Ada lebih dari satu permintaan perawatan KPK-TKT-0001-M di Odoo. Periksa manual.')
        ->and(panggilanOdoo())->toBe(['maintenance.request/search_read']);
});

// ---- Kegagalan ----

it('gagal dengan saran perintah bila fasilitas tidak lagi tertaut, tanpa memanggil Odoo', function () {
    $tiket = tiketErpUji();
    $baris = barisRawat($tiket);
    $tiket->fasilitas->update(['odoo_equipment_id' => null]);

    $hasil = kirimKeOdoo($baris);

    expect($hasil->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($hasil->pesan_error)->toBe('Fasilitas belum ditautkan ke peralatan Odoo. Jalankan php artisan erp:cocokkan-peralatan.');
    Http::assertNothingSent();
});

it('gagal tanpa memanggil Odoo bila tiket tidak selesai', function () {
    $tiket = tiketErpUji();
    $baris = barisRawat($tiket);
    DB::table('tiket_keluhan')->where('id', $tiket->id)->update(['status_tiket' => 'dikerjakan']);

    expect(kirimKeOdoo($baris)->pesan_error)->toBe('Tiket belum berstatus selesai.');
    Http::assertNothingSent();
});

it('peralatan yang sudah dihapus di Odoo menghasilkan pesan yang menyarankan pencocokan ulang', function () {
    fakeOdooPemeliharaan([
        'maintenance.request/create' => Http::response([
            'name' => 'odoo.exceptions.MissingError', 'message' => 'Record does not exist or has been deleted.',
        ], 404),
        'maintenance.equipment/search_read' => Http::response([]),
    ]);

    $baris = kirimKeOdoo(barisRawat());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Peralatan Odoo #42 untuk fasilitas ini tidak ditemukan. Jalankan php artisan erp:cocokkan-peralatan.')
        ->and(bodyOdoo('maintenance.equipment/search_read'))->toBe([
            'domain' => [['id', '=', 42]], 'fields' => ['id'], 'limit' => 1, 'context' => ['active_test' => false],
        ]);
});

it('penolakan lain saat membuat permintaan tetap dilaporkan apa adanya', function () {
    fakeOdooPemeliharaan([
        'maintenance.request/create' => Http::response([
            'name' => 'odoo.exceptions.ValidationError', 'message' => 'Tim perawatan wajib diisi.',
        ], 422),
        'maintenance.equipment/search_read' => Http::response([['id' => 42]]),
    ]);

    expect(kirimKeOdoo(barisRawat())->pesan_error)
        ->toBe('Odoo menolak: odoo.exceptions.ValidationError: Tim perawatan wajib diisi.');
});

it('gagal bila Odoo tidak punya tahap selesai', function () {
    fakeOdooPemeliharaan(['maintenance.stage/search_read' => Http::response([])]);

    $baris = kirimKeOdoo(barisRawat());

    expect($baris->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($baris->pesan_error)->toBe('Tahap perawatan selesai tidak ditemukan di Odoo.')
        ->and(panggilanOdoo())->not->toContain('maintenance.request/write');
});

it('gagal bila permintaan belum di tahap selesai setelah dipindahkan', function () {
    fakeOdooPemeliharaan([
        'maintenance.request/read' => Http::response([['id' => 5, 'stage_id' => [1, 'New Request']]]),
        'maintenance.stage/read' => Http::response([['id' => 1, 'done' => false]]),
    ]);

    expect(kirimKeOdoo(barisRawat())->pesan_error)
        ->toBe('Permintaan perawatan KPK-TKT-0001-M sudah dibuat tetapi belum di tahap selesai di Odoo.');
});

it('Odoo mati menandai gagal tanpa membatalkan status selesai di web', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect')]);
    $tiket = tiketErpUji();

    expect(kirimKeOdoo(barisRawat($tiket))->pesan_error)
        ->toBe('Odoo tidak dapat dihubungi. Coba kirim ulang setelah Odoo menyala.')
        ->and($tiket->fresh()->status_tiket)->toBe(StatusTiket::Selesai);
});

// ---- Baris B dan M independen ----

it('kegagalan baris pemeliharaan tidak mengubah baris biaya perbaikan tiket yang sama', function () {
    $tiket = tiketErpUji();
    fakeOdooBiaya();
    fakeOdooPemeliharaan(['maintenance.stage/search_read' => Http::response([])]);
    $biaya = kirimKeOdoo(barisTiket($tiket, JenisTransaksiErp::BiayaPerbaikan));
    $sebelum = (array) DB::table('sinkronisasi_erp')->find($biaya->id);

    expect(kirimKeOdoo(barisRawat($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and($biaya->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim)
        ->and((array) DB::table('sinkronisasi_erp')->find($biaya->id))->toBe($sebelum);
});

it('kegagalan baris biaya perbaikan tidak menghalangi baris pemeliharaan tiket yang sama', function () {
    $tiket = tiketErpUji();
    fakeOdooBiaya(['account.move/create' => Http::response('Bad Gateway', 502)]);
    fakeOdooPemeliharaan();

    expect(kirimKeOdoo(barisTiket($tiket, JenisTransaksiErp::BiayaPerbaikan))->status_sinkronisasi)->toBe(StatusSinkronisasi::Gagal)
        ->and(kirimKeOdoo(barisRawat($tiket))->status_sinkronisasi)->toBe(StatusSinkronisasi::Terkirim);
});
