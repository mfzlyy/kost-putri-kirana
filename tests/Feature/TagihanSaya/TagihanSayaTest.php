<?php

use App\Enums\StatusTagihan;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function buatTagihanSaya(Kontrak $kontrak, string $periode, string $jatuhTempo, StatusTagihan $status, int $nominal = 1400000): Tagihan
{
    return Tagihan::factory()->create([
        'kontrak_id' => $kontrak->id,
        'periode' => $periode,
        'jatuh_tempo' => $jatuhTempo,
        'nominal' => $nominal,
        'status_tagihan' => $status,
    ]);
}

function halamanTagihanSaya(User $user)
{
    return Livewire::actingAs($user)->test('pages::tagihan-saya.index');
}

it('F-11 butir 1: penghuni melihat periode, nominal, jatuh tempo, dan status tagihannya sendiri', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatTagihanSaya($kontrak, '2026-10-01', '2026-10-10', StatusTagihan::Lunas);
    Pembayaran::factory()->diterima()->create([
        'tagihan_id' => Tagihan::query()->where('periode', '2026-10-01')->value('id'),
        'tanggal_bayar' => '2026-10-08',
    ]);
    buatTagihanSaya($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);

    $lain = buatKontrakUji('06', 'Rina Maharani');
    buatTagihanSaya($lain, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar, 1750000);

    halamanTagihanSaya($kontrak->penghuni->user)
        ->assertDontSee('Halo, Maya')
        ->assertDontSee('Kamar 05. Tarif Rp1.400.000 per bulan.')
        ->assertSee('November 2026')
        ->assertSee('Oktober 2026')
        ->assertSee('10 Nov')
        ->assertSee('Rp1.400.000')
        ->assertSee('Belum Bayar')
        ->assertSee('Lunas, 8 Okt')
        ->assertDontSee('Rp1.750.000')
        ->assertDontSee('Rina');
});

it('F-11 butir 2: bukti yang ditolak menampilkan alasan, dan alasan berisi HTML tampil sebagai teks', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $tagihan = buatTagihanSaya($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    Pembayaran::factory()->ditolak('Nominal tidak sesuai <script>alert(1)</script>')->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-11-02 08:00', 'Asia/Jakarta'),
        'diverifikasi_pada' => Carbon::parse('2026-11-03 12:00', 'Asia/Jakarta'),
    ]);

    $komponen = halamanTagihanSaya($kontrak->penghuni->user)
        ->assertSee('Ditolak, 3 Nov: Nominal tidak sesuai &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('Ditolak, 3 Nov: Nominal tidak sesuai &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    expect($komponen->html())->toContain('role="status"');
});

it('F-11 butir 3: tagihan tunggakan diberi penanda "Telat n hari"', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatTagihanSaya($kontrak, '2026-10-01', '2026-10-10', StatusTagihan::BelumDibayar);

    halamanTagihanSaya($kontrak->penghuni->user)
        ->assertSee('Jatuh Tempo 10 Okt, Telat 26 Hari')
        ->assertSee('Telat 26 Hari');
});

it('F-11 butir 3: bukti menunggu yang diunggah setelah jatuh tempo tetap telat dan diberi keterangan', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $tagihan = buatTagihanSaya($kontrak, '2026-10-01', '2026-10-10', StatusTagihan::MenungguVerifikasi);
    Pembayaran::factory()->menunggu()->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-11-03 10:00', 'Asia/Jakarta'),
    ]);

    halamanTagihanSaya($kontrak->penghuni->user)
        ->assertSee('Menunggu, 3 Nov')
        ->assertSee('Telat 26 Hari')
        ->assertDontSee('Bukti dikirim setelah jatuh tempo');
});

it('F-11: bukti menunggu yang tepat waktu tidak dihitung telat', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $tagihan = buatTagihanSaya($kontrak, '2026-10-01', '2026-10-10', StatusTagihan::MenungguVerifikasi);
    Pembayaran::factory()->menunggu()->create([
        'tagihan_id' => $tagihan->id,
        'diunggah_pada' => Carbon::parse('2026-10-10 23:00', 'Asia/Jakarta'),
    ]);

    halamanTagihanSaya($kontrak->penghuni->user)
        ->assertSee('Menunggu, 10 Okt')
        ->assertDontSee('Telat')
        ->assertDontSee('Bukti dikirim setelah jatuh tempo');
});

it('F-11 butir 4: tanpa tagihan tampil "Belum ada tagihan" dan bagian Semua tagihan tidak ada', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');

    halamanTagihanSaya($kontrak->penghuni->user)
        ->assertSee('Belum ada tagihan')
        ->assertDontSee('Riwayat Tagihan');
});

it('kartu utama memilih tagihan belum dibayar paling lama dan menyebut sisanya', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatTagihanSaya($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar, 1400000);
    $lama = buatTagihanSaya($kontrak, '2026-09-01', '2026-09-10', StatusTagihan::BelumDibayar, 1300000);
    buatTagihanSaya($kontrak, '2026-10-01', '2026-10-10', StatusTagihan::BelumDibayar, 1350000);

    $komponen = halamanTagihanSaya($kontrak->penghuni->user);

    expect($komponen->instance()->kartuUtama)->toMatchArray(['keadaan' => 'belum', 'lainnya' => 2])
        ->and($komponen->instance()->kartuUtama['baris']['id'])->toBe($lama->id);

    $komponen->assertSee('Tagihan September')
        ->assertSee('Rp1.300.000')
        ->assertSee('2 tagihan lain belum dibayar.')
        ->assertSee('Unggah Bukti')
        ->assertSee(route('tagihan-saya.unggah', $lama->id), false);
});

