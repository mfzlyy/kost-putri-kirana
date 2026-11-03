<?php

namespace App\Models;

use App\Enums\StatusVerifikasi;
use Carbon\CarbonImmutable;
use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tagihan_id
 * @property CarbonImmutable $tanggal_bayar
 * @property string $nominal_bayar
 * @property string $bukti_bayar_path
 * @property CarbonImmutable $diunggah_pada
 * @property StatusVerifikasi $status_verifikasi
 * @property string|null $alasan_penolakan
 * @property int|null $diverifikasi_oleh
 * @property CarbonImmutable|null $diverifikasi_pada
 */
class Pembayaran extends Model
{
    /** @use HasFactory<PembayaranFactory> */
    use HasFactory;

    protected $table = 'pembayaran';

    public $timestamps = false;

    protected $fillable = [
        'tagihan_id',
        'tanggal_bayar',
        'nominal_bayar',
        'bukti_bayar_path',
    ];

    /**
     * Jalur berkas bukti tidak ikut serialisasi; berkas hanya dibuka lewat route yang memeriksa Policy.
     *
     * @var list<string>
     */
    protected $hidden = [
        'bukti_bayar_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'immutable_date',
            'nominal_bayar' => 'decimal:2',
            'diunggah_pada' => 'immutable_datetime',
            'status_verifikasi' => StatusVerifikasi::class,
            'diverifikasi_pada' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function menunggu(Builder $query): void
    {
        $query->where('pembayaran.status_verifikasi', StatusVerifikasi::Menunggu->value);
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'tagihan_id');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
