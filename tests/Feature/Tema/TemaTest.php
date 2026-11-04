<?php

use App\Models\PengaturanOperasional;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

function renderStatus(string $jenis): string
{
    return Blade::render('<x-status :jenis="$jenis">Teks status</x-status>', ['jenis' => $jenis]);
}

it('halaman login dan halaman aplikasi hanya mode terang tanpa skrip appearance', function () {
    PengaturanOperasional::factory()->create();

    $login = $this->get(route('login'))->assertOk();
    $kamar = $this->actingAs(User::factory()->pengelola()->create())->get('/kamar')->assertOk();

    foreach ([$login, $kamar] as $halaman) {
        $halaman->assertDontSee('class="dark"', false)
            ->assertDontSee('flux.appearance', false)
            ->assertSee('<meta name="color-scheme" content="light"', false)
            ->assertSee('<link rel="icon" href="/favicon.svg"', false);
    }
});

it('berkas favicon lama tidak ada lagi', function () {
    expect(file_exists(public_path('favicon.svg')))->toBeTrue()
        ->and(file_exists(public_path('favicon.ico')))->toBeFalse()
        ->and(file_exists(public_path('apple-touch-icon.png')))->toBeFalse();
});

it('x-status merender ikon dan teks untuk keenam jenis', function (string $jenis, string $warna) {
    $html = renderStatus($jenis);

    expect($html)->toContain('<svg')
        ->and($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('Teks status')
        ->and($html)->toContain($warna)
        ->and($html)->not->toContain('rounded-full');
})->with([
    'ok' => ['ok', 'text-status-ok'],
    'tunggu' => ['tunggu', 'text-status-wait'],
    'belum' => ['belum', 'text-status-none'],
    'kosong' => ['kosong', 'text-status-none'],
    'telat' => ['telat', 'text-status-late'],
    'gagal' => ['gagal', 'text-status-late'],
]);

it('x-status dengan jenis tak dikenal melempar InvalidArgumentException', function () {
    try {
        renderStatus('tidak-ada');
    } catch (Throwable $galat) {
        while ($galat->getPrevious() !== null) {
            $galat = $galat->getPrevious();
        }

        expect($galat)->toBeInstanceOf(InvalidArgumentException::class);

        return;
    }

    $this->fail('Komponen seharusnya melempar exception.');
});

it('sidebar pengelola menandai item aktif dengan aria-current di /kamar', function () {
    PengaturanOperasional::factory()->create();

    $html = $this->actingAs(User::factory()->pengelola()->create())->get('/kamar')->assertOk()->getContent();

    expect($html)->toMatch('/<a\s+href="[^"]*\/kamar"\s+wire:navigate\s+aria-current="page"/')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});
