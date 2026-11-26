<?php

use App\Enums\StatusTiket;
use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Penghuni;
use App\Models\RiwayatStatusTiket;
use App\Models\SinkronisasiErp;
use App\Models\TiketKeluhan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    // Pengiriman ke Odoo diuji di tests/Feature/Integrasi; di sini antreannya saja.
    Queue::fake();
});

afterEach(fn () => Carbon::setTestNow());

function halamanPekerjaan(User $user)
{
    return Livewire::actingAs($user)->test('pages::permintaan-kerja.index');
}

function halamanDetailPekerjaan(User $user, TiketKeluhan $tiket)
{
    return Livewire::actingAs($user)->test('pages::permintaan-kerja.detail', ['tiket' => $tiket]);
}

/**
 * Pencocok toast Flux: teks (dan varian bila diberikan).
 */
function toastPekerjaan(string $teks, ?string $varian = null): Closure
{
    return fn (string $nama, array $parameter): bool => ($parameter['slots']['text'] ?? null) === $teks
        && ($varian === null || ($parameter['dataset']['variant'] ?? null) === $varian);
}

/**
 * Tiket milik $vendor pada $status, dengan riwayat lengkap sampai status itu. Fasilitas baru di kamar baru
 * bila tidak diberikan; $penghuni dibuat baru bila tidak diberikan.
 */
function tiketKerjaUji(
    Vendor $vendor,
    string $nomor,
    StatusTiket $status,
    string $ditugaskan = '2026-11-01 08:00',
    array $atribut = [],
    ?Fasilitas $fasilitas = null,
    ?string $selesai = '2026-11-04 10:00',
    ?Penghuni $penghuni = null,
): TiketKeluhan {
    $penghuni ??= Penghuni::factory()->create();
    $fasilitas ??= Fasilitas::factory()->create(['nama_fasilitas' => 'Lampu kamar mandi']);
    $waktuDitugaskan = Carbon::parse($ditugaskan, 'Asia/Jakarta');

    $tiket = TiketKeluhan::factory()->create(array_merge([
        'nomor_tiket' => $nomor,
        'penghuni_id' => $penghuni->id,
        'fasilitas_id' => $fasilitas->id,
        'vendor_id' => $vendor->id,
        'lokasi' => 'kamar',
        'jenis_kerusakan' => 'Lampu mati',
        'catatan_instruksi' => 'Ganti lampu',
        'status_tiket' => $status,
        'biaya_perbaikan' => $status === StatusTiket::Selesai ? 90000 : null,
        'tanggal_lapor' => $waktuDitugaskan->copy()->subDay(),
    ], $atribut));

    $riwayat = [
        [null, StatusTiket::Diajukan, $penghuni->user_id, $waktuDitugaskan->copy()->subDay()],
        [StatusTiket::Diajukan, StatusTiket::Ditugaskan, User::factory()->pengelola()->create()->id, $waktuDitugaskan],
        [StatusTiket::Ditugaskan, StatusTiket::Dikerjakan, $vendor->user_id, $waktuDitugaskan->copy()->addHour()],
        [StatusTiket::Dikerjakan, StatusTiket::Selesai, $vendor->user_id, Carbon::parse($selesai ?? $ditugaskan, 'Asia/Jakarta')],
    ];

    foreach ($riwayat as [$sebelum, $sesudah, $oleh, $waktu]) {
        $urutan = array_search($sesudah, StatusTiket::cases(), true);

        if ($urutan > array_search($status, StatusTiket::cases(), true)) {
            continue;
        }

        RiwayatStatusTiket::factory()->perubahan($sebelum, $sesudah)->create([
            'tiket_id' => $tiket->id,
            'diubah_oleh' => $oleh,
            'waktu_perubahan' => $waktu,
        ]);
    }

    return $tiket;
}

it('F-19: vendor hanya melihat tiket miliknya; tiket vendor lain tidak tampil dan detailnya 403', function () {
    $vendor = Vendor::factory()->create();
    $lain = Vendor::factory()->create();
    $milik = tiketKerjaUji($vendor, 'KLH-0001', StatusTiket::Ditugaskan);
    $bukanMilik = tiketKerjaUji($lain, 'KLH-0002', StatusTiket::Ditugaskan);

    halamanPekerjaan($vendor->user)
        ->assertSee('KLH-0001')
        ->assertDontSee('KLH-0002');

    $this->actingAs($vendor->user)->get(route('permintaan-kerja.detail', $milik))->assertOk();
    $this->actingAs($vendor->user)->get(route('permintaan-kerja.detail', $bukanMilik))->assertForbidden();
});

