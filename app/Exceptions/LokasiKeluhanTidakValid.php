<?php

namespace App\Exceptions;

use DomainException;

/**
 * Lokasi atau fasilitas keluhan tidak cocok dengan data penghuni (F-16 butir 1).
 * $field menunjuk isian form yang menampilkan pesannya: lokasi atau fasilitas_id.
 */
class LokasiKeluhanTidakValid extends DomainException
{
    public const PESAN_TANPA_KONTRAK = 'Belum ada kontrak aktif. Pilih Area Bersama.';

    public const PESAN_FASILITAS = 'Fasilitas tidak ada di lokasi ini.';

    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public static function tanpaKontrakAktif(): self
    {
        return new self('lokasi', self::PESAN_TANPA_KONTRAK);
    }

    public static function fasilitasTidakAda(): self
    {
        return new self('fasilitas_id', self::PESAN_FASILITAS);
    }
}
