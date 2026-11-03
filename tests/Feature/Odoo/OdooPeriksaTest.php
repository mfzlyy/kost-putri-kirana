<?php

use App\Console\Commands\OdooPeriksa;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Meniru Odoo yang sehat. $timpa: "model/method" => respons Http pengganti.
 */
function fakeOdooSehat(array $timpa = []): void
{
    Http::fake(function (Request $request) use ($timpa) {
        preg_match('#/json/2/(.+)/([^/]+)$#', $request->url(), $cocok);
        [, $model, $method] = $cocok;

        if (isset($timpa["{$model}/{$method}"])) {
            return $timpa["{$model}/{$method}"];
        }

        return Http::response(match (true) {
            "{$model}/{$method}" === 'res.users/context_get' => ['uid' => 5, 'lang' => 'en_US', 'tz' => 'Asia/Jakarta'],
            "{$model}/{$method}" === 'res.users/read' => [[
                'id' => 5, 'name' => 'Integrasi Web KPK', 'login' => 'integrasi@kostputrikirana.test',
                'share' => false, 'company_id' => [1, 'Kost Putri Kirana'], 'all_group_ids' => [11, 12],
            ]],
            "{$model}/{$method}" === 'res.groups/read' => [
                ['id' => 11, 'full_name' => 'Accounting / Invoicing'], ['id' => 12, 'full_name' => 'Purchase / User'],
            ],
            "{$model}/{$method}" === 'res.company/read' => [['id' => 1, 'name' => 'Kost Putri Kirana', 'currency_id' => [12, 'IDR']]],
            $method === 'fields_get' => collect(OdooPeriksa::MODEL_DIBUTUHKAN[$model])
                ->mapWithKeys(fn (string $field) => [$field => ['type' => 'char']])->all(),
            "{$model}/{$method}" === 'account.journal/search_read' => [
                ['id' => 1, 'name' => 'Sales', 'type' => 'sale'], ['id' => 2, 'name' => 'Purchases', 'type' => 'purchase'],
                ['id' => 6, 'name' => 'Bank', 'type' => 'bank'], ['id' => 7, 'name' => 'Cash', 'type' => 'cash'],
            ],
            default => [],
        });
    });
}

beforeEach(function () {
    Http::preventStrayRequests();

    config(['services.odoo' => [
        'url' => 'http://odoo.test:8069',
        'db' => 'kpk_uji',
        'api_key' => 'kunci-uji-bukan-rahasia-0123456789abcdef',
        'api_key_expires_at' => now()->addDays(60)->toDateString(),
        'timeout' => 30,
        'user_agent' => 'KostPutriKirana/1.0',
    ]]);
});

it('menghasilkan exit 0 bila semua pemeriksaan OK', function () {
    fakeOdooSehat();

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('uid=5 lang=en_US tz=Asia/Jakarta')
        ->expectsOutputToContain('Semua pemeriksaan OK.')
        ->assertExitCode(0);
});

it('hanya membaca: tidak ada panggilan create, write, atau aksi', function () {
    fakeOdooSehat();

    $this->artisan('odoo:periksa')->assertExitCode(0);

    Http::assertNotSent(fn (Request $request) => preg_match('#/(create|write|unlink|action_[a-z_]+)$#', $request->url()) === 1);
});

it('menandai GAGAL dan exit 1 bila model tidak ada', function () {
    fakeOdooSehat([
        'maintenance.request/fields_get' => Http::response([
            'name' => 'werkzeug.exceptions.NotFound',
            'message' => "the model 'maintenance.request' does not exist",
        ], 404),
    ]);

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('model tidak tersedia')
        ->expectsOutputToContain('Ada pemeriksaan yang GAGAL.')
        ->assertExitCode(1);
});

it('menandai GAGAL bila field yang dibutuhkan tidak ada di model', function () {
    fakeOdooSehat([
        'account.move/fields_get' => Http::response(['move_type' => ['type' => 'selection'], 'state' => ['type' => 'selection']]),
    ]);

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('field tidak ada: partner_id')
        ->assertExitCode(1);
});

it('menandai GAGAL bila koneksi atau API key ditolak', function () {
    fakeOdooSehat([
        'res.users/context_get' => Http::response(['name' => 'werkzeug.exceptions.Unauthorized', 'message' => 'Invalid apikey'], 401),
    ]);

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('Invalid apikey')
        ->assertExitCode(1);
});

it('menandai PERINGATAN dan exit 1 bila API key tersisa 5 hari', function () {
    fakeOdooSehat();
    config(['services.odoo.api_key_expires_at' => now()->addDays(5)->toDateString()]);

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('sisa 5 hari')
        ->expectsOutputToContain('PERINGATAN')
        ->assertExitCode(1);
});

it('menandai PERINGATAN dan exit 1 bila tanggal kedaluwarsa API key kosong', function () {
    fakeOdooSehat();
    config(['services.odoo.api_key_expires_at' => null]);

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('ODOO_API_KEY_EXPIRES_AT kosong')
        ->assertExitCode(1);
});

it('menandai PERINGATAN (bukan GAGAL) bila grup hak akses tidak dapat dibaca', function () {
    fakeOdooSehat([
        'res.groups/read' => Http::response(['name' => 'odoo.exceptions.AccessError', 'message' => 'tidak boleh'], 403),
    ]);

    $this->artisan('odoo:periksa')
        ->expectsOutputToContain('tidak dapat dibaca')
        ->assertExitCode(0);
});

it('menandai GAGAL bila mata uang perusahaan bukan IDR', function () {
    fakeOdooSehat([
        'res.company/read' => Http::response([['id' => 1, 'name' => 'Kost Putri Kirana', 'currency_id' => [2, 'USD']]]),
    ]);

    $this->artisan('odoo:periksa')->expectsOutputToContain('USD')->assertExitCode(1);
});

it('menandai GAGAL bila jurnal bank tidak ada', function () {
    fakeOdooSehat([
        'account.journal/search_read' => Http::response([
            ['id' => 1, 'name' => 'Sales', 'type' => 'sale'], ['id' => 2, 'name' => 'Purchases', 'type' => 'purchase'],
        ]),
    ]);

    $this->artisan('odoo:periksa')->assertExitCode(1);
});

it('tidak pernah mencetak API key', function () {
    fakeOdooSehat([
        'res.users/context_get' => Http::response(['name' => 'x', 'message' => 'token kunci-uji-bukan-rahasia-0123456789abcdef salah'], 401),
    ]);

    $this->artisan('odoo:periksa')
        ->doesntExpectOutputToContain('kunci-uji-bukan-rahasia-0123456789abcdef')
        ->assertExitCode(1);
});

it('odoo:uji-alur ditolak di luar environment local dan tanpa opsi --ya', function () {
    Http::fake();

    $this->artisan('odoo:uji-alur', ['--ya' => true])
        ->expectsOutputToContain('hanya boleh dijalankan saat APP_ENV=local')
        ->assertExitCode(1);

    $this->app['env'] = 'local';

    $this->artisan('odoo:uji-alur')
        ->expectsOutputToContain('--ya')
        ->assertExitCode(1);

    Http::assertNothingSent();
});
