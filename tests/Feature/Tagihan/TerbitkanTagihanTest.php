<?php

use App\Enums\StatusTagihan;
use App\Exceptions\PenerbitanTagihanDitolak;
use App\Models\Kontrak;
use App\Models\PengaturanOperasional;
use App\Models\Penghuni;
use App\Models\Tagihan;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));
    PengaturanOperasional::factory()->lengkap(10, 30)->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Enam kontrak aktif, tarif berbeda per kamar.
 *
 * @return list<Kontrak>
 */
function enamKontrakAktif(): array
{
    $tarif = ['01' => 1250000, '02' => 1250000, '03' => 1250000, '04' => 1300000, '05' => 1400000, '06' => 1400000];
    $kontrak = [];

    foreach ($tarif as $nomor => $nilai) {
        $kontrak[] = buatKontrakUji($nomor, "Penghuni {$nomor}", [
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2027-01-01',
            'tarif_bulanan' => $nilai,
        ]);
    }

    return $kontrak;
}

function periodeUji(string $tanggal = '2026-11-01'): CarbonImmutable
{
    return CarbonImmutable::parse($tanggal);
}

/**
 * Pencocok event toast Flux berdasarkan teksnya.
 */
function toastTagihan(string $teks): Closure
{
    return fn (string $nama, array $parameter): bool => ($parameter['slots']['text'] ?? null) === $teks;
}

it('F-10 butir 1 dan 2: menerbitkan November untuk 6 kontrak aktif, nominal = tarif kontrak, jatuh tempo sesuai pengaturan', function () {
    $kontrak = enamKontrakAktif();

    $hasil = Tagihan::terbitkanPeriode(periodeUji('2026-11-17')); // tanggal di tengah bulan dinormalisasi ke tanggal 1

    expect($hasil->dibuat)->toBe(6)
        ->and($hasil->dilewati)->toBe(0)
        ->and($hasil->totalNominal)->toBe(1250000 * 3 + 1300000 + 1400000 * 2)
        ->and($hasil->periode->toDateString())->toBe('2026-11-01')
        ->and(Tagihan::query()->count())->toBe(6);

    foreach ($kontrak as $item) {
        $tagihan = Tagihan::query()->where('kontrak_id', $item->id)->sole();

        expect($tagihan->periode->toDateString())->toBe('2026-11-01')
            ->and((int) $tagihan->nominal)->toBe((int) $item->tarif_bulanan)
            ->and($tagihan->jatuh_tempo->toDateString())->toBe('2026-11-10')
            ->and($tagihan->status_tagihan)->toBe(StatusTagihan::BelumDibayar);
    }
});

it('jatuh tempo mengikuti tanggal pada pengaturan, bukan angka tetap', function () {
    enamKontrakAktif();
    PengaturanOperasional::aktif()->update(['tanggal_jatuh_tempo' => 25]);

    Tagihan::terbitkanPeriode(periodeUji());

    expect(Tagihan::query()->pluck('jatuh_tempo')->map->toDateString()->unique()->all())->toBe(['2026-11-25']);
});

it('F-10 butir 3: menerbitkan ulang tidak membuat tagihan ganda dan menghitung yang dilewati', function () {
    enamKontrakAktif();

    Tagihan::terbitkanPeriode(periodeUji());
    $ulang = Tagihan::terbitkanPeriode(periodeUji());

    expect($ulang->dibuat)->toBe(0)
        ->and($ulang->dilewati)->toBe(6)
        ->and($ulang->totalNominal)->toBe(0)
        ->and(Tagihan::query()->count())->toBe(6);

    // Kontrak baru muncul setelahnya: hanya kontrak itu yang dibuat, yang lain dilewati.
    buatKontrakUji('07', 'Penghuni 07', ['tanggal_mulai' => '2026-11-01', 'tanggal_berakhir' => '2027-11-01', 'tarif_bulanan' => 1500000]);
    $susulan = Tagihan::terbitkanPeriode(periodeUji());

    expect($susulan->dibuat)->toBe(1)
        ->and($susulan->dilewati)->toBe(6)
        ->and($susulan->totalNominal)->toBe(1500000)
        ->and(Tagihan::query()->count())->toBe(7);
});

