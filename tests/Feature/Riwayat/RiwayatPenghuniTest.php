<?php

use App\Enums\StatusTagihan;
use App\Models\Kontrak;
use App\Models\Pembayaran;
use App\Models\PengaturanOperasional;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Jakarta'));
    // Baris sinkronisasi menunggu tidak boleh dikirim ke Odoo saat tes.
    Queue::fake();
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

function halamanRiwayat(User $user, array $query = [])
{
    return Livewire::withQueryParams($query)->actingAs($user)->test('pages::riwayat-transaksi.index');
}

it('F-14 butir 1: penghuni melihat periode, nominal, tanggal bayar, tanggal diterima, dan Lunas', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatPembayaranDiterima($kontrak, '2026-09-01', '2026-09-08', '2026-09-09 10:00');

    halamanRiwayat($kontrak->penghuni->user)
        ->assertSee('Riwayat')
        ->assertDontSee('Pembayaran yang sudah diterima pengelola.')
        ->assertSee('September 2026')
        ->assertSee('Rp1.400.000')
        ->assertSee('8 Sep')
        ->assertSee('9 Sep')
        ->assertSee('Dibayar 8 Sep, Diterima 9 Sep')
        ->assertSee('Lunas');
});

it('F-14 butir 1: pembayaran menunggu dan ditolak tidak pernah tampil', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatPembayaranDiterima($kontrak, '2026-09-01', '2026-09-08', '2026-09-09 10:00');

    $menunggu = Tagihan::factory()->create(['kontrak_id' => $kontrak->id, 'periode' => '2026-10-01', 'jatuh_tempo' => '2026-10-10', 'nominal' => 1111000, 'status_tagihan' => StatusTagihan::MenungguVerifikasi]);
    Pembayaran::factory()->menunggu()->create(['tagihan_id' => $menunggu->id]);
    $ditolak = Tagihan::factory()->create(['kontrak_id' => $kontrak->id, 'periode' => '2026-11-01', 'jatuh_tempo' => '2026-11-10', 'nominal' => 2222000]);
    Pembayaran::factory()->ditolak()->create(['tagihan_id' => $ditolak->id]);

    $komponen = halamanRiwayat($kontrak->penghuni->user)
        ->assertDontSee('Rp1.111.000')
        ->assertDontSee('Rp2.222.000')
        ->assertDontSee('Oktober 2026')
        ->assertDontSee('November 2026');

    expect($komponen->instance()->barisPenghuni)->toHaveCount(1);
});

it('F-14 butir 2: pembayaran penghuni lain tidak pernah tampil, juga bila ID dikirim lewat query string', function () {
    $saya = buatKontrakUji('05', 'Maya Sari');
    $lain = buatKontrakUji('06', 'Rina Maharani');
    buatPembayaranDiterima($saya, '2026-09-01', '2026-09-08', '2026-09-09 10:00', 1400000);
    $milikLain = buatPembayaranDiterima($lain, '2026-09-01', '2026-09-08', '2026-09-09 10:00', 1750000);

    $query = ['penghuni' => $lain->penghuni_id, 'periode' => '2026-09', 'pembayaran' => $milikLain->id, 'id' => $milikLain->id];

    halamanRiwayat($saya->penghuni->user, $query)
        ->assertSee('Rp1.400.000')
        ->assertDontSee('Rp1.750.000')
        ->assertDontSee('Rina');

    $this->actingAs($saya->penghuni->user)
        ->get('/riwayat-transaksi?'.http_build_query($query))
        ->assertOk()
        ->assertSee('Rp1.400.000')
        ->assertDontSee('Rp1.750.000')
        ->assertDontSee('Rina');
});

it('F-14: pembayaran dari kontrak lama milik penghuni yang sama tampil', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $lama = Kontrak::factory()->berakhir()->create([
        'penghuni_id' => $kontrak->penghuni_id,
        'kamar_id' => buatKontrakUji('07', 'Orang Lain')->kamar_id,
        'tarif_bulanan' => 1111000,
    ]);
    buatPembayaranDiterima($lama, '2025-03-01', '2025-03-08', '2025-03-09 10:00', 1111000);
    buatPembayaranDiterima($kontrak, '2026-09-01', '2026-09-08', '2026-09-09 10:00');

    halamanRiwayat($kontrak->penghuni->user)
        ->assertSee('Maret 2025')
        ->assertSee('Rp1.111.000')
        ->assertSee('Sejak')
        ->assertSee('Maret 2025')
        ->assertSee('2 kali')
        ->assertSee('Rp2.511.000');
});

it('F-14 butir 3: penghuni tidak melihat status Odoo, nama pengelola, atau kolom penghuni dan kamar', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $pengelola = User::factory()->pengelola()->create(['nama' => 'Pengelola Rahasia']);
    buatPembayaranDiterima($kontrak, '2026-08-01', '2026-08-08', '2026-08-09 10:00', oleh: $pengelola, sinkron: 'gagal');
    buatPembayaranDiterima($kontrak, '2026-09-01', '2026-09-08', '2026-09-09 10:00', oleh: $pengelola, sinkron: 'terkirim');
    buatPembayaranDiterima($kontrak, '2026-10-01', '2026-10-08', '2026-10-09 10:00', oleh: $pengelola, sinkron: 'menunggu');

    $html = $this->actingAs($kontrak->penghuni->user)->get('/riwayat-transaksi')->assertOk()->getContent();

    foreach (['Odoo', 'Terkirim', 'Gagal', 'Menunggu', 'Pengelola Rahasia', 'oleh ', '>Penghuni</th>', '>Kamar</th>', $kontrak->penghuni->no_telepon] as $terlarang) {
        expect($html)->not->toContain($terlarang);
    }
});

