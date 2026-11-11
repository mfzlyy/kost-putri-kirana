<?php

use App\Models\Kamar;
use App\Models\Kontrak;
use App\Models\Penghuni;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Kamar bernomor $nomor yang dihuni $nama lewat kontrak aktif. $kontrak menimpa isian kontrak (tanggal, tarif, status).
 */
function buatKontrakUji(string $nomor, string $nama, array $kontrak = []): Kontrak
{
    $kamar = Kamar::query()->firstOrCreate(
        ['nomor_kamar' => $nomor],
        ['tarif_bulanan' => 1400000],
    );
    $penghuni = Penghuni::factory()->create([
        'user_id' => User::factory()->penghuni()->create(['nama' => $nama]),
    ]);

    return Kontrak::factory()->create(array_merge([
        'penghuni_id' => $penghuni->id,
        'kamar_id' => $kamar->id,
        'tarif_bulanan' => $kamar->tarif_bulanan,
    ], $kontrak));
}