it('kartu utama: jatuh tempo masih jauh, hari ini, dan sudah lewat', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $tagihan = buatTagihanSaya($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);
    $penghuni = $kontrak->penghuni->user;

    halamanTagihanSaya($penghuni)->assertSee('5 Hari Lagi');

    Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'Asia/Jakarta'));
    halamanTagihanSaya($penghuni)->assertSee('Jatuh Tempo Hari Ini');

    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00', 'Asia/Jakarta'));
    halamanTagihanSaya($penghuni)->assertSee('Jatuh Tempo 10 Nov, Telat 2 Hari');

    expect($tagihan->fresh()->status_tagihan)->toBe(StatusTagihan::BelumDibayar);
});

it('kartu utama: keadaan menunggu, lunas, dan kosong', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $user = $kontrak->penghuni->user;

    expect(halamanTagihanSaya($user)->instance()->kartuUtama['keadaan'])->toBe('kosong');

    $tagihan = buatTagihanSaya($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::MenungguVerifikasi);
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $tagihan->id]);

    halamanTagihanSaya($user)
        ->assertSee('Bukti November sedang dicek pengelola.')
        ->assertSee('Menunggu')
        ->assertDontSee('Unggah Bukti');

    $tagihan->pembayaran()->delete();
    Pembayaran::factory()->diterima()->create(['tagihan_id' => $tagihan->id]);
    $tagihan->forceFill(['status_tagihan' => StatusTagihan::Lunas])->save();

    halamanTagihanSaya($user)->assertSee('Semua tagihan sudah lunas.');
});

it('tagihan dari kontrak lama milik penghuni yang sama ikut tampil', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $lama = Kontrak::factory()->berakhir()->create([
        'penghuni_id' => $kontrak->penghuni_id,
        'kamar_id' => buatKontrakUji('07', 'Orang Lain')->kamar_id,
        'tarif_bulanan' => 1111000,
    ]);
    buatTagihanSaya($lama, '2025-03-01', '2025-03-10', StatusTagihan::Lunas, 1111000);

    halamanTagihanSaya($kontrak->penghuni->user)
        ->assertSee('Maret 2025')
        ->assertSee('Rp1.111.000');
});

it('penghuni tanpa baris penghuni melihat pesan belum dilengkapi, tanpa galat', function () {
    $user = User::factory()->penghuni()->create();
    expect(Penghuni::query()->where('user_id', $user->id)->exists())->toBeFalse();

    halamanTagihanSaya($user)
        ->assertOk()
        ->assertSee('Tagihan')
        ->assertSee('Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.');
});

it('daftar dipaginasi 12 tagihan, terbaru dulu', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    foreach (range(0, 13) as $selisih) {
        $periode = Carbon::parse('2026-11-01')->subMonthsNoOverflow($selisih + 1);
        buatTagihanSaya($kontrak, $periode->toDateString(), $periode->copy()->day(10)->toDateString(), StatusTagihan::Lunas);
    }

    $komponen = halamanTagihanSaya($kontrak->penghuni->user);

    expect($komponen->instance()->daftar)->toHaveCount(12)
        ->and($komponen->instance()->daftar->first()['periode']->toDateString())->toBe('2026-10-01');
});

it('jumlah query tidak bertambah menurut jumlah tagihan (tanpa N+1)', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');

    $user = $kontrak->penghuni->user;

    $hitung = function () use ($user) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        halamanTagihanSaya($user);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    foreach (range(1, 2) as $selisih) {
        $periode = Carbon::parse('2026-11-01')->subMonthsNoOverflow($selisih);
        $tagihan = buatTagihanSaya($kontrak, $periode->toDateString(), $periode->copy()->day(10)->toDateString(), StatusTagihan::BelumDibayar);
        Pembayaran::factory()->ditolak()->create(['tagihan_id' => $tagihan->id]);
    }
    $sedikit = $hitung();

    foreach (range(3, 11) as $selisih) {
        $periode = Carbon::parse('2026-11-01')->subMonthsNoOverflow($selisih);
        $tagihan = buatTagihanSaya($kontrak, $periode->toDateString(), $periode->copy()->day(10)->toDateString(), StatusTagihan::BelumDibayar);
        Pembayaran::factory()->ditolak()->create(['tagihan_id' => $tagihan->id]);
    }

    expect($hitung())->toBe($sedikit);
});

it('F-11: pengelola, pemilik, dan vendor mendapat 403 di /tagihan-saya', function (string $peran) {
    $this->actingAs(User::factory()->{$peran}()->create())
        ->get('/tagihan-saya')
        ->assertForbidden();
})->with(['pengelola', 'pemilik', 'vendor']);

it('F-11: penghuni membuka /tagihan-saya lewat HTTP dan melihat tagihannya', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatTagihanSaya($kontrak, '2026-11-01', '2026-11-10', StatusTagihan::BelumDibayar);

    $this->actingAs($kontrak->penghuni->user)
        ->get('/tagihan-saya')
        ->assertOk()
        ->assertDontSee('Halo, Maya')
        ->assertSee('Unggah Bukti');
});

it('tamu diarahkan ke halaman login', function () {
    $this->get('/tagihan-saya')->assertRedirect(route('login'));
});
