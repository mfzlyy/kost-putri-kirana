<?php

use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const KUNCI_UJI = 'kunci-uji-bukan-rahasia-0123456789abcdef';

beforeEach(function () {
    Http::preventStrayRequests();

    config(['services.odoo' => [
        'url' => 'http://odoo.test:8069',
        'db' => 'kpk_uji',
        'api_key' => KUNCI_UJI,
        'api_key_expires_at' => now()->addDays(60)->toDateString(),
        'timeout' => 30,
        'user_agent' => 'KostPutriKirana/1.0',
    ]]);
});

it('memanggil POST /json/2/{model}/{method} dengan header bearer, basis data, dan user agent', function () {
    Http::fake(['*' => Http::response([['id' => 7, 'name' => 'Penghuni #9001']])]);

    $hasil = app(OdooClient::class)->panggil('res.partner', 'search_read', [
        'domain' => [['ref', '=', 'PNH-9001']],
        'fields' => ['name'],
    ]);

    expect($hasil)->toBe([['id' => 7, 'name' => 'Penghuni #9001']]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://odoo.test:8069/json/2/res.partner/search_read'
        && $request->hasHeader('Authorization', 'Bearer '.KUNCI_UJI)
        && $request->hasHeader('X-Odoo-Database', 'kpk_uji')
        && $request->hasHeader('User-Agent', 'KostPutriKirana/1.0')
        && $request->hasHeader('Accept', 'application/json')
        && $request->data() === ['domain' => [['ref', '=', 'PNH-9001']], 'fields' => ['name']]);
});

it('mengirim body kosong sebagai objek JSON {} dan tidak menambah context bila kosong', function () {
    Http::fake(['*' => Http::response(['uid' => 5])]);

    app(OdooClient::class)->panggil('res.users', 'context_get');

    Http::assertSent(fn (Request $request) => $request->body() === '{}');
});

it('mengirim context bila diisi', function () {
    Http::fake(['*' => Http::response([])]);

    app(OdooClient::class)->panggil('account.payment.register', 'action_create_payments', ['ids' => [3]], [
        'active_model' => 'account.move',
        'active_ids' => [10],
        'dont_redirect_to_payments' => true,
    ]);

    Http::assertSent(fn (Request $request) => $request->data() === [
        'ids' => [3],
        'context' => ['active_model' => 'account.move', 'active_ids' => [10], 'dont_redirect_to_payments' => true],
    ]);
});

it('menyusun argumen bernama untuk helper searchRead, create, write, read, fieldsGet, dan aksi', function () {
    Http::fake([
        '*/create' => Http::response([42]),
        '*' => Http::response([]),
    ]);
    $odoo = app(OdooClient::class);

    $odoo->searchRead('account.move', [['ref', '=', 'KPK-PAY-000123']], ['state'], limit: 1, order: 'id');
    expect($odoo->create('res.partner', ['name' => 'Penghuni #1', 'ref' => 'PNH-1']))->toBe(42);
    $odoo->write('maintenance.request', [5], ['stage_id' => 3]);
    $odoo->read('account.move', [1], ['state']);
    $odoo->fieldsGet('res.partner', ['type', 'required']);
    $odoo->aksi('account.move', 'action_post', [9]);

    $diharapkan = [
        '/json/2/account.move/search_read' => ['domain' => [['ref', '=', 'KPK-PAY-000123']], 'fields' => ['state'], 'limit' => 1, 'order' => 'id'],
        '/json/2/res.partner/create' => ['vals_list' => [['name' => 'Penghuni #1', 'ref' => 'PNH-1']]],
        '/json/2/maintenance.request/write' => ['ids' => [5], 'vals' => ['stage_id' => 3]],
        '/json/2/account.move/read' => ['ids' => [1], 'fields' => ['state']],
        '/json/2/res.partner/fields_get' => ['attributes' => ['type', 'required']],
        '/json/2/account.move/action_post' => ['ids' => [9]],
    ];

    foreach ($diharapkan as $path => $body) {
        Http::assertSent(fn (Request $request) => parse_url($request->url(), PHP_URL_PATH) === $path && $request->data() === $body);
    }
});

