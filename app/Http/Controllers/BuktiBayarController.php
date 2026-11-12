<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Membuka berkas bukti bayar dari disk privat (F-13 butir 2). Tidak ada URL publik atau signed URL:
 * setiap pembukaan diperiksa Policy dan tipe isi berkas dideteksi ulang di server.
 */
class BuktiBayarController extends Controller
{
    public function tampil(Pembayaran $pembayaran): Response
    {
        Gate::authorize('view', $pembayaran);

        $berkas = $pembayaran->infoBerkasBukti();

        abort_if($berkas === null, 404);

        $kamar = $pembayaran->tagihan->kontrak->kamar;
        $nama = sprintf(
            'bukti-%s-kamar-%s.%s',
            $pembayaran->tagihan->periode->format('Y-m'),
            Str::of((string) $kamar?->nomor_kamar)->replaceMatches('/[^A-Za-z0-9]+/', '')->whenEmpty(fn () => str('x')),
            $berkas['ekstensi'],
        );

        $header = [
            'Content-Type' => $berkas['mime'],
            'Content-Disposition' => 'inline; filename="'.$nama.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        // Gambar di dokumen ber-sandbox tetap tampil. PDF tidak diberi header ini karena Chrome tidak merender
        // PDF di dokumen ber-sandbox; tipenya sudah diperiksa saat diunggah dan dideteksi ulang di sini.
        if ($berkas['ekstensi'] !== 'pdf') {
            $header['Content-Security-Policy'] = 'sandbox';
        }

        return response(Storage::disk('local')->get($pembayaran->bukti_bayar_path), 200, $header);
    }
}