it('F-19 butir 1: Perlu Dikerjakan urut ditugaskan terlama di atas, tanpa tiket diajukan dan tiket vendor lain', function () {
    $vendor = Vendor::factory()->create();
    tiketKerjaUji($vendor, 'KLH-0001', StatusTiket::Dikerjakan, '2026-11-02 08:00');
    tiketKerjaUji($vendor, 'KLH-0003', StatusTiket::Ditugaskan, '2026-10-30 08:00');
    tiketKerjaUji($vendor, 'KLH-0004', StatusTiket::Ditugaskan, '2026-11-04 08:00');
    tiketKerjaUji(Vendor::factory()->create(), 'KLH-0005', StatusTiket::Ditugaskan, '2026-10-01 08:00');
    TiketKeluhan::factory()->create(['nomor_tiket' => 'KLH-0002', 'status_tiket' => StatusTiket::Diajukan]);

    halamanPekerjaan($vendor->user)
        ->assertSeeInOrder(['KLH-0003', 'KLH-0001', 'KLH-0004'])
        ->assertSeeText('Perlu Dikerjakan 3')
        ->assertSeeText('Ditugaskan 30 Okt')
        ->assertDontSee('KLH-0002')
        ->assertDontSee('KLH-0005');
});

it('F-19: kartu memuat jenis kerusakan, lokasi dan fasilitas, status, serta aksi sesuai status', function () {
    $vendor = Vendor::factory()->create();
    $kamar = Kamar::factory()->create(['nomor_kamar' => '06']);
    $ditugaskan = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan, fasilitas: Fasilitas::factory()->create(['kamar_id' => $kamar->id, 'nama_fasilitas' => 'Lampu kamar mandi']));
    $dikerjakan = tiketKerjaUji($vendor, 'KLH-0011', StatusTiket::Dikerjakan, atribut: ['lokasi' => 'area_bersama'], fasilitas: Fasilitas::factory()->areaBersama()->create(['nama_fasilitas' => 'Pompa air']));

    halamanPekerjaan($vendor->user)
        ->assertSee('Lampu mati')
        ->assertSeeText('Kamar 06, Lampu kamar mandi')
        ->assertSeeText('Area Bersama, Pompa air')
        ->assertSeeText('Ditugaskan')
        ->assertSeeText('Dikerjakan')
        ->assertSeeHtml('wire:click="mulai('.$ditugaskan->id.')"')
        ->assertDontSeeHtml('wire:click="mulai('.$dikerjakan->id.')"')
        ->assertSeeHtml('href="'.route('permintaan-kerja.detail', $dikerjakan).'"');
});

it('F-19: Selesai Bulan Ini hanya tiket selesai di bulan berjalan, terbaru di atas, lengkap dengan biaya', function () {
    $vendor = Vendor::factory()->create();
    tiketKerjaUji($vendor, 'KLH-0001', StatusTiket::Selesai, '2026-10-20 08:00', ['biaya_perbaikan' => 150000], selesai: '2026-11-02 10:00');
    tiketKerjaUji($vendor, 'KLH-0002', StatusTiket::Selesai, '2026-10-20 08:00', selesai: '2026-11-04 10:00');
    tiketKerjaUji($vendor, 'KLH-0003', StatusTiket::Selesai, '2026-10-01 08:00', selesai: '2026-10-30 10:00');
    tiketKerjaUji(Vendor::factory()->create(), 'KLH-0004', StatusTiket::Selesai, selesai: '2026-11-03 10:00');

    halamanPekerjaan($vendor->user)
        ->assertSeeText('Selesai Bulan Ini')
        ->assertSeeInOrder(['KLH-0002', 'KLH-0001'])
        ->assertSeeText('Selesai 4 Nov')
        ->assertSeeText('Rp150.000')
        ->assertDontSee('KLH-0003')
        ->assertDontSee('KLH-0004');
});

