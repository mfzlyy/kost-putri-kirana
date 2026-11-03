<?php

namespace App\Models;

use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TagihanFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $kontrak_id
 * @property CarbonImmutable $periode
 * @property string $nominal
 * @property CarbonImmutable $jatuh_tempo
 * @property StatusTagihan $status_tagihan
 * @property-read int $hari_terlambat
 */
class Tagihan extends Model
{
    /** @use HasFactory<TagihanFactory> */
    use HasFactory;

    protected $table = 'tagihan';

    public $timestamps = false;

    protected $fillable = [
        'kontrak_id',
        'periode',
        'nominal',
        'jatuh_tempo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periode' => 'immutable_date',
            'nominal' => 'decimal:2',
            'jatuh_tempo' => 'immutable_date',
            'status_tagihan' => StatusTagihan::class,
        ];
    }

    /**
     * Tagihan tunggakan pada $tanggal (ATB-05). Tidak disimpan, selalu dihitung:
     * belum lunas, $tanggal lewat jatuh tempo, dan tidak ada bukti berstatus menunggu
     * yang diunggah paling lambat pada tanggal jatuh tempo.
     */
    #[Scope]
    protected function tunggakan(Builder $query, ?CarbonInterface $tanggal = null): void
    {
        $zona = config('app.timezone');

        $query->where('tagihan.status_tagihan', '<>', StatusTagihan::Lunas->value)
            ->where('tagihan.jatuh_tempo', '<', self::tanggalKalender($tanggal))
            ->whereDoesntHave('pembayaran', function (Builder $pembayaran) use ($zona) {
                $pembayaran->where('pembayaran.status_verifikasi', StatusVerifikasi::Menunggu->value)
                    ->whereRaw('(pembayaran.diunggah_pada AT TIME ZONE ?)::date <= tagihan.jatuh_tempo', [$zona]);
            });
    }

    #[Scope]
    protected function milikPenghuni(Builder $query, Penghuni $penghuni): void
    {
        $query->whereHas('kontrak', fn (Builder $kontrak) => $kontrak->where('kontrak.penghuni_id', $penghuni->id));
    }

    /**
     * Logika yang sama dengan scope tunggakan(), untuk satu objek.
     */
    public function isTunggakan(?CarbonInterface $tanggal = null): bool
    {
        if ($this->status_tagihan === StatusTagihan::Lunas) {
            return false;
        }

        if (self::tanggalKalender($tanggal) <= $this->jatuh_tempo->toDateString()) {
            return false;
        }

        $zona = config('app.timezone');

        $adaBuktiTepatWaktu = $this->pembayaran()
            ->where('status_verifikasi', StatusVerifikasi::Menunggu->value)
            ->get()
            ->contains(fn (Pembayaran $pembayaran) => $pembayaran->diunggah_pada
                ->setTimezone($zona)->toDateString() <= $this->jatuh_tempo->toDateString());

        return ! $adaBuktiTepatWaktu;
    }

    /**
     * Jumlah hari sejak jatuh tempo; 0 bila bukan tunggakan.
     */
    public function hariTerlambatPada(?CarbonInterface $tanggal = null): int
    {
        if (! $this->isTunggakan($tanggal)) {
            return 0;
        }

        return (int) $this->jatuh_tempo->diffInDays(CarbonImmutable::parse(self::tanggalKalender($tanggal)));
    }

    protected function hariTerlambat(): Attribute
    {
        return Attribute::get(fn (): int => $this->hariTerlambatPada());
    }

    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'tagihan_id');
    }

    /**
     * Tanggal kalender (Y-m-d) di zona waktu aplikasi.
     */
    private static function tanggalKalender(?CarbonInterface $tanggal): string
    {
        return ($tanggal ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString();
    }
}
