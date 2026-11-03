<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Setelah login, pengguna selalu masuk ke halaman utama perannya (PRD Bagian 6),
 * bukan ke URL yang tersimpan sebelumnya.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $request->session()->forget('url.intended');

        return redirect()->route($request->user()->peran->halamanUtama());
    }
}
