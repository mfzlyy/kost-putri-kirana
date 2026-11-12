<?php

use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Exceptions\TagihanTidakBisaDiberiBukti;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\SinkronisasiErp;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const PESAN_BERKAS_UJI = 'Berkas harus JPG, PNG, atau PDF dan paling besar 5 MB.';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Isi berkas diperiksa lewat tanda byte di awal berkas (finfo), jadi tes tidak butuh ekstensi GD.
 * Ukuran ($kb) dicapai dengan menambah byte kosong di belakang.
 */
function berkasUji(string $jenis, string $nama, int $kb = 1): UploadedFile
{
    $awal = match ($jenis) {
        'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        'gif' => base64_decode('R0lGODlhAQABAAAAACw='),
        'pdf' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
        'txt' => "ini hanya teks biasa, bukan gambar\n",
    };

    return UploadedFile::fake()->createWithContent($nama, str_pad($awal, $kb * 1024, "\0"));
}

function siapkanUnggah(StatusTagihan $status = StatusTagihan::BelumDibayar, string $nama = 'Maya Sari'): Tagihan
{
    $kontrak = buatKontrakUji('05', $nama);

    return Tagihan::factory()->create([
        'kontrak_id' => $kontrak->id,
        'periode' => '2026-11-01',
        'jatuh_tempo' => '2026-11-10',
        'nominal' => 1400000,
        'status_tagihan' => $status,
    ]);
}

function pemilikTagihan(Tagihan $tagihan): User
{
    return $tagihan->kontrak->penghuni->user;
}

function halamanUnggah(Tagihan $tagihan, ?User $user = null)
{
    return Livewire::actingAs($user ?? pemilikTagihan($tagihan))->test('pages::tagihan-saya.unggah', ['tagihan' => $tagihan]);
}

it('F-12 butir 4: JPG, PNG, dan PDF diterima; pembayaran menunggu dibuat dan tagihan menunggu verifikasi', function (string $jenis, string $nama) {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->set('bukti', berkasUji($jenis, $nama))
        ->set('tanggal_bayar', '2026-11-03')
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertRedirect(route('tagihan-saya.index'));

    $pembayaran = Pembayaran::query()->where('tagihan_id', $tagihan->id)->sole();

    expect($pembayaran->status_verifikasi)->toBe(StatusVerifikasi::Menunggu)
        ->and((float) $pembayaran->nominal_bayar)->toBe(1400000.0)
        ->and($pembayaran->tanggal_bayar->toDateString())->toBe('2026-11-03')
        ->and($pembayaran->diunggah_pada->equalTo(now()))->toBeTrue()
        ->and($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi);

    Storage::disk('local')->assertExists($pembayaran->bukti_bayar_path);
    expect($pembayaran->bukti_bayar_path)->toEndWith('.'.$jenis);
})->with([
    'jpg' => ['jpg', 'transfer.jpg'],
    'png' => ['png', 'transfer.png'],
    'pdf' => ['pdf', 'transfer.pdf'],
]);

it('F-12 butir 2: berkas persis 5 MB masih diterima', function () {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->set('bukti', berkasUji('pdf', 'besar.pdf', 5120))
        ->call('kirim')
        ->assertHasNoErrors();

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi);
});

it('F-12 butir 2 dan 5: format salah atau lebih dari 5 MB ditolak dengan satu pesan, status tagihan tetap', function (string $jenis, string $nama, int $kb) {
    $tagihan = siapkanUnggah();

    $komponen = halamanUnggah($tagihan)
        ->set('bukti', berkasUji($jenis, $nama, $kb))
        ->assertHasErrors('bukti')
        ->assertSee(PESAN_BERKAS_UJI);

    // Berkas yang ditolak dibuang, jadi mengirim tanpa memilih lagi diminta memilih berkas.
    $komponen->call('kirim')->assertHasErrors(['bukti' => 'required']);

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and(Pembayaran::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('bukti-bayar'))->toBe([]);
})->with([
    '.txt' => ['txt', 'catatan.txt', 1],
    '.gif' => ['gif', 'animasi.gif', 1],
    'teks bernama .jpg' => ['txt', 'palsu.jpg', 1],
    'teks bernama .pdf' => ['txt', 'palsu.pdf', 1],
    'PDF 5121 KB' => ['pdf', 'terlalu-besar.pdf', 5121],
]);

