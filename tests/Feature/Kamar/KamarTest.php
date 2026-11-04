<?php

use App\Models\Fasilitas;
use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function daftarKamarSebagai(User $user, array $query = [])
{
    return Livewire::withQueryParams($query)->actingAs($user)->test('pages::kamar.index');
}

function formKamarSebagai(User $user, ?Kamar $kamar = null)
{
    return Livewire::actingAs($user)->test('pages::kamar.form', $kamar ? ['kamar' => $kamar] : []);
}

function kamarTerisi(string $nomor, string $namaPenghuni, int $tarif = 1400000): Kamar
{
    $kamar = Kamar::factory()->create(['nomor_kamar' => $nomor, 'tarif_bulanan' => $tarif]);
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => $namaPenghuni]),
    ]);
    Kontrak::factory()->aktif()->create(['kamar_id' => $kamar->id, 'penghuni_id' => $penghuni->id]);

    return $kamar;
}

it('F-05: daftar menampilkan nomor, tarif berformat rupiah, penghuni aktif, dan status yang dihitung', function () {
    kamarTerisi('01', 'Anisa Putri', 1250000);
    Kamar::factory()->create(['nomor_kamar' => '02', 'tarif_bulanan' => 1500000]);

    $this->actingAs(User::factory()->pengelola()->create())
        ->get('/kamar')
        ->assertOk()
        ->assertSeeInOrder(['01', 'Anisa Putri', 'Rp1.250.000', 'Terisi'])
        ->assertSeeInOrder(['02', 'Belum ada', 'Rp1.500.000', 'Kosong'])
        ->assertSee('2 kamar, 1 terisi.');
});

it('F-05: filter status terisi dan kosong bekerja, nilai lain dianggap Semua', function () {
    kamarTerisi('01', 'Anisa Putri');
    Kamar::factory()->create(['nomor_kamar' => '02']);
    $pengelola = User::factory()->pengelola()->create();

    expect(daftarKamarSebagai($pengelola, ['status' => 'terisi'])->instance()->daftarKamar->pluck('nomor_kamar')->all())->toBe(['01'])
        ->and(daftarKamarSebagai($pengelola, ['status' => 'kosong'])->instance()->daftarKamar->pluck('nomor_kamar')->all())->toBe(['02'])
        ->and(daftarKamarSebagai($pengelola, ['status' => 'sembarang'])->instance()->daftarKamar->pluck('nomor_kamar')->all())->toBe(['01', '02']);

    daftarKamarSebagai($pengelola)
        ->call('$set', 'filterStatus', 'kosong')
        ->assertSet('filterStatus', 'kosong');
});

it('F-05: jumlah query daftar tidak bertambah saat jumlah kamar bertambah', function () {
    $pengelola = User::factory()->pengelola()->create();

    $hitungQuery = function () use ($pengelola): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($pengelola)->get('/kamar')->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    kamarTerisi('01', 'Anisa Putri');
    $sedikit = $hitungQuery();

    foreach (['02', '03', '04', '05', '06'] as $nomor) {
        kamarTerisi($nomor, "Penghuni {$nomor}");
    }
    Kamar::factory()->count(3)->create();

    expect($hitungQuery())->toBe($sedikit);
});

it('F-05: pengelola menambah kamar dengan tarif berformat ribuan', function () {
    formKamarSebagai(User::factory()->pengelola()->create())
        ->set('nomor_kamar', '09')
        ->set('tarif_bulanan', '1.400.000')
        ->set('keterangan', 'Kamar mandi dalam')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('kamar.index'));

    $kamar = Kamar::query()->where('nomor_kamar', '09')->firstOrFail();

    expect((int) $kamar->tarif_bulanan)->toBe(1400000)
        ->and($kamar->keterangan)->toBe('Kamar mandi dalam');
});

it('F-05: nomor kamar ganda ditolak dengan pesan yang benar', function () {
    Kamar::factory()->create(['nomor_kamar' => '05']);

    formKamarSebagai(User::factory()->pengelola()->create())
        ->set('nomor_kamar', '05')
        ->set('tarif_bulanan', '1400000')
        ->call('simpan')
        ->assertHasErrors(['nomor_kamar'])
        ->assertSee('Nomor kamar 05 sudah dipakai.');

    expect(Kamar::query()->where('nomor_kamar', '05')->count())->toBe(1);
});

it('F-05: tarif nol dan teks tanpa angka ditolak', function (string $tarif) {
    formKamarSebagai(User::factory()->pengelola()->create())
        ->set('nomor_kamar', '09')
        ->set('tarif_bulanan', $tarif)
        ->call('simpan')
        ->assertHasErrors(['tarif_bulanan'])
        ->assertSee('Isi tarif dalam rupiah, lebih dari 0.');

    expect(Kamar::query()->where('nomor_kamar', '09')->exists())->toBeFalse();
})->with(['0', 'abc', '']);

it('F-05: mengubah kamar tidak mengubah nomor unik miliknya sendiri dan tidak menyentuh tarif kontrak', function () {
    $kamar = kamarTerisi('05', 'Maya Sari', 1400000);
    $kontrak = $kamar->kontrakAktif()->firstOrFail();

    formKamarSebagai(User::factory()->pengelola()->create(), $kamar)
        ->assertSet('nomor_kamar', '05')
        ->assertSet('tarif_bulanan', '1.400.000')
        ->assertSee('Tarif baru berlaku untuk kontrak berikutnya. Kontrak Maya Sari tetap Rp1.400.000 sampai')
        ->set('tarif_bulanan', '1.450.000')
        ->call('simpan')
        ->assertHasNoErrors();

    expect((int) $kamar->fresh()->tarif_bulanan)->toBe(1450000)
        ->and((int) $kontrak->fresh()->tarif_bulanan)->toBe(1400000);
});

