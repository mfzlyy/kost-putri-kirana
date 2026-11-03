<?php

namespace App\Models;

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use Carbon\CarbonImmutable;
use Database\Factories\SinkronisasiErpFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property JenisTransaksiErp $jenis_transaksi
 * @property string $referensi_unik
 * @property int|null $pembayaran_id
 * @property int|null $tiket_id
 * @property StatusSinkronisasi $status_sinkronisasi
 * @property int|null $odoo_record_id
 * @property string|null $pesan_error
 * @property CarbonImmutable|null $waktu_kirim
 */
class SinkronisasiErp extends Model
{
    /** @use HasFactory<SinkronisasiErpFactory> */
    use HasFactory;

    protected $table = 'sinkronisasi_erp';

    public $timestamps = false;

    protected $fillable = [
        'jenis_transaksi',
        'referensi_unik',
        'pembayaran_id',
        'tiket_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_transaksi' => JenisTransaksiErp::class,
            'status_sinkronisasi' => StatusSinkronisasi::class,
            'waktu_kirim' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function gagal(Builder $query): void
    {
        $query->where('sinkronisasi_erp.status_sinkronisasi', StatusSinkronisasi::Gagal->value);
    }

    #[Scope]
    protected function menunggu(Builder $query): void
    {
        $query->where('sinkronisasi_erp.status_sinkronisasi', StatusSinkronisasi::Menunggu->value);
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'pembayaran_id');
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(TiketKeluhan::class, 'tiket_id');
    }
}