it('F-19: bagian Selesai Bulan Ini tidak tampil bila kosong', function () {
    $vendor = Vendor::factory()->create();
    tiketKerjaUji($vendor, 'KLH-0001', StatusTiket::Ditugaskan);

    halamanPekerjaan($vendor->user)->assertDontSee('Selesai Bulan Ini');
});

it('F-19 butir 2: halaman daftar dan detail tidak memuat nama, telepon, email, atau nomor identitas penghuni', function () {
    $vendor = Vendor::factory()->create();
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => 'Rahasia Penghuni', 'email' => 'rahasia.penghuni@contoh.test']),
        'no_identitas_enc' => '3216011234567890',
        'no_telepon' => '081299998888',
    ]);
    $tiket = tiketKerjaUji($vendor, 'KLH-0001', StatusTiket::Dikerjakan, penghuni: $penghuni);
    tiketKerjaUji($vendor, 'KLH-0002', StatusTiket::Selesai, penghuni: $penghuni, selesai: '2026-11-03 10:00');

    $halaman = [
        $this->actingAs($vendor->user)->get(route('permintaan-kerja.index'))->assertOk()->getContent(),
        $this->actingAs($vendor->user)->get(route('permintaan-kerja.detail', $tiket))->assertOk()->getContent(),
    ];

    foreach ($halaman as $html) {
        expect($html)->not->toContain('Rahasia Penghuni')
            ->not->toContain('rahasia.penghuni@contoh.test')
            ->not->toContain('081299998888')
            ->not->toContain('0812 9999 8888')
            ->not->toContain('3216011234567890')
            ->not->toContain('3216 0112 3456 7890');
    }
});

it('F-19 butir 3: tanpa tiket tampil teks Belum ada permintaan kerja', function () {
    $vendor = Vendor::factory()->create();

    halamanPekerjaan($vendor->user)
        ->assertSeeText('Pekerjaan')
        ->assertSeeText('Belum ada permintaan kerja')
        ->assertSeeText('Perlu Dikerjakan 0');
});

it('F-19: akun vendor tanpa baris vendor melihat pesan belum dilengkapi tanpa galat', function () {
    $user = User::factory()->vendor()->create();

    halamanPekerjaan($user)
        ->assertOk()
        ->assertSeeText('Pekerjaan')
        ->assertSeeText('Data vendor Anda belum dilengkapi pengelola. Hubungi pengelola kos.');

    $this->actingAs($user)->get(route('permintaan-kerja.index'))->assertOk()->assertSee('belum dilengkapi');
});

it('F-19: Mulai di daftar mengubah tiket menjadi Dikerjakan, mencatat riwayat, dan menampilkan toast', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan);
    $riwayatAwal = $tiket->riwayatStatus()->count();

    halamanPekerjaan($vendor->user)
        ->call('mulai', $tiket->id)
        ->assertDispatched('toast-show', toastPekerjaan('KLH-0010 mulai dikerjakan.', 'success'))
        ->assertDontSeeHtml('wire:click="mulai('.$tiket->id.')"')
        ->assertSeeHtml('data-test="selesai-tiket"');

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Dikerjakan)
        ->and($tiket->riwayatStatus()->count())->toBe($riwayatAwal + 1)
        ->and($tiket->riwayatStatus()->get()->last()->diubah_oleh)->toBe($vendor->user_id);
});

it('F-19: Mulai pada tiket yang sudah berubah tidak menambah riwayat dan menampilkan toast peringatan', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan);

    $halaman = halamanPekerjaan($vendor->user);
    TiketKeluhan::find($tiket->id)->mulaiKerjakan($vendor->user);
    $riwayat = $tiket->riwayatStatus()->count();

    $halaman->call('mulai', $tiket->id)
        ->assertDispatched('toast-show', toastPekerjaan('Status tiket ini sudah berubah. Halaman sudah diperbarui.', 'warning'));

    expect($tiket->riwayatStatus()->count())->toBe($riwayat);
});

