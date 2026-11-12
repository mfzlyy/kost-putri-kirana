<?php

use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function pemilikBukti(Pembayaran $pembayaran): User
{
    return $pembayaran->tagihan->kontrak->penghuni->user;
}

it('F-13 butir 2: pengelola, pemilik, dan penghuni pemilik membuka bukti dengan header aman', function (string $peran, string $jenis, string $mime) {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari', berkas: $jenis);
    $user = $peran === 'penghuni' ? pemilikBukti($p) : User::factory()->{$peran}()->create();

    $respons = $this->actingAs($user)->get(route('bukti-bayar.tampil', $p));

    $respons->assertOk()
        ->assertHeader('Content-Type', $mime)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeader('Content-Disposition', 'inline; filename="bukti-2026-11-kamar-05.'.$jenis.'"');

    $cache = (string) $respons->headers->get('Cache-Control');
    expect($cache)->toContain('private')->toContain('no-store');
    expect($respons->getContent())->toBe(isiBerkasContoh($jenis));

    if ($jenis === 'pdf') {
        $respons->assertHeaderMissing('Content-Security-Policy');
    } else {
        $respons->assertHeader('Content-Security-Policy', 'sandbox');
    }
})->with(function () {
    foreach (['pengelola', 'pemilik', 'penghuni'] as $peran) {
        yield "{$peran} jpg" => [$peran, 'jpg', 'image/jpeg'];
        yield "{$peran} png" => [$peran, 'png', 'image/png'];
        yield "{$peran} pdf" => [$peran, 'pdf', 'application/pdf'];
    }
});

it('nama berkas di header tanpa data pribadi dan tanpa nama asli', function () {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari');

    $disposisi = $this->actingAs(User::factory()->pengelola()->create())
        ->get(route('bukti-bayar.tampil', $p))
        ->headers->get('Content-Disposition');

    expect($disposisi)->not->toContain('Maya')->not->toContain($p->bukti_bayar_path)
        ->and($disposisi)->toBe('inline; filename="bukti-2026-11-kamar-05.jpg"');
});

it('F-13 butir 2: penghuni lain dan vendor mendapat 403, tamu diarahkan ke login', function () {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari');
    $lain = buatKontrakUji('06', 'Rina Maharani')->penghuni->user;

    $this->actingAs($lain)->get(route('bukti-bayar.tampil', $p))->assertForbidden();
    $this->actingAs(User::factory()->vendor()->create())->get(route('bukti-bayar.tampil', $p))->assertForbidden();

    auth()->logout();
    $this->get(route('bukti-bayar.tampil', $p))->assertRedirect(route('login'));
});

it('403 diutamakan daripada 404 agar penghuni lain tidak tahu berkas ada atau tidak', function () {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari', berkas: null);
    $lain = buatKontrakUji('06', 'Rina Maharani')->penghuni->user;

    $this->actingAs($lain)->get(route('bukti-bayar.tampil', $p))->assertForbidden();
});

it('F-13 butir 2: path migrasi tanpa bukti dan berkas yang hilang menghasilkan 404', function () {
    $migrasi = siapkanBuktiMenunggu('05', 'Maya Sari', berkas: null);
    $hilang = siapkanBuktiMenunggu('06', 'Rina Maharani');
    Storage::disk('local')->delete($hilang->bukti_bayar_path);

    $pengelola = User::factory()->pengelola()->create();

    $this->actingAs($pengelola)->get(route('bukti-bayar.tampil', $migrasi))->assertNotFound();
    $this->actingAs($pengelola)->get(route('bukti-bayar.tampil', $hilang))->assertNotFound();
    $this->actingAs($pengelola)->get('/bukti-bayar/999999')->assertNotFound();
});

it('F-13 butir 2: berkas yang isinya bukan JPG, PNG, atau PDF tidak dikirim (404)', function () {
    $teks = siapkanBuktiMenunggu('05', 'Maya Sari', berkas: 'txt');
    // Teks yang diberi ekstensi .jpg tetap ditolak karena isinya yang dideteksi.
    Storage::disk('local')->put('bukti-bayar/2026/11/palsu.jpg', '<html><script>alert(1)</script></html>');
    $palsu = siapkanBuktiMenunggu('06', 'Rina Maharani', berkas: null);
    $palsu->forceFill(['bukti_bayar_path' => 'bukti-bayar/2026/11/palsu.jpg'])->save();

    $pengelola = User::factory()->pengelola()->create();

    $this->actingAs($pengelola)->get(route('bukti-bayar.tampil', $teks))->assertNotFound();
    $this->actingAs($pengelola)->get(route('bukti-bayar.tampil', $palsu))->assertNotFound();
});

it('tidak ada URL publik atau signed URL ke berkas bukti', function () {
    $p = siapkanBuktiMenunggu('05', 'Maya Sari');
    $pengelola = User::factory()->pengelola()->create();

    expect($p->toArray())->not->toHaveKey('bukti_bayar_path');

    // Route /storage bawaan disk local menolak permintaan tanpa tanda tangan; berkas tidak pernah dikirim lewat sana.
    expect($this->actingAs($pengelola)->get('/storage/'.$p->bukti_bayar_path)->status())->toBeIn([403, 404]);
    $this->actingAs($pengelola)->get('/'.$p->bukti_bayar_path)->assertNotFound();

    $html = $this->actingAs($pengelola)->get('/verifikasi-pembayaran')->getContent();
    expect($html)->not->toContain($p->bukti_bayar_path)->not->toContain('signature=');
});
