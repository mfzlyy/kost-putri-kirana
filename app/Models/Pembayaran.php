<?php

namespace App\Models;

use App\Enums\JenisTransaksiErp;
use App\Enums\StatusSinkronisasi;
use App\Enums\StatusTagihan;
use App\Enums\StatusVerifikasi;
use App\Exceptions\PembayaranSudahDiputuskan;
use Carbon\CarbonImmutable;
use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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

    /**
     * Jalur penanda pembayaran hasil migrasi yang tidak punya berkas bukti.
     */
    public const PATH_TANPA_BUKTI = 'migrasi/tanpa-bukti';

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

    /**
     * Pengelola menerima bukti (F-13, ATB-06): pembayaran diterima, tagihan lunas, dan satu baris sinkronisasi_erp
     * berstatus menunggu dibuat dengan referensi KPK-PAY-000123. Pengiriman ke Odoo dikerjakan F-22, bukan di sini.
     *
     * @throws PembayaranSudahDiputuskan
     */
    public function terima(User $pengelola): void
    {
        $this->putuskan(function (self $pembayaran, Tagihan $tagihan) use ($pengelola) {
            $pembayaran->forceFill([
                'status_verifikasi' => StatusVerifikasi::Diterima,
                'alasan_penolakan' => null,
                'diverifikasi_oleh' => $pengelola->id,
                'diverifikasi_pada' => now(),
            ])->save();

            $tagihan->forceFill(['status_tagihan' => StatusTagihan::Lunas])->save();

            (new SinkronisasiErp)->forceFill([
                'jenis_transaksi' => JenisTransaksiErp::Pembayaran,
                'pembayaran_id' => $pembayaran->id,
                'referensi_unik' => sprintf('KPK-PAY-%06d', $pembayaran->id),
                'status_sinkronisasi' => StatusSinkronisasi::Menunggu,
            ])->save();
        });
    }

    /**
     * Pengelola menolak bukti dengan alasan (F-13, ATB-04): tagihan kembali belum dibayar supaya penghuni bisa
     * mengunggah bukti baru. Berkas bukti tidak dihapus; disimpan sebagai jejak verifikasi.
     *
     * @throws PembayaranSudahDiputuskan
     * @throws InvalidArgumentException bila alasan kosong
     */
    public function tolak(User $pengelola, string $alasan): void
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $this->putuskan(function (self $pembayaran, Tagihan $tagihan) use ($pengelola, $alasan) {
            $pembayaran->forceFill([
                'status_verifikasi' => StatusVerifikasi::Ditolak,
                'alasan_penolakan' => $alasan,
                'diverifikasi_oleh' => $pengelola->id,
                'diverifikasi_pada' => now(),
            ])->save();

            $tagihan->forceFill(['status_tagihan' => StatusTagihan::BelumDibayar])->save();
        });
    }

    /**
     * Menjalankan keputusan dalam satu transaksi: baris pembayaran dan tagihannya dikunci dan statusnya dicek ulang,
     * jadi dua keputusan bersamaan tidak bisa sama-sama berhasil.
     *
     * @param  callable(self, Tagihan): void  $ubah
     */
    private function putuskan(callable $ubah): void
    {
        try {
            DB::transaction(function () use ($ubah) {
                $pembayaran = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
                $tagihan = Tagihan::query()->whereKey($pembayaran->tagihan_id)->lockForUpdate()->firstOrFail();

                if ($pembayaran->status_verifikasi !== StatusVerifikasi::Menunggu) {
                    throw PembayaranSudahDiputuskan::sudahDiputuskan();
                }

                $ubah($pembayaran, $tagihan);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Permintaan bersamaan sudah menerima lebih dulu (uq_pembayaran_diterima, uq_sinkronisasi_pembayaran).
            // Juga unique referensi_unik di sinkronisasi_erp, yang ikut terkena bila antrean ERP sudah dibuat.
            if ($e->index === 'uq_pembayaran_diterima' || str_contains((string) $e->index, 'sinkronisasi')) {
                throw PembayaranSudahDiputuskan::sudahDiputuskan();
            }

            throw $e;
        }

        $this->refresh();
    }

    /**
     * Keterangan berkas bukti di disk privat, dari isi berkas (bukan ekstensi): tipe MIME, ekstensi, dan ukuran
     * dalam byte. Null untuk pembayaran migrasi tanpa bukti, berkas yang hilang, atau isi yang bukan JPG, PNG, atau PDF.
     *
     * @return array{mime: string, ekstensi: string, ukuran: int}|null
     */
    public function infoBerkasBukti(): ?array
    {
        $jalur = $this->bukti_bayar_path;

        if ($jalur === self::PATH_TANPA_BUKTI) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($jalur)) {
            return null;
        }

        $mime = mime_content_type($disk->path($jalur));
        $ekstensi = $mime === false ? null : (Tagihan::EKSTENSI_BUKTI[$mime] ?? null);

        if ($ekstensi === null) {
            return null;
        }

        return ['mime' => $mime, 'ekstensi' => $ekstensi, 'ukuran' => $disk->size($jalur)];
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