it('F-20: Mulai di halaman detail, lalu Selesai dengan konfirmasi, mengarah ke daftar dengan toast', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan);

    $detail = halamanDetailPekerjaan($vendor->user, $tiket)
        ->assertSeeHtml('data-test="mulai-tiket"')
        ->assertDontSeeHtml('data-test="form-selesaikan"')
        ->call('mulai')
        ->assertDispatched('toast-show', toastPekerjaan('KLH-0010 mulai dikerjakan.', 'success'))
        ->assertSeeHtml('data-test="form-selesaikan"')
        ->assertSeeText('Isi 0 bila tanpa biaya.');

    $detail->set('biaya', '150.000')
        ->call('periksa')
        ->assertHasNoErrors()
        ->assertDispatched('modal-show', name: 'selesaikan-tiket')
        ->assertSeeText('Selesaikan KLH-0010?')
        ->assertSeeText('Rp150.000')
        ->assertSeeText('Pekerjaan akan ditandai selesai dan biaya dicatat. Setelah ini tidak bisa diubah.');

    // Dialog hanya dibuka: belum ada yang disimpan sampai dikonfirmasi.
    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Dikerjakan);

    $detail->call('selesaikan')
        ->assertHasNoErrors()
        ->assertSessionHas('toast', 'KLH-0010 selesai. Biaya Rp150.000 tercatat.')
        ->assertRedirect(route('permintaan-kerja.index'));

    $tiket->refresh();
    expect($tiket->status_tiket)->toBe(StatusTiket::Selesai)
        ->and((int) $tiket->biaya_perbaikan)->toBe(150000)
        ->and($tiket->riwayatStatus()->get()->pluck('status_sesudah')->map->value->all())
        ->toBe(['diajukan', 'ditugaskan', 'dikerjakan', 'selesai'])
        ->and(SinkronisasiErp::query()->where('tiket_id', $tiket->id)->pluck('referensi_unik')->all())->toBe(['KPK-TKT-0010-B']);

    // Halaman daftar membaca flash dan menampilkannya sebagai toast; tiket pindah ke Selesai Bulan Ini.
    $this->actingAs($vendor->user)
        ->withSession(['toast' => 'KLH-0010 selesai. Biaya Rp150.000 tercatat.'])
        ->get(route('permintaan-kerja.index'))
        ->assertOk()
        ->assertSee('KLH-0010 selesai. Biaya Rp150.000 tercatat.', false)
        ->assertSee('Selesai Bulan Ini');
});

it('F-20 butir 2: validasi biaya menampilkan pesan masing-masing tanpa mengubah tiket', function (string $isian, string $pesan) {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Dikerjakan);

    halamanDetailPekerjaan($vendor->user, $tiket)
        ->set('biaya', $isian)
        ->call('periksa')
        ->assertHasErrors('biaya')
        ->assertSeeText($pesan)
        ->assertNotDispatched('modal-show');

    halamanDetailPekerjaan($vendor->user, $tiket)
        ->set('biaya', $isian)
        ->call('selesaikan')
        ->assertHasErrors('biaya')
        ->assertNoRedirect();

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Dikerjakan)
        ->and(SinkronisasiErp::query()->count())->toBe(0);
})->with([
    'kosong' => ['', 'Isi biaya perbaikan.'],
    'huruf' => ['abc', 'Biaya harus angka rupiah, 0 atau lebih.'],
    'negatif' => ['-5000', 'Biaya harus angka rupiah, 0 atau lebih.'],
    'desimal' => ['1.500,50', 'Biaya harus angka rupiah, 0 atau lebih.'],
    'di atas batas' => ['100.000.001', 'Biaya paling banyak Rp100.000.000.'],
]);

it('F-20: isian dengan pemisah ribuan disimpan sebagai bilangan bulat; 0 dan batas atas diterima', function (string $isian, int $tersimpan) {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Dikerjakan);

    halamanDetailPekerjaan($vendor->user, $tiket)
        ->set('biaya', $isian)
        ->call('selesaikan')
        ->assertHasNoErrors();

    expect((int) $tiket->fresh()->biaya_perbaikan)->toBe($tersimpan)
        ->and($tiket->fresh()->status_tiket)->toBe(StatusTiket::Selesai);
})->with([
    'ribuan' => ['1.250.000', 1250000],
    'tanpa pemisah' => ['1250000', 1250000],
    'nol' => ['0', 0],
    'batas atas' => ['100.000.000', 100000000],
    'dengan awalan Rp' => ['Rp 75.000', 75000],
]);

