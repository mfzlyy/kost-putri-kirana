<?php

namespace App\Support\Tagihan;

use App\Exceptions\KonfigurasiTagihanTidakValid;
use App\Models\Kontrak;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Nominal dan jatuh tempo tagihan satu kontrak untuk satu periode (F-10 butir 2 dan 5).
 * Kontrak yang tidak penuh sebulan mengikuti ISU-P01 lewat config kirana.tagihan.parsial.
 */
class PenghitungNominal
{
    public const PENUH = 'penuh';

    public const PRORATA = 'prorata';

    public function __construct(private readonly ?string $strategi = null) {}

    /**
     * Strategi yang dipakai: nilai konstruktor, atau config bila tidak diberikan.
     *
     * @throws KonfigurasiTagihanTidakValid
     */
    public function strategi(): string
    {
        $nilai = $this->strategi ?? config('kirana.tagihan.parsial');

        if (! in_array($nilai, [self::PENUH, self::PRORATA], true)) {
            throw new KonfigurasiTagihanTidakValid($nilai);
        }

        return $nilai;
    }

    /**
     * Nominal tagihan dalam rupiah bulat. Prorata dihitung dengan presisi penuh lalu dibulatkan
     * ke rupiah terdekat (setengah ke atas) di akhir.
     *
     * @throws KonfigurasiTagihanTidakValid
     */
    public function untuk(Kontrak $kontrak, CarbonInterface $periode): int
    {
        $tarif = (int) round((float) $kontrak->tarif_bulanan);

        if ($this->strategi() === self::PENUH) {
            return $tarif;
        }

        $hariBulan = $periode->daysInMonth;

        // Bilangan bulat murni: round(tarif x hari / hariBulan), setengah ke atas.
        return intdiv(2 * $tarif * $this->hariTerisi($kontrak, $periode) + $hariBulan, 2 * $hariBulan);
    }

    /**
     * Jumlah hari kalender dalam bulan $periode yang berada di rentang kontrak (tanggal mulai dan berakhir ikut dihitung).
     */
    public function hariTerisi(Kontrak $kontrak, CarbonInterface $periode): int
    {
        $awalBulan = $periode->copy()->startOfMonth()->toDateString();
        $akhirBulan = $periode->copy()->endOfMonth()->toDateString();

        $awal = max($awalBulan, $kontrak->tanggal_mulai->toDateString());
        $akhir = min($akhirBulan, $kontrak->tanggal_berakhir->toDateString());

        if ($awal > $akhir) {
            return 0;
        }

        return (int) CarbonImmutable::parse($awal)->diffInDays(CarbonImmutable::parse($akhir)) + 1;
    }

    /**
     * Kontrak menutup seluruh hari dalam bulan $periode.
     */
    public function penuhSebulan(Kontrak $kontrak, CarbonInterface $periode): bool
    {
        return $this->hariTerisi($kontrak, $periode) === $periode->daysInMonth;
    }

    /**
     * Jatuh tempo = tanggal pengaturan pada bulan $periode. Kontrak yang dimulai setelah tanggal itu
     * jatuh temponya pada tanggal mulai, supaya penghuni baru tidak langsung menunggak.
     */
    public function jatuhTempoUntuk(Kontrak $kontrak, CarbonInterface $periode, int $tanggalJatuhTempo): CarbonImmutable
    {
        $jatuhTempo = CarbonImmutable::parse($periode->copy()->startOfMonth()->toDateString())->day($tanggalJatuhTempo);

        return $kontrak->tanggal_mulai->toDateString() > $jatuhTempo->toDateString()
            ? CarbonImmutable::parse($kontrak->tanggal_mulai->toDateString())
            : $jatuhTempo;
    }
}
