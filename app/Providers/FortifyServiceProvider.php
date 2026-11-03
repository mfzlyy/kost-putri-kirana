<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Login F-01: cari akun berdasarkan email (huruf kecil), periksa password, lalu status akun.
     * Pesan "nonaktif" hanya muncul bila password benar, sehingga tidak membocorkan keberadaan akun.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim((string) $request->input(Fortify::username())));
            $password = (string) $request->input('password');

            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                // Waktu proses disamakan dengan pengecekan password sungguhan.
                Hash::make($password);

                return null;
            }

            if (! Hash::check($password, $user->password_hash)) {
                return null;
            }

            if (! $user->isAktif()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('auth.inactive'),
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        // Konfirmasi password tidak dipakai (ganti password meminta password lama); rute bawaan Fortify
        // tetap ada, jadi arahkan ke beranda alih-alih menampilkan halaman yang tidak tersedia.
        Fortify::confirmPasswordView(fn () => redirect('/beranda'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)
                ->by($throttleKey)
                ->response(function (Request $request, array $headers) {
                    $pesan = trans('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60]);

                    return back()
                        ->withInput($request->only(Fortify::username()))
                        ->withErrors([Fortify::username() => $pesan]);
                });
        });
    }
}