it('F-20: Selesai pada tiket yang sudah berubah menghasilkan toast peringatan tanpa antrean baru', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Dikerjakan);

    $detail = halamanDetailPekerjaan($vendor->user, $tiket)->set('biaya', '50.000');
    TiketKeluhan::find($tiket->id)->selesaikan(10000, $vendor->user);

    $detail->call('selesaikan')
        ->assertDispatched('toast-show', toastPekerjaan('Status tiket ini sudah berubah. Halaman sudah diperbarui.', 'warning'))
        ->assertNoRedirect()
        ->assertDontSeeHtml('data-test="form-selesaikan"');

    expect((int) $tiket->fresh()->biaya_perbaikan)->toBe(10000)
        ->and(SinkronisasiErp::query()->where('tiket_id', $tiket->id)->count())->toBe(1);
});

it('F-20 butir 4: tiket selesai menampilkan biaya dan tanggal selesai tanpa aksi, riwayat tanpa nama pengubah', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Selesai, '2026-11-01 08:00', ['biaya_perbaikan' => 275000], selesai: '2026-11-04 10:30');

    halamanDetailPekerjaan($vendor->user, $tiket)
        ->assertSeeText('Biaya Perbaikan')
        ->assertSeeText('Rp275.000')
        ->assertSeeText('4 November 2026')
        ->assertSeeText('Ditugaskan')
        ->assertSeeText('1 November 2026')
        ->assertSeeText('Riwayat Status')
        ->assertSeeText('1 Nov, 08.00')
        ->assertSeeText('4 Nov, 10.30')
        ->assertDontSeeHtml('data-test="mulai-tiket"')
        ->assertDontSeeHtml('data-test="form-selesaikan"')
        ->assertDontSee($vendor->user->nama);
});

it('F-19: halaman detail memuat lokasi, fasilitas, deskripsi, instruksi, dan tombol kembali beraksesibilitas', function () {
    $vendor = Vendor::factory()->create();
    $kamar = Kamar::factory()->create(['nomor_kamar' => '06']);
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan, '2026-11-01 08:00', ['deskripsi' => "Lampu mati\nsejak kemarin", 'catatan_instruksi' => 'Bawa lampu LED'], Fasilitas::factory()->create(['kamar_id' => $kamar->id, 'nama_fasilitas' => 'Lampu kamar mandi']));

    halamanDetailPekerjaan($vendor->user, $tiket)
        ->assertSeeText('KLH-0010')
        ->assertSeeText('Kamar 06')
        ->assertSeeText('Lampu kamar mandi')
        ->assertSeeText('Bawa lampu LED')
        ->assertSeeText('1 November 2026')
        ->assertSeeHtml('aria-label="Kembali ke pekerjaan"')
        ->assertSeeHtml("Lampu mati\nsejak kemarin");
});

it('F-19: deskripsi dan instruksi berisi tag HTML tampil ter-escape', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan, atribut: [
        'deskripsi' => '<script>alert(1)</script>',
        'catatan_instruksi' => '<img src=x onerror=alert(2)>',
    ]);

    $html = $this->actingAs($vendor->user)->get(route('permintaan-kerja.detail', $tiket))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(2)>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(2)&gt;');
});

it('ATB-08: vendor lain tidak bisa memulai tiket yang bukan miliknya', function () {
    $pemilik = Vendor::factory()->create();
    $lain = Vendor::factory()->create();
    $tiket = tiketKerjaUji($pemilik, 'KLH-0010', StatusTiket::Ditugaskan);
    $riwayat = $tiket->riwayatStatus()->count();

    halamanPekerjaan($lain->user)->call('mulai', $tiket->id)->assertForbidden();

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Ditugaskan)
        ->and($tiket->riwayatStatus()->count())->toBe($riwayat);
});