it('F-05: kamar tanpa kontrak dan fasilitas bisa dihapus', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '09']);

    daftarKamarSebagai(User::factory()->pengelola()->create())
        ->call('konfirmasiHapus', $kamar->id)
        ->assertSet('idHapus', $kamar->id)
        ->call('hapus', $kamar->id)
        ->assertHasNoErrors();

    expect(Kamar::query()->whereKey($kamar->id)->exists())->toBeFalse();
});

it('F-05: kamar yang tidak boleh dihapus ditolak dengan pesan yang sesuai dan tetap ada', function (Closure $siapkan, string $pesan) {
    $kamar = $siapkan();

    daftarKamarSebagai(User::factory()->pengelola()->create())
        ->call('hapus', $kamar->id)
        ->assertSet('galatHapus', $pesan);

    expect(Kamar::query()->whereKey($kamar->id)->exists())->toBeTrue();
})->with([
    'kontrak aktif' => [
        fn () => kamarTerisi('05', 'Maya Sari'),
        'Kamar 05 masih punya kontrak aktif atas nama Maya Sari, jadi belum bisa dihapus.',
    ],
    'kontrak berakhir' => [
        function () {
            $kamar = Kamar::factory()->create(['nomor_kamar' => '07']);
            Kontrak::factory()->berakhir()->create(['kamar_id' => $kamar->id]);

            return $kamar;
        },
        'Kamar 07 punya riwayat kontrak, jadi tidak bisa dihapus agar riwayatnya tetap utuh.',
    ],
    'fasilitas' => [
        function () {
            $kamar = Kamar::factory()->create(['nomor_kamar' => '08']);
            Fasilitas::factory()->create(['kamar_id' => $kamar->id]);

            return $kamar;
        },
        'Kamar 08 masih punya data fasilitas, jadi belum bisa dihapus.',
    ],
]);

it('F-05: pemilik melihat daftar tanpa tombol tambah dan tanpa aksi', function () {
    Kamar::factory()->create(['nomor_kamar' => '01']);

    $this->actingAs(User::factory()->pemilik()->create())
        ->get('/kamar')
        ->assertOk()
        ->assertSee('01')
        ->assertDontSee('Tambah kamar')
        ->assertDontSee('>Ubah<', false)
        ->assertDontSee('>Hapus<', false)
        ->assertDontSee('hapus-kamar');
});

it('F-05: pemilik ditolak di halaman tambah dan ubah', function () {
    $kamar = Kamar::factory()->create();
    $pemilik = User::factory()->pemilik()->create();

    $this->actingAs($pemilik)->get('/kamar/tambah')->assertForbidden();
    $this->actingAs($pemilik)->get("/kamar/{$kamar->id}/ubah")->assertForbidden();
});

it('F-05: aksi di komponen yang dipanggil langsung oleh pemilik ditolak', function () {
    $kamar = Kamar::factory()->create();
    $pemilik = User::factory()->pemilik()->create();

    daftarKamarSebagai($pemilik)->call('konfirmasiHapus', $kamar->id)->assertForbidden();
    daftarKamarSebagai($pemilik)->call('hapus', $kamar->id)->assertForbidden();

    expect(Kamar::query()->whereKey($kamar->id)->exists())->toBeTrue();

    Livewire::actingAs($pemilik)->test('pages::kamar.form')->assertForbidden();
});

it('F-05: penghuni dan vendor mendapat 403 di ketiga route kamar', function (string $peran) {
    $kamar = Kamar::factory()->create();
    $user = User::factory()->{$peran}()->create();

    foreach (['/kamar', '/kamar/tambah', "/kamar/{$kamar->id}/ubah"] as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
})->with(['penghuni', 'vendor']);

it('F-05: halaman ubah menampilkan alasan hapus ditolak dan tombol hapus nonaktif', function () {
    $kamar = kamarTerisi('05', 'Maya Sari');

    formKamarSebagai(User::factory()->pengelola()->create(), $kamar)
        ->assertSee('Kamar 05 masih punya kontrak aktif atas nama Maya Sari, jadi belum bisa dihapus.');
});

it('F-05: pengelola menghapus kamar dari halaman ubah', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '09']);

    formKamarSebagai(User::factory()->pengelola()->create(), $kamar)
        ->call('hapus')
        ->assertRedirect(route('kamar.index'));

    expect(Kamar::query()->whereKey($kamar->id)->exists())->toBeFalse();
});

it('F-05: pesan sukses simpan kamar sampai ke halaman daftar', function () {
    $pengelola = User::factory()->pengelola()->create();

    formKamarSebagai($pengelola)
        ->set('nomor_kamar', '09')
        ->set('tarif_bulanan', '1.400.000')
        ->call('simpan')
        ->assertSessionHas('toast', 'Kamar 09 disimpan.')
        ->assertRedirect(route('kamar.index'));

    // Halaman tujuan membaca flash tersebut dan menampilkannya sebagai toast.
    $this->actingAs($pengelola)
        ->withSession(['toast' => 'Kamar 09 disimpan.'])
        ->get(route('kamar.index'))
        ->assertOk()
        ->assertSee('Kamar 09 disimpan.', false);
});

it('F-05: pesan sukses hapus kamar dari halaman ubah sampai ke halaman daftar', function () {
    $kamar = Kamar::factory()->create(['nomor_kamar' => '09']);

    formKamarSebagai(User::factory()->pengelola()->create(), $kamar)
        ->call('hapus')
        ->assertSessionHas('toast', 'Kamar 09 dihapus.');
});
