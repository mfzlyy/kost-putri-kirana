<?php

namespace App\Services\Odoo;

use RuntimeException;

/**
 * Galat dari pemanggilan Odoo. Pesannya aman disimpan di sinkronisasi_erp.pesan_error:
 * tanpa traceback, API key, atau isi header.
 */
class OdooException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusHttp = null,
        private readonly ?string $namaGalat = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Status HTTP balasan Odoo; null bila koneksi gagal atau konfigurasi belum lengkap.
     */
    public function statusHttp(): ?int
    {
        return $this->statusHttp;
    }

    /**
     * Nama galat Odoo, misalnya "odoo.exceptions.AccessError".
     */
    public function namaGalat(): ?string
    {
        return $this->namaGalat;
    }
}