it('mengubah error 401 menjadi OdooException tanpa API key', function () {
    Http::fake(['*' => Http::response([
        'name' => 'werkzeug.exceptions.Unauthorized',
        'message' => 'Invalid apikey',
        'debug' => 'Traceback (most recent call last): ... Authorization: bearer '.KUNCI_UJI,
    ], 401)]);

    try {
        app(OdooClient::class)->panggil('res.users', 'context_get');
        $this->fail('OdooException seharusnya dilempar.');
    } catch (OdooException $e) {
        expect($e->getMessage())
            ->toBe('Odoo menjawab HTTP 401: werkzeug.exceptions.Unauthorized: Invalid apikey')
            ->not->toContain(KUNCI_UJI)
            ->not->toContain('Traceback')
            ->and($e->statusHttp())->toBe(401)
            ->and($e->namaGalat())->toBe('werkzeug.exceptions.Unauthorized');
    }
});

it('mengubah error 500 menjadi OdooException tanpa traceback dan tanpa API key', function () {
    Http::fake(['*' => Http::response([
        'name' => 'builtins.ValueError',
        'message' => "Invalid field 'x' on 'account.move' (token ".KUNCI_UJI.')',
        'arguments' => ['rahasia-argumen'],
        'debug' => "Traceback (most recent call last):\n  File \"/usr/lib/python3/odoo/http.py\"",
    ], 500)]);

    try {
        app(OdooClient::class)->panggil('account.move', 'search_read', ['domain' => []]);
        $this->fail('OdooException seharusnya dilempar.');
    } catch (OdooException $e) {
        expect($e->getMessage())
            ->toContain('HTTP 500')
            ->toContain("Invalid field 'x' on 'account.move'")
            ->not->toContain(KUNCI_UJI)
            ->not->toContain('Traceback')
            ->not->toContain('rahasia-argumen')
            ->and($e->statusHttp())->toBe(500);
    }
});

it('memotong pesan AccessError yang panjang dan multi-baris menjadi satu baris pendek', function () {
    Http::fake(['*' => Http::response([
        'name' => 'odoo.exceptions.AccessError',
        'message' => "You are not allowed to access 'System Parameter' (ir.config_parameter) records.\n\n".str_repeat('Contact your administrator. ', 60),
    ], 403)]);

    try {
        app(OdooClient::class)->panggil('ir.config_parameter', 'search_read');
        $this->fail('OdooException seharusnya dilempar.');
    } catch (OdooException $e) {
        expect($e->getMessage())
            ->not->toContain("\n")
            ->toContain('odoo.exceptions.AccessError')
            ->toContain('HTTP 403')
            ->and(mb_strlen($e->getMessage()))->toBeLessThanOrEqual(503);
    }
});

it('menangani balasan error yang bukan JSON', function () {
    Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

    expect(fn () => app(OdooClient::class)->panggil('res.users', 'context_get'))
        ->toThrow(OdooException::class, 'HTTP 502');
});

it('menolak konfigurasi yang belum lengkap sebelum ada request', function (string $kunci) {
    Http::fake();
    config(["services.odoo.{$kunci}" => '']);

    expect(fn () => app(OdooClient::class)->panggil('res.users', 'context_get'))
        ->toThrow(OdooException::class, 'Konfigurasi Odoo belum lengkap');

    Http::assertNothingSent();
})->with(['url', 'db', 'api_key']);

it('mengubah timeout atau koneksi gagal menjadi OdooException tanpa API key', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds (token '.KUNCI_UJI.')')]);

    try {
        app(OdooClient::class)->panggil('res.users', 'context_get');
        $this->fail('OdooException seharusnya dilempar.');
    } catch (OdooException $e) {
        expect($e->getMessage())
            ->toContain('Odoo tidak dapat dihubungi')
            ->toContain('timed out')
            ->not->toContain(KUNCI_UJI)
            ->and($e->statusHttp())->toBeNull();
    }
});
