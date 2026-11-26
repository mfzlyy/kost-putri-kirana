<?php

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Exceptions\PembayaranSudahDiputuskan;
use App\Jobs\KirimSinkronisasiErp;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    Http::preventStrayRequests();
    Queue::fake();
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function komponenVerifikasi(?User $pengelola = null)
{
    return Livewire::actingAs($pengelola ?? User::factory()->pengelola()->create())
        ->test('pages::verifikasi-pembayaran.index');
}

it('F-13 butir 3 dan 5: terima membuat pembayaran diterima, tagihan lunas, dan satu antrean ERP', function () {
    $pengelola = User::factory()->pengelola()->create();
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    $p->terima($pengelola);

    $p = Pembayaran::query()->findOrFail($p->id);
    expect($p->status_verifikasi)->toBe(StatusVerifikasi::Diterima)
        ->and($p->diverifikasi_oleh)->toBe($pengelola->id)
        ->and($p->diverifikasi_pada->equalTo(now()))->toBeTrue()
        ->and($p->alasan_penolakan)->toBeNull()
        ->and($p->tagihan->status_tagihan)->toBe(StatusTagihan::Lunas);

    $sinkron = SinkronisasiErp::query()->sole();
    expect($sinkron->jenis_transaksi)->toBe(JenisTransaksiErp::Pembayaran)
        ->and($sinkron->pembayaran_id)->toBe($p->id)
        ->and($sinkron->tiket_id)->toBeNull()
        ->and($sinkron->referensi_unik)->toBe(sprintf('KPK-PAY-%06d', $p->id))
        ->and($sinkron->status_sinkronisasi)->toBe(StatusSinkronisasi::Menunggu)
        ->and($sinkron->odoo_record_id)->toBeNull()
        ->and($sinkron->waktu_kirim)->toBeNull();

    Queue::assertPushed(KirimSinkronisasiErp::class, 1);
    Http::assertNothingSent();
});

it('referensi ERP berformat KPK-PAY- diikuti id pembayaran 6 digit berawalan nol', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    DB::table('pembayaran')->where('id', $p->id)->update(['id' => 123]);

    Pembayaran::query()->findOrFail(123)->terima(User::factory()->pengelola()->create());

    expect(SinkronisasiErp::query()->sole()->referensi_unik)->toBe('KPK-PAY-000123');
});

it('F-13 butir 4: tolak menyimpan alasan, mengembalikan tagihan ke belum dibayar, dan berkas tetap ada', function () {
    $pengelola = User::factory()->pengelola()->create();
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    $p->tolak($pengelola, '  Nominal transfer kurang Rp100.000.  ');

    $p = Pembayaran::query()->findOrFail($p->id);
    expect($p->status_verifikasi)->toBe(StatusVerifikasi::Ditolak)
        ->and($p->alasan_penolakan)->toBe('Nominal transfer kurang Rp100.000.')
        ->and($p->diverifikasi_oleh)->toBe($pengelola->id)
        ->and($p->diverifikasi_pada->equalTo(now()))->toBeTrue()
        ->and($p->tagihan->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and(SinkronisasiErp::query()->count())->toBe(0);

    Storage::disk('local')->assertExists($p->bukti_bayar_path);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('tolak dengan alasan kosong ditolak di model', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    expect(fn () => $p->tolak(User::factory()->pengelola()->create(), "   \n "))
        ->toThrow(InvalidArgumentException::class);

    expect($p->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Menunggu);
});

it('F-13 butir 4: setelah ditolak, penghuni bisa mengunggah bukti baru', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $p->tolak(User::factory()->pengelola()->create(), 'Buram, mohon foto ulang.');
    $tagihan = $p->tagihan->fresh();

    $baru = $tagihan->unggahBukti(UploadedFile::fake()->createWithContent('baru.jpg', isiBerkasContoh('jpg')), now());

    expect($baru->status_verifikasi)->toBe(StatusVerifikasi::Menunggu)
        ->and($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi)
        ->and(Pembayaran::query()->where('tagihan_id', $tagihan->id)->count())->toBe(2);
});

it('memutuskan pembayaran yang sudah diputuskan melempar PembayaranSudahDiputuskan tanpa mengubah data', function (string $awal, string $kedua) {
    $pengelola = User::factory()->pengelola()->create();
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    $awal === 'terima' ? $p->terima($pengelola) : $p->tolak($pengelola, 'Bukti tidak terbaca.');

    $basi = Pembayaran::query()->findOrFail($p->id);
    $diverifikasiPada = $basi->diverifikasi_pada;
    $statusTagihan = $basi->tagihan->fresh()->status_tagihan;

    // Model basi: objek $p di memori masih menganggap statusnya menunggu.
    $lain = User::factory()->pengelola()->create();
    $panggil = fn () => $kedua === 'terima' ? $p->terima($lain) : $p->tolak($lain, 'Alasan lain lagi.');

    expect($panggil)->toThrow(PembayaranSudahDiputuskan::class, 'Bukti ini sudah diputuskan. Daftar sudah diperbarui.');

    $sesudah = Pembayaran::query()->findOrFail($p->id);
    expect($sesudah->diverifikasi_oleh)->toBe($pengelola->id)
        ->and($sesudah->diverifikasi_pada->equalTo($diverifikasiPada))->toBeTrue()
        ->and($sesudah->tagihan->status_tagihan)->toBe($statusTagihan)
        ->and(SinkronisasiErp::query()->count())->toBe($awal === 'terima' ? 1 : 0);
})->with([
    'terima lalu terima' => ['terima', 'terima'],
    'terima lalu tolak' => ['terima', 'tolak'],
    'tolak lalu terima' => ['tolak', 'terima'],
    'tolak lalu tolak' => ['tolak', 'tolak'],
]);

it('permintaan bersamaan: indeks uq_pembayaran_diterima diubah menjadi PembayaranSudahDiputuskan, semua dibatalkan', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    // Keadaan balapan: pembayaran lain untuk tagihan yang sama sudah diterima lebih dulu.
    Pembayaran::factory()->diterima()->create(['tagihan_id' => $p->tagihan_id]);

    expect(fn () => $p->terima(User::factory()->pengelola()->create()))
        ->toThrow(PembayaranSudahDiputuskan::class);

    expect($p->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Menunggu)
        ->and($p->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi)
        ->and(SinkronisasiErp::query()->count())->toBe(0);
});

