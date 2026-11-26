<?php

namespace App\Models;

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Exceptions\SinkronisasiTidakBisaDikirimUlang;
use App\Jobs\KirimSinkronisasiErp;
use Carbon\CarbonImmutable;
use Database\Factories\SinkronisasiErpFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Baris menunggu yang baru dibuat, jenis apa pun, langsung diantrekan untuk dikirim ke Odoo (F-22 s.d. F-24).
     * afterCommit: job baru masuk antrean setelah transaksi pembuatnya berhasil, dan tidak pernah masuk bila
     * transaksinya dibatalkan.
     */
    protected static function booted(): void
    {
        static::created(function (self $sinkronisasi) {
            if ($sinkronisasi->status_sinkronisasi === StatusSinkronisasi::Menunggu) {
                KirimSinkronisasiErp::dispatch($sinkronisasi)->afterCommit();
            }
        });
    }

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

    /**
     * Pengelola mengirim ulang transaksi yang gagal (F-25): status kembali menunggu, pesan galat dikosongkan, dan
     * job pengirim diantrekan setelah commit. waktu_kirim tetap berisi percobaan terakhir. Tidak ada panggilan
     * Odoo di sini.
     *
     * Barisnya dikunci dan statusnya dibaca ulang, jadi dua permintaan bersamaan hanya mengantrekan satu job.
     *
     * @throws SinkronisasiTidakBisaDikirimUlang bila statusnya bukan gagal
     */
    public function kirimUlang(User $pengelola): void
    {
        $baris = DB::transaction(function (): self {
            $terkini = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($terkini->status_sinkronisasi !== StatusSinkronisasi::Gagal) {
                throw SinkronisasiTidakBisaDikirimUlang::statusBerubah();
            }

            $terkini->forceFill([
                'status_sinkronisasi' => StatusSinkronisasi::Menunggu,
                'pesan_error' => null,
            ])->save();

            return $terkini;
        });

        KirimSinkronisasiErp::dispatch($baris)->afterCommit();

        Log::info('Sinkronisasi ERP dikirim ulang.', [
            'referensi_unik' => $baris->referensi_unik,
            'pengelola_id' => $pengelola->id,
        ]);

        $this->setRawAttributes($baris->getAttributes(), true);
    }

    /**
     * Nama jenis transaksi untuk tampilan: "Pembayaran", "Biaya Perbaikan", "Pemeliharaan".
     */
    public function labelJenis(): string
    {
        return Str::title($this->jenis_transaksi->label());
    }

    /**
     * Urutan halaman Sinkronisasi: percobaan terbaru di atas, yang belum pernah dicoba di bawah, lalu ID terbaru.
     */
    #[Scope]
    protected function terbaruDicoba(Builder $query): void
    {
        $query->orderByRaw('sinkronisasi_erp.waktu_kirim desc nulls last')
            ->orderByDesc('sinkronisasi_erp.id');
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