it('F-12: berkas kosong diminta dipilih dengan pesan khusus', function () {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->call('kirim')
        ->assertHasErrors(['bukti' => 'required'])
        ->assertSee('Pilih berkas bukti transfer.');
});

it('F-12: kegagalan unggahan sementara memakai pesan format dan batas yang sama', function () {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->call('_uploadErrored', 'bukti', null, false)
        ->assertSee(PESAN_BERKAS_UJI);

    halamanUnggah($tagihan)
        ->call('_uploadErrored', 'bukti', json_encode(['errors' => ['files.0' => ['Bukti tidak boleh berukuran lebih dari 5120 kilobita.']]]), false)
        ->assertSee(PESAN_BERKAS_UJI)
        ->assertDontSee('kilobita');
});

it('F-12 butir 2: batas unggahan sementara Livewire dipasang 5 MB', function () {
    expect(config('livewire.temporary_file_upload.rules'))->toContain('max:5120');
});

it('F-12 butir 3: tanggal transfer kosong, tidak valid, dan setelah hari ini ditolak', function (string $nilai, string $pesan) {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->set('bukti', berkasUji('jpg', 'transfer.jpg'))
        ->set('tanggal_bayar', $nilai)
        ->call('kirim')
        ->assertHasErrors('tanggal_bayar')
        ->assertSee($pesan);

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar);
})->with([
    'kosong' => ['', 'Isi tanggal transfer.'],
    'besok' => ['2026-11-06', 'Tanggal transfer tidak boleh setelah hari ini.'],
    'bukan tanggal' => ['kemarin', 'Tanggal transfer tidak valid.'],
]);

it('F-12 butir 3: tanggal transfer bawaan hari ini (Asia/Jakarta) dan hari ini diterima', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 23:30:00', 'UTC')); // 6 November 06:30 WIB
    $tagihan = siapkanUnggah();

    $komponen = halamanUnggah($tagihan);

    expect($komponen->get('tanggal_bayar'))->toBe('2026-11-06');

    $komponen->set('bukti', berkasUji('jpg', 'transfer.jpg'))->call('kirim')->assertHasNoErrors();
});

it('F-12 butir 1: tagihan milik penghuni lain mendapat 403 di route dan di aksi langsung', function () {
    $tagihan = siapkanUnggah();
    $lain = buatKontrakUji('06', 'Rina Maharani')->penghuni->user;

    $this->actingAs($lain)->get(route('tagihan-saya.unggah', $tagihan))->assertForbidden();

    halamanUnggah($tagihan, $lain)->assertForbidden();

    // Aksi langsung: komponen dibuka pemilik, lalu sesi berganti ke penghuni lain.
    $komponen = halamanUnggah($tagihan);
    $this->actingAs($lain);
    $komponen->set('bukti', berkasUji('jpg', 'transfer.jpg'))->call('kirim')->assertForbidden();

    expect(Pembayaran::query()->count())->toBe(0)
        ->and($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar);
});

it('F-12: pengelola, pemilik, dan vendor mendapat 403 di halaman unggah', function (string $peran) {
    $tagihan = siapkanUnggah();

    $this->actingAs(User::factory()->{$peran}()->create())
        ->get(route('tagihan-saya.unggah', $tagihan))
        ->assertForbidden();
})->with(['pengelola', 'pemilik', 'vendor']);

it('F-12 butir 4: tagihan menunggu verifikasi atau lunas dialihkan ke Tagihan saya dengan pesan', function (StatusTagihan $status) {
    $tagihan = siapkanUnggah($status);

    $this->actingAs(pemilikTagihan($tagihan))
        ->get(route('tagihan-saya.unggah', $tagihan))
        ->assertRedirect(route('tagihan-saya.index'))
        ->assertSessionHas('toast', TagihanTidakBisaDiberiBukti::PESAN)
        ->assertSessionHas('toast_varian', 'warning');

    expect(Pembayaran::query()->count())->toBe(0);
})->with([StatusTagihan::MenungguVerifikasi, StatusTagihan::Lunas]);