it('permintaan bersamaan yang sudah menyisipkan tagihan lebih dulu dihitung dilewati, bukan galat', function () {
    $kontrak = enamKontrakAktif();
    $disisipkan = false;

    // Kontrak dibaca setelah pengecekan "sudah punya tagihan". Menyisipkan tagihan kontrak pertama saat itu
    // meniru permintaan lain yang menyelip: penerbit baru tahu setelah insert melanggar indeks unik.
    Kontrak::retrieved(function (Kontrak $dibaca) use (&$disisipkan, $kontrak) {
        if ($disisipkan || $dibaca->id !== $kontrak[0]->id) {
            return;
        }

        $disisipkan = true;
        DB::table('tagihan')->insert([
            'kontrak_id' => $dibaca->id,
            'periode' => '2026-11-01',
            'nominal' => 1250000,
            'jatuh_tempo' => '2026-11-10',
        ]);
    });

    try {
        $hasil = Tagihan::terbitkanPeriode(periodeUji());
    } finally {
        Kontrak::flushEventListeners();
    }

    expect($disisipkan)->toBeTrue()
        ->and($hasil->dibuat)->toBe(5)
        ->and($hasil->dilewati)->toBe(1)
        ->and(Tagihan::query()->count())->toBe(6)
        ->and(Tagihan::query()->where('kontrak_id', $kontrak[0]->id)->count())->toBe(1);
});

it('F-10 butir 7: tanpa kontrak yang berhak, tidak ada tagihan yang dibuat', function () {
    $hasil = Tagihan::terbitkanPeriode(periodeUji());

    expect($hasil->dibuat)->toBe(0)
        ->and($hasil->dilewati)->toBe(0)
        ->and($hasil->kontrakBerhak())->toBe(0)
        ->and(Tagihan::query()->count())->toBe(0);
});