it('permintaan bersamaan: indeks uq_sinkronisasi_pembayaran diubah menjadi PembayaranSudahDiputuskan, semua dibatalkan', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    SinkronisasiErp::factory()->untukPembayaran($p)->create();

    expect(fn () => $p->terima(User::factory()->pengelola()->create()))
        ->toThrow(PembayaranSudahDiputuskan::class);

    expect($p->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Menunggu)
        ->and($p->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi)
        ->and(SinkronisasiErp::query()->count())->toBe(1);
});

it('tagihan yang telat lalu diterima tidak lagi termasuk tunggakan', function () {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari', '2026-11-03 10:00', periode: '2026-10-01', jatuhTempo: '2026-10-10', nominal: 1400000);

    expect(Tagihan::query()->tunggakan(now())->whereKey($p->tagihan_id)->exists())->toBeTrue();

    $p->terima(User::factory()->pengelola()->create());

    expect(Tagihan::query()->tunggakan(now())->whereKey($p->tagihan_id)->exists())->toBeFalse()
        ->and($p->tagihan->fresh()->isTunggakan())->toBeFalse();
});

it('tagihan yang ditolak dan sudah lewat jatuh tempo kembali dihitung tunggakan', function () {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari', '2026-10-10 10:00', periode: '2026-10-01', jatuhTempo: '2026-10-10', nominal: 1400000);

    expect(Tagihan::query()->tunggakan(now())->whereKey($p->tagihan_id)->exists())->toBeFalse();

    $p->tolak(User::factory()->pengelola()->create(), 'Nominal tidak sesuai.');

    expect(Tagihan::query()->tunggakan(now())->whereKey($p->tagihan_id)->exists())->toBeTrue();
});

// ---- Lewat halaman ----

it('F-13: halaman Terima mengubah data, menampilkan toast, dan memilih item berikutnya', function () {
    $pertama = siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    $kedua = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');
    $pengelola = User::factory()->pengelola()->create();

    $komponen = komponenVerifikasi($pengelola)
        ->call('pilih', $pertama->id)
        ->call('terima', $pertama->id)
        ->assertDispatched('toast-show')
        ->assertDispatched('antrean-verifikasi', jumlah: 1)
        ->assertSet('bukti', '')
        ->assertDontSee('bukti bayar menunggu')
        ->assertSee('Kamar 02, Tagihan November');

    expect($komponen->instance()->panel->id)->toBe($kedua->id)
        ->and($pertama->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Diterima)
        ->and($pertama->fresh()->diverifikasi_oleh)->toBe($pengelola->id)
        ->and($pertama->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::Lunas)
        ->and(SinkronisasiErp::query()->sole()->pembayaran_id)->toBe($pertama->id);
});

