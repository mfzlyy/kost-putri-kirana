<?php

namespace App\Support\Tagihan;

use Carbon\CarbonImmutable;

/**
 * Hasil (atau pratinjau) penerbitan tagihan satu periode (F-10).
 * Pada pratinjau, "dibuat" berarti tagihan yang akan dibuat dan tidak ada yang disimpan.
 */
final class HasilPenerbitan
{
    /**
     * @param  int  $dibuat  jumlah tagihan baru
     * @param  int  $dilewati  kontrak berhak yang sudah punya tagihan periode ini
     * @param  int  $totalNominal  jumlah nominal tagihan baru, rupiah
     * @param  int  $tidakPenuh  dari tagihan baru, berapa yang kontraknya tidak penuh sebulan
     * @param  CarbonImmutable|null  $jatuhTempo  jatuh tempo menurut pengaturan pada periode ini
     */
    public function __construct(
        public readonly CarbonImmutable $periode,
        public readonly int $dibuat,
        public readonly int $dilewati,
        public readonly int $totalNominal,
        public readonly int $tidakPenuh = 0,
        public readonly ?CarbonImmutable $jatuhTempo = null,
    ) {}

    /**
     * Jumlah kontrak yang berhak ditagih pada periode ini, baik yang baru maupun yang dilewati.
     */
    public function kontrakBerhak(): int
    {
        return $this->dibuat + $this->dilewati;
    }
}