it('F-10 butir 4: kontrak yang sudah berakhir tidak mendapat tagihan baru', function () {
    $kontrak = buatKontrakUji('01', 'Penghuni 01', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-01-01']);
    $kontrak->akhiri(CarbonImmutable::parse('2026-10-15'));

    $hasil = Tagihan::terbitkanPeriode(periodeUji());

    expect($hasil->dibuat)->toBe(0)
        ->and(Tagihan::query()->count())->toBe(0);
});

it('ditolak selama tanggal jatuh tempo belum diatur', function () {
    enamKontrakAktif();
    PengaturanOperasional::aktif()->update(['tanggal_jatuh_tempo' => null]);

    expect(fn () => Tagihan::terbitkanPeriode(periodeUji()))
        ->toThrow(PenerbitanTagihanDitolak::class, 'Atur tanggal jatuh tempo di Pengaturan dulu.')
        ->and(Tagihan::query()->count())->toBe(0);
});

it('[ASUMSI] kontrak yang mulai setelah tanggal jatuh tempo bulan itu mendapat jatuh tempo = tanggal mulai', function () {
    buatKontrakUji('01', 'Penghuni 01', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-01-01']);
    $baru = buatKontrakUji('02', 'Penghuni 02', ['tanggal_mulai' => '2026-11-20', 'tanggal_berakhir' => '2027-11-19']);

    Tagihan::terbitkanPeriode(periodeUji());

    expect(DB::table('tagihan')->where('kontrak_id', $baru->id)->value('jatuh_tempo'))->toBe('2026-11-20')
        ->and(DB::table('tagihan')->where('kontrak_id', '<>', $baru->id)->value('jatuh_tempo'))->toBe('2026-11-10');
});

it('[ASUMSI] periode di luar 12 bulan lalu sampai 1 bulan depan ditolak', function (string $periode, bool $diterima) {
    buatKontrakUji('01', 'Penghuni 01', ['tanggal_mulai' => '2020-01-01', 'tanggal_berakhir' => '2030-01-01']);

    if ($diterima) {
        expect(Tagihan::terbitkanPeriode(periodeUji($periode))->dibuat)->toBe(1);

        return;
    }

    expect(fn () => Tagihan::terbitkanPeriode(periodeUji($periode)))->toThrow(PenerbitanTagihanDitolak::class)
        ->and(Tagihan::query()->count())->toBe(0);
})->with([
    'bulan depan' => ['2026-12-01', true],
    'dua bulan lagi' => ['2027-01-01', false],
    '12 bulan lalu' => ['2025-11-01', true],
    '13 bulan lalu' => ['2025-10-01', false],
    'salah tahun' => ['2020-11-01', false],
]);

it('F-10 butir 6: tagihan baru terbaca lewat milikPenghuni untuk penghuni itu dan tidak untuk penghuni lain', function () {
    $kontrak = enamKontrakAktif();
    Tagihan::terbitkanPeriode(periodeUji());

    $pemilik = Penghuni::query()->findOrFail($kontrak[0]->penghuni_id);
    $lain = Penghuni::query()->findOrFail($kontrak[1]->penghuni_id);

    $milik = Tagihan::query()->milikPenghuni($pemilik)->get();

    expect($milik)->toHaveCount(1)
        ->and($milik->first()->kontrak_id)->toBe($kontrak[0]->id)
        ->and($milik->first()->status_tagihan)->toBe(StatusTagihan::BelumDibayar)
        ->and(Tagihan::query()->milikPenghuni($lain)->pluck('kontrak_id')->all())->toBe([$kontrak[1]->id]);
});

it('tidak mengubah kontrak dan tidak membuat baris pembayaran atau sinkronisasi_erp', function () {
    enamKontrakAktif();
    $sebelum = Kontrak::query()->orderBy('id')->get()->map->getAttributes()->all();

    Tagihan::terbitkanPeriode(periodeUji());

    expect(Kontrak::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($sebelum)
        ->and(DB::table('sinkronisasi_erp')->count())->toBe(0)
        ->and(DB::table('pembayaran')->count())->toBe(0);
});

it('pratinjau memberi angka yang sama dengan penerbitan tanpa menyimpan apa pun', function () {
    enamKontrakAktif();

    $pratinjau = Tagihan::pratinjauPeriode(periodeUji());

    expect(Tagihan::query()->count())->toBe(0)
        ->and($pratinjau->dibuat)->toBe(6)
        ->and($pratinjau->jatuhTempo->toDateString())->toBe('2026-11-10');

    $hasil = Tagihan::terbitkanPeriode(periodeUji());

    expect($hasil->dibuat)->toBe($pratinjau->dibuat)
        ->and($hasil->totalNominal)->toBe($pratinjau->totalNominal)
        ->and($hasil->tidakPenuh)->toBe($pratinjau->tidakPenuh);
});

it('strategi prorata mengubah nominal kontrak yang tidak penuh sebulan, strategi penuh tidak', function () {
    buatKontrakUji('01', 'Penghuni 01', ['tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2027-01-01', 'tarif_bulanan' => 1500000]);
    $baru = buatKontrakUji('02', 'Penghuni 02', ['tanggal_mulai' => '2026-11-21', 'tanggal_berakhir' => '2027-11-20', 'tarif_bulanan' => 1500000]);

    config(['kirana.tagihan.parsial' => 'penuh']);
    $penuh = Tagihan::pratinjauPeriode(periodeUji());

    config(['kirana.tagihan.parsial' => 'prorata']);
    $prorata = Tagihan::pratinjauPeriode(periodeUji());

    expect($penuh->totalNominal)->toBe(3000000)
        ->and($penuh->tidakPenuh)->toBe(1)
        ->and($prorata->totalNominal)->toBe(2000000)
        ->and($prorata->tidakPenuh)->toBe(1);

    Tagihan::terbitkanPeriode(periodeUji());

    expect((int) Tagihan::query()->where('kontrak_id', $baru->id)->value('nominal'))->toBe(500000)
        ->and(DB::table('tagihan')->where('kontrak_id', $baru->id)->value('jatuh_tempo'))->toBe('2026-11-21');
});

it('aksi terbitkan: hanya pengelola yang boleh; pemilik, penghuni, dan vendor ditolak tanpa tagihan baru', function (string $peran) {
    enamKontrakAktif();
    $pengguna = User::factory()->{$peran}()->create();

    $komponen = Livewire::actingAs($pengguna)->test('pages::tagihan.index');

    // Mount sudah menolak penghuni dan vendor; pemilik masuk tetapi aksinya ditolak.
    if ($peran !== 'pemilik') {
        $komponen->assertForbidden();
    } else {
        $komponen->set('periodeTerbit', '2026-11')->call('terbitkan')->assertForbidden();

        Livewire::actingAs($pengguna)->test('pages::tagihan.index')->call('bukaTerbit')->assertForbidden();
    }

    expect(Tagihan::query()->count())->toBe(0);
})->with(['pemilik', 'penghuni', 'vendor']);

it('aksi terbitkan oleh pengelola: toast hasil, pindah ke periode yang diterbitkan, tagihan tersimpan', function () {
    enamKontrakAktif();
    $pengelola = User::factory()->pengelola()->create();

    Livewire::actingAs($pengelola)->test('pages::tagihan.index')
        ->call('bukaTerbit')
        ->assertSet('periodeTerbit', '2026-11')
        ->set('periodeTerbit', '2026-11')
        ->call('terbitkan')
        ->assertDispatched('toast-show', toastTagihan('6 tagihan November 2026 diterbitkan.'))
        ->assertSet('periode', '2026-11');

    expect(Tagihan::query()->count())->toBe(6);

    Livewire::actingAs($pengelola)->test('pages::tagihan.index')
        ->call('bukaTerbit')
        ->set('periodeTerbit', '2026-11')
        ->call('terbitkan')
        ->assertDispatched('toast-show', toastTagihan('Semua kontrak sudah punya tagihan November 2026. 6 dilewati.'));

    expect(Tagihan::query()->count())->toBe(6);
});

it('toast: sebagian dibuat sebagian dilewati, dan tidak ada kontrak aktif', function () {
    $kontrak = enamKontrakAktif();
    Tagihan::factory()->create(['kontrak_id' => $kontrak[0]->id, 'periode' => '2026-11-01', 'jatuh_tempo' => '2026-11-10']);
    $pengelola = User::factory()->pengelola()->create();

    Livewire::actingAs($pengelola)->test('pages::tagihan.index')
        ->call('bukaTerbit')
        ->set('periodeTerbit', '2026-11')
        ->call('terbitkan')
        ->assertDispatched('toast-show', toastTagihan('5 tagihan November 2026 diterbitkan. 1 dilewati karena sudah ada.'));

    Livewire::actingAs($pengelola)->test('pages::tagihan.index')
        ->call('bukaTerbit')
        ->set('periodeTerbit', '2025-12')
        ->call('terbitkan')
        ->assertDispatched('toast-show', toastTagihan('Tidak ada kontrak aktif pada Desember 2025. Tidak ada tagihan yang dibuat.'));
});

it('aksi terbitkan memvalidasi ulang periode dari browser: format dan rentang', function () {
    enamKontrakAktif();
    $pengelola = User::factory()->pengelola()->create();

    Livewire::actingAs($pengelola)->test('pages::tagihan.index')
        ->call('bukaTerbit')
        ->set('periodeTerbit', 'bukan-periode')
        ->call('terbitkan')
        ->assertHasErrors('periodeTerbit')
        ->set('periodeTerbit', '2020-01')
        ->call('terbitkan')
        ->assertHasErrors('periodeTerbit');

    expect(Tagihan::query()->count())->toBe(0);
});

it('aksi terbitkan ditolak dengan toast saat tanggal jatuh tempo belum diatur', function () {
    enamKontrakAktif();
    PengaturanOperasional::aktif()->update(['tanggal_jatuh_tempo' => null]);
    $pengelola = User::factory()->pengelola()->create();

    Livewire::actingAs($pengelola)->test('pages::tagihan.index')
        ->call('bukaTerbit')
        ->assertDispatched('toast-show', toastTagihan('Atur tanggal jatuh tempo di Pengaturan dulu.'))
        ->set('periodeTerbit', '2026-11')
        ->call('terbitkan')
        ->assertDispatched('toast-show', toastTagihan('Atur tanggal jatuh tempo di Pengaturan dulu.'));

    expect(Tagihan::query()->count())->toBe(0);
});