it('ATB-08: aksi detail ditolak 403 bila tiket sudah dialihkan ke vendor lain, tanpa perubahan', function (string $aksi) {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Dikerjakan);

    $detail = halamanDetailPekerjaan($vendor->user, $tiket)->set('biaya', '10.000');
    TiketKeluhan::query()->whereKey($tiket->id)->update(['vendor_id' => Vendor::factory()->create()->id]);

    $detail->call($aksi)->assertForbidden();

    expect($tiket->fresh()->status_tiket)->toBe(StatusTiket::Dikerjakan)
        ->and(SinkronisasiErp::query()->count())->toBe(0);
})->with(['periksa', 'selesaikan', 'mulai']);

it('ATB-08: detail tiket vendor lain ditolak saat komponen dibuat', function () {
    $tiket = tiketKerjaUji(Vendor::factory()->create(), 'KLH-0010', StatusTiket::Ditugaskan);

    halamanDetailPekerjaan(Vendor::factory()->create()->user, $tiket)->assertForbidden();
});

it('F-19: pengelola, pemilik, dan penghuni mendapat 403 di daftar dan detail', function (string $peran) {
    $tiket = tiketKerjaUji(Vendor::factory()->create(), 'KLH-0010', StatusTiket::Ditugaskan);
    $user = User::factory()->{$peran}()->create();

    $this->actingAs($user)->get(route('permintaan-kerja.index'))->assertForbidden();
    $this->actingAs($user)->get(route('permintaan-kerja.detail', $tiket))->assertForbidden();
})->with(['pengelola', 'pemilik', 'penghuni']);

it('F-19: kebijakan kerjakan menolak akun vendor nonaktif dan peran lain', function () {
    $vendor = Vendor::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan);

    expect($vendor->user->can('kerjakan', $tiket))->toBeTrue();

    $vendor->user->nonaktifkan();
    expect(User::find($vendor->user_id)->can('kerjakan', $tiket))->toBeFalse()
        ->and(User::factory()->pengelola()->create()->can('kerjakan', $tiket))->toBeFalse()
        ->and(User::factory()->vendor()->create()->can('kerjakan', $tiket))->toBeFalse();
});

it('F-20 butir 4: riwayat Dikerjakan dan Selesai tampil di Keluhan penghuni dan Keluhan kantor', function () {
    $vendor = Vendor::factory()->create();
    $penghuni = Penghuni::factory()->create();
    $tiket = tiketKerjaUji($vendor, 'KLH-0010', StatusTiket::Ditugaskan, '2026-11-04 08:00', penghuni: $penghuni);

    $tiket->mulaiKerjakan($vendor->user);
    Carbon::setTestNow(Carbon::parse('2026-11-05 11:30:00', 'Asia/Jakarta'));
    TiketKeluhan::find($tiket->id)->selesaikan(80000, $vendor->user);

    Livewire::actingAs($penghuni->user)->test('pages::keluhan-saya.index')
        ->assertSeeText('Dikerjakan')
        ->assertSeeText('5 Nov, 09.00')
        ->assertSeeText('Selesai')
        ->assertSeeText('5 Nov, 11.30');

    Livewire::actingAs(User::factory()->pengelola()->create())->test('pages::tiket-keluhan.index', ['tiket' => (string) $tiket->id])
        ->assertSeeText('Dikerjakan')
        ->assertSeeText('5 Nov, 09.00')
        ->assertSeeText('Selesai')
        ->assertSeeText('5 Nov, 11.30')
        ->assertSeeText('Rp80.000');
});

it('F-19: jumlah query tetap untuk 1 dan 10 tiket (tanpa N+1)', function () {
    $vendor = Vendor::factory()->create();

    $hitung = function () use ($vendor): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        halamanPekerjaan($vendor->user);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    tiketKerjaUji($vendor, 'KLH-0001', StatusTiket::Ditugaskan);
    tiketKerjaUji($vendor, 'KLH-0101', StatusTiket::Selesai, selesai: '2026-11-03 10:00');
    $hitung();
    $satu = $hitung();

    foreach (range(2, 10) as $n) {
        tiketKerjaUji($vendor, sprintf('KLH-%04d', $n), $n % 2 === 0 ? StatusTiket::Dikerjakan : StatusTiket::Ditugaskan, '2026-11-01 08:00');
        tiketKerjaUji($vendor, sprintf('KLH-%04d', 100 + $n), StatusTiket::Selesai, selesai: '2026-11-03 10:00');
    }

    expect($hitung())->toBe($satu);
});