it('F-14: ringkasan Sejak dan jumlah kali serta total benar', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatPembayaranDiterima($kontrak, '2026-02-01', '2026-02-08', '2026-02-09 10:00', 1000000);
    buatPembayaranDiterima($kontrak, '2026-03-01', '2026-03-08', '2026-03-09 10:00', 1200000);
    buatPembayaranDiterima($kontrak, '2026-09-01', '2026-09-08', '2026-09-09 10:00', 1400000);

    halamanRiwayat($kontrak->penghuni->user)
        ->assertSee('Sejak')
        ->assertSee('Februari 2026')
        ->assertSee('3 kali')
        ->assertSee('Rp3.600.000');
});

it('F-14: Lainnya muncul bila lebih dari 12, menambah baris, dan hilang bila semua tampil', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    foreach (range(1, 15) as $n) {
        $periode = Carbon::parse('2026-10-01')->subMonthsNoOverflow($n);
        buatPembayaranDiterima($kontrak, $periode->toDateString(), $periode->copy()->day(8)->toDateString(), $periode->copy()->day(9)->toDateTimeString());
    }

    $komponen = halamanRiwayat($kontrak->penghuni->user);

    expect($komponen->instance()->barisPenghuni)->toHaveCount(12);
    $komponen->assertSee('Lainnya')
        ->call('tampilkanLagi');

    expect($komponen->instance()->barisPenghuni)->toHaveCount(15);
    $komponen->assertDontSee('Lainnya');
});

it('F-14: Lainnya menambah paling banyak 12 baris', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    foreach (range(1, 30) as $n) {
        $periode = Carbon::parse('2026-10-01')->subMonthsNoOverflow($n);
        buatPembayaranDiterima($kontrak, $periode->toDateString(), $periode->copy()->day(8)->toDateString(), $periode->copy()->day(9)->toDateTimeString());
    }

    halamanRiwayat($kontrak->penghuni->user)
        ->assertSee('Lainnya')
        ->call('tampilkanLagi')
        ->assertSee('Lainnya')
        ->call('tampilkanLagi')
        ->assertDontSee('Lainnya');
});

it('F-14: baris diurut periode terbaru di atas', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    buatPembayaranDiterima($kontrak, '2026-08-01', '2026-08-08', '2026-08-09 10:00');
    buatPembayaranDiterima($kontrak, '2026-10-01', '2026-10-08', '2026-10-09 10:00');
    buatPembayaranDiterima($kontrak, '2026-09-01', '2026-09-08', '2026-09-09 10:00');

    expect(halamanRiwayat($kontrak->penghuni->user)->instance()->barisPenghuni->map(fn ($b) => $b->tagihan->periode->format('Y-m'))->all())
        ->toBe(['2026-10', '2026-09', '2026-08']);
});

it('F-14: tanpa pembayaran diterima tampil "Belum ada pembayaran yang diterima." tanpa ringkasan', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');

    halamanRiwayat($kontrak->penghuni->user)
        ->assertSee('Belum ada pembayaran yang diterima.')
        ->assertDontSee('Sejak')
        ->assertDontSee('kali,');
});

it('penghuni tanpa baris penghuni melihat pesan belum dilengkapi', function () {
    halamanRiwayat(User::factory()->penghuni()->create())
        ->assertOk()
        ->assertSee('Riwayat')
        ->assertSee('Data diri Anda belum dilengkapi pengelola. Hubungi pengelola kos.');
});

it('jumlah query tidak bertambah menurut jumlah pembayaran (tanpa N+1)', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');
    $user = $kontrak->penghuni->user;

    $hitung = function () use ($user) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        halamanRiwayat($user);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    foreach (range(1, 2) as $n) {
        $p = Carbon::parse('2026-10-01')->subMonthsNoOverflow($n);
        buatPembayaranDiterima($kontrak, $p->toDateString(), $p->copy()->day(8)->toDateString(), $p->copy()->day(9)->toDateTimeString());
    }
    $hitung();
    $sedikit = $hitung();

    foreach (range(3, 11) as $n) {
        $p = Carbon::parse('2026-10-01')->subMonthsNoOverflow($n);
        buatPembayaranDiterima($kontrak, $p->toDateString(), $p->copy()->day(8)->toDateString(), $p->copy()->day(9)->toDateTimeString());
    }

    expect($hitung())->toBe($sedikit);
});

it('halaman penghuni memuat menu tab dengan Riwayat aktif', function () {
    $kontrak = buatKontrakUji('05', 'Maya Sari');

    $html = $this->actingAs($kontrak->penghuni->user)->get('/riwayat-transaksi')->getContent();

    expect($html)->toMatch('#aria-current="page"[^>]*>.*?<span>Riwayat</span>#s');
});