it('F-13: toast sukses Terima menyebut kamar dan bulan', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    komponenVerifikasi()
        ->call('terima', $p->id)
        ->assertDispatched('toast-show', fn ($nama, $data) => str_contains(json_encode($data), 'Pembayaran Kamar 02 November diterima.'));
});

it('F-13: halaman Tolak wajib alasan 5 sampai 500 karakter setelah trim', function (string $alasan, ?string $pesan) {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    $komponen = komponenVerifikasi()->set('alasan', $alasan)->call('tolak', $p->id);

    if ($pesan !== null) {
        $komponen->assertHasErrors('alasan')->assertSee($pesan);
        expect($p->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Menunggu)
            ->and($p->tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi);

        return;
    }

    $komponen->assertHasNoErrors();
    expect($p->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Ditolak);
})->with([
    'kosong' => ['', 'Isi alasan penolakan.'],
    'spasi saja' => ['      ', 'Isi alasan penolakan.'],
    '3 karakter' => ['abc', 'Alasan paling sedikit 5 karakter.'],
    '4 karakter setelah trim' => ['  abcd  ', 'Alasan paling sedikit 5 karakter.'],
    '501 karakter' => [str_repeat('a', 501), 'Alasan paling banyak 500 karakter.'],
    '500 karakter' => [str_repeat('a', 500), null],
    '5 karakter' => ['abcde', null],
]);

it('F-13: Tolak valid menyimpan alasan, toast, berkas tetap, dan tidak ada antrean ERP', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');
    $path = $p->bukti_bayar_path;

    komponenVerifikasi()
        ->call('bukaTolak', $p->id)
        ->set('alasan', 'Nominal transfer kurang Rp100.000.')
        ->call('tolak', $p->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn ($nama, $data) => str_contains(json_encode($data), 'Bukti Kamar 02 November ditolak.'))
        ->assertSee('Tidak ada bukti yang menunggu');

    $p->refresh();
    expect($p->alasan_penolakan)->toBe('Nominal transfer kurang Rp100.000.')
        ->and($p->tagihan->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and(SinkronisasiErp::query()->count())->toBe(0);

    Storage::disk('local')->assertExists($path);
});

it('F-13: bukaTolak mengosongkan alasan dan galat dari percobaan sebelumnya', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    komponenVerifikasi()
        ->set('alasan', 'ab')
        ->call('tolak', $p->id)
        ->assertHasErrors('alasan')
        ->call('bukaTolak', $p->id)
        ->assertSet('alasan', '')
        ->assertHasNoErrors();
});

it('F-13: bukti yang sudah diputuskan pihak lain memunculkan toast peringatan dan memuat ulang antrean', function () {
    $basi = siapkanBuktiMenunggu('01', 'Anisa Putri', '2026-11-02 08:00');
    $lain = siapkanBuktiMenunggu('02', 'Rina Maharani', '2026-11-03 08:00');

    $komponen = komponenVerifikasi()->call('pilih', $basi->id);

    // Pengelola lain menerima bukti yang sama lebih dulu.
    $basi->terima(User::factory()->pengelola()->create());

    $komponen->call('terima', $basi->id)
        ->assertDispatched('toast-show', fn ($nama, $data) => str_contains(json_encode($data), 'warning') && str_contains(json_encode($data), 'Bukti ini sudah diputuskan. Daftar sudah diperbarui.'))
        ->assertSet('bukti', '')
        ->assertDontSee('bukti bayar menunggu');

    expect(SinkronisasiErp::query()->count())->toBe(1)
        ->and($komponen->instance()->panel->id)->toBe($lain->id);

    // Id yang tidak dikenal diperlakukan sama.
    $komponen->set('alasan', 'Alasan yang cukup')->call('tolak', 987654)->assertDispatched('toast-show');
    expect($lain->fresh()->status_verifikasi)->toBe(StatusVerifikasi::Menunggu);
});

it('F-13: halaman tidak menulis kolom status langsung dan tidak memanggil Odoo; pengiriman hanya lewat antrean', function () {
    $p = siapkanBuktiMenunggu('02', 'Rina Maharani');

    komponenVerifikasi()->call('terima', $p->id);

    Http::assertNothingSent();
    Queue::assertPushed(KirimSinkronisasiErp::class, 1);
    expect(SinkronisasiErp::query()->menunggu()->count())->toBe(1);
});
