<?php

namespace App\Services\Odoo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use stdClass;

/**
 * Satu-satunya tempat yang memanggil HTTP ke Odoo (JSON-2 API).
 *
 * POST {url}/json/2/{model}/{method} dengan header Authorization: bearer {api_key}
 * dan X-Odoo-Database. Setiap panggilan berjalan dalam transaksi Odoo tersendiri.
 */
class OdooClient
{
    private const BATAS_PESAN = 500;

    /**
     * @param  array<string, mixed>  $argumen  argumen bernama method Odoo (ids, vals_list, domain, fields, ...)
     * @param  array<string, mixed>  $context
     *
     * @throws OdooException
     */
    public function panggil(string $model, string $method, array $argumen = [], array $context = []): mixed
    {
        $url = rtrim((string) config('services.odoo.url'), '/');
        $db = (string) config('services.odoo.db');
        $kunci = (string) config('services.odoo.api_key');

        if ($url === '' || $db === '' || $kunci === '') {
            throw new OdooException('Konfigurasi Odoo belum lengkap.');
        }

        if ($context !== []) {
            $argumen['context'] = $context;
        }

        // Body kosong harus berupa objek JSON ({}), bukan larik ([]).
        $body = json_encode(
            $argumen === [] ? new stdClass : $argumen,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        try {
            $respons = Http::withToken($kunci)
                ->withHeaders([
                    'X-Odoo-Database' => $db,
                    'User-Agent' => (string) config('services.odoo.user_agent'),
                ])
                ->acceptJson()
                ->withBody($body, 'application/json')
                ->timeout((int) config('services.odoo.timeout', 30))
                ->post("{$url}/json/2/{$model}/{$method}");
        } catch (ConnectionException $e) {
            throw new OdooException(
                $this->bersihkan('Odoo tidak dapat dihubungi: '.$e->getMessage(), $kunci)
            );
        }

        if ($respons->failed()) {
            throw $this->galatDariRespons($respons, $kunci);
        }

        return $respons->json();
    }

    /**
     * @param  list<mixed>  $domain
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    public function searchRead(string $model, array $domain, array $fields = [], ?int $limit = null, ?string $order = null, array $context = []): array
    {
        $argumen = ['domain' => $domain];

        if ($fields !== []) {
            $argumen['fields'] = $fields;
        }

        if ($limit !== null) {
            $argumen['limit'] = $limit;
        }

        if ($order !== null) {
            $argumen['order'] = $order;
        }

        return $this->panggil($model, 'search_read', $argumen, $context);
    }

    /**
     * Membuat satu catatan dan mengembalikan ID-nya.
     *
     * @param  array<string, mixed>  $nilai
     */
    public function create(string $model, array $nilai, array $context = []): int
    {
        $hasil = $this->panggil($model, 'create', ['vals_list' => [$nilai]], $context);

        return (int) (is_array($hasil) ? Arr::first($hasil) : $hasil);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $nilai
     */
    public function write(string $model, array $ids, array $nilai, array $context = []): mixed
    {
        return $this->panggil($model, 'write', ['ids' => $ids, 'vals' => $nilai], $context);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    public function read(string $model, array $ids, array $fields = [], array $context = []): array
    {
        $argumen = ['ids' => $ids];

        if ($fields !== []) {
            $argumen['fields'] = $fields;
        }

        return $this->panggil($model, 'read', $argumen, $context);
    }

    /**
     * @param  list<string>  $attributes
     * @return array<string, array<string, mixed>>
     */
    public function fieldsGet(string $model, array $attributes = []): array
    {
        return $this->panggil($model, 'fields_get', $attributes === [] ? [] : ['attributes' => $attributes]) ?? [];
    }

    /**
     * Memanggil method aksi pada catatan (action_post, action_create_payments, ...).
     *
     * @param  list<int>  $ids
     */
    public function aksi(string $model, string $method, array $ids, array $context = []): mixed
    {
        return $this->panggil($model, $method, ['ids' => $ids], $context);
    }

    private function galatDariRespons(Response $respons, string $kunci): OdooException
    {
        $data = $respons->json();
        $data = is_array($data) ? $data : [];

        // Hanya name dan message yang diambil; "debug" (traceback) dan "arguments" sengaja diabaikan.
        $nama = is_string($data['name'] ?? null) ? $data['name'] : null;
        $pesan = is_string($data['message'] ?? null) ? $data['message'] : null;

        $ringkas = trim(($nama !== null ? "{$nama}: " : '').($pesan ?? 'Tidak ada pesan galat dari Odoo.'));

        return new OdooException(
            $this->bersihkan("Odoo menjawab HTTP {$respons->status()}: {$ringkas}", $kunci),
            $respons->status(),
            $nama,
        );
    }

    private function bersihkan(string $teks, string $kunci): string
    {
        $teks = str_replace($kunci, '[disembunyikan]', $teks);

        return Str::limit(preg_replace('/\s+/', ' ', $teks) ?? $teks, self::BATAS_PESAN, '...');
    }
}
