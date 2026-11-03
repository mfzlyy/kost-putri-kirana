<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route menurut peran: ->middleware('peran:pengelola,pemilik').
 * Parameter dicocokkan dengan nilai enum PeranPengguna.
 */
class PastikanPeran
{
    public const PESAN_DITOLAK = 'Anda tidak memiliki akses ke halaman ini.';

    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->peran->value, $peran, true),
            403,
            self::PESAN_DITOLAK,
        );

        return $next($request);
    }
}