it('F-12 butir 4: aksi kirim pada tagihan yang sudah menunggu ditolak, tanpa pembayaran baru', function () {
    $tagihan = siapkanUnggah();
    $komponen = halamanUnggah($tagihan)->set('bukti', berkasUji('jpg', 'transfer.jpg'));

    // Tab lain sudah mengirim bukti lebih dulu.
    $tagihan->unggahBukti(berkasUji('jpg', 'lain.jpg'), now());

    $komponen->call('kirim')->assertRedirect(route('tagihan-saya.index'));

    expect(Pembayaran::query()->where('tagihan_id', $tagihan->id)->count())->toBe(1)
        ->and(session('toast'))->toBe(TagihanTidakBisaDiberiBukti::PESAN)
        ->and(Storage::disk('local')->allFiles('bukti-bayar'))->toHaveCount(1);
});

it('F-12 butir 4: kirim ganda ditolak indeks uq_pembayaran_menunggu, berkas kedua dihapus', function () {
    $tagihan = siapkanUnggah();
    // Keadaan tidak konsisten: sudah ada bukti menunggu padahal tagihan masih belum dibayar.
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $tagihan->id]);

    expect(fn () => $tagihan->unggahBukti(berkasUji('jpg', 'kedua.jpg'), now()))
        ->toThrow(TagihanTidakBisaDiberiBukti::class, TagihanTidakBisaDiberiBukti::PESAN);

    expect(Pembayaran::query()->where('tagihan_id', $tagihan->id)->count())->toBe(1)
        ->and($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and(Storage::disk('local')->allFiles('bukti-bayar'))->toBe([]);
});

it('F-12 butir 4: unggahan kedua untuk tagihan yang sama ditolak, hanya satu pembayaran menunggu', function () {
    $tagihan = siapkanUnggah();

    $tagihan->unggahBukti(berkasUji('jpg', 'pertama.jpg'), now());

    expect(fn () => $tagihan->unggahBukti(berkasUji('jpg', 'kedua.jpg'), now()))
        ->toThrow(TagihanTidakBisaDiberiBukti::class);

    expect(Pembayaran::query()->menunggu()->where('tagihan_id', $tagihan->id)->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('bukti-bayar'))->toHaveCount(1);
});

it('F-12 butir 5: kegagalan transaksi menghapus berkas, status tetap, dan penghuni bisa mencoba lagi', function () {
    $tagihan = siapkanUnggah();
    $gagal = true;
    Pembayaran::creating(function () use (&$gagal) {
        if ($gagal) {
            throw new RuntimeException('Gangguan basis data buatan.');
        }
    });

    $komponen = halamanUnggah($tagihan)
        ->set('bukti', berkasUji('jpg', 'transfer.jpg'))
        ->call('kirim')
        ->assertHasErrors('bukti')
        ->assertNoRedirect();

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and(Pembayaran::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('bukti-bayar'))->toBe([])
        ->and($komponen->get('bukti'))->toBeNull();

    $gagal = false;
    $komponen->set('bukti', berkasUji('jpg', 'transfer.jpg'))
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertRedirect(route('tagihan-saya.index'));

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi);
});

it('F-12: setelah bukti ditolak (tagihan kembali belum dibayar) penghuni bisa mengunggah lagi', function () {
    $tagihan = siapkanUnggah();
    Pembayaran::factory()->ditolak()->create(['tagihan_id' => $tagihan->id]);

    halamanUnggah($tagihan)
        ->set('bukti', berkasUji('png', 'baru.png'))
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertRedirect(route('tagihan-saya.index'));

    expect(Pembayaran::query()->where('tagihan_id', $tagihan->id)->count())->toBe(2)
        ->and(Pembayaran::query()->menunggu()->where('tagihan_id', $tagihan->id)->count())->toBe(1)
        ->and($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::MenungguVerifikasi);
});

it('F-12: berkas tersimpan di disk privat dengan nama acak, bukan nama asli, dan tidak ada URL publik', function () {
    Storage::fake('public');
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->set('bukti', berkasUji('jpg', 'transfer-november-maya.jpg'))
        ->call('kirim');

    $pembayaran = Pembayaran::query()->sole();

    expect($pembayaran->bukti_bayar_path)->toMatch('#^bukti-bayar/2026/11/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$#')
        ->and($pembayaran->bukti_bayar_path)->not->toContain('maya')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($pembayaran->toArray())->not->toHaveKey('bukti_bayar_path');
});

it('F-12: mengunggah bukti tidak membuat baris sinkronisasi_erp (baru saat diterima, F-13)', function () {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)->set('bukti', berkasUji('jpg', 'transfer.jpg'))->call('kirim');

    expect(SinkronisasiErp::query()->count())->toBe(0);
});

it('F-12: sukses menampilkan toast lewat session dan Tagihan saya menampilkannya', function () {
    $tagihan = siapkanUnggah();

    $this->actingAs(pemilikTagihan($tagihan));

    Livewire::test('pages::tagihan-saya.unggah', ['tagihan' => $tagihan])
        ->set('bukti', berkasUji('jpg', 'transfer.jpg'))
        ->call('kirim');

    expect(session('toast'))->toBe('Bukti November terkirim. Tunggu pengelola mengecek.')
        ->and(session('toast_varian'))->toBe('success');

    Livewire::test('pages::tagihan-saya.index')
        ->assertSee('Menunggu, 5 Nov')
        ->assertDontSee('Unggah Bukti</a>', false);
});

it('halaman unggah menampilkan nominal, tanpa field nominal dan tanpa catatan', function () {
    $tagihan = siapkanUnggah();

    $komponen = halamanUnggah($tagihan)
        ->assertSee('Unggah Bukti')
        ->assertSee('Kembali')
        ->assertSee('Bukti')
        ->assertSee('Pilih')
        ->assertSee('JPG, PNG, PDF. Maks 5 MB.')
        ->assertSee('Nominal')
        ->assertSee('Rp1.400.000')
        ->assertSee('Tanggal Transfer')
        ->assertSee('Kirim')
        ->assertDontSee('Satu bukti untuk satu tagihan penuh.')
        ->assertDontSee('Jatuh tempo 10 Nov. Bukti yang terkirim')
        ->assertDontSee('Setelah terkirim, statusnya jadi Menunggu dicek.')
        ->assertSee('accept=".jpg,.jpeg,.png,.pdf"', false);

    expect(property_exists($komponen->instance(), 'nominal'))->toBeFalse()
        ->and($komponen->html())->not->toContain('name="nominal"');
});

it('halaman unggah setelah jatuh tempo tetap tanpa catatan telat', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00', 'Asia/Jakarta'));
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->assertDontSee('Tagihan tetap tercatat telat sampai bukti diterima pengelola.');
});

it('halaman unggah menampilkan nama dan ukuran berkas setelah dipilih', function () {
    $tagihan = siapkanUnggah();

    halamanUnggah($tagihan)
        ->set('bukti', berkasUji('jpg', 'transfer-november.jpg', 1843))
        ->assertSee('transfer-november.jpg')
        ->assertSee('1,8 MB')
        ->assertSee('Ganti');
});

it('tamu diarahkan ke login dari halaman unggah', function () {
    $tagihan = siapkanUnggah();

    $this->get(route('tagihan-saya.unggah', $tagihan))->assertRedirect(route('login'));
});

it('Tagihan::unggahBukti menolak tipe berkas yang tidak didukung sebelum menyimpan apa pun', function () {
    $tagihan = siapkanUnggah();

    expect(fn () => $tagihan->unggahBukti(berkasUji('txt', 'teks.txt'), now()))
        ->toThrow(InvalidArgumentException::class);

    expect(Storage::disk('local')->allFiles('bukti-bayar'))->toBe([])
        ->and(Kontrak::query()->count())->toBe(1);
});
