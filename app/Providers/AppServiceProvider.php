<?php

namespace App\Providers;

use App\Http\Middleware\PastikanAkunAktif;
use App\Http\Middleware\PastikanPeran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Aturan password awal (F-02) dan ganti password (F-01): minimal 8 karakter, huruf dan angka.
        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());
    }

    /**
     * Akun nonaktif ditolak oleh semua Policy; middleware dicek ulang pada setiap aksi Livewire.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->isAktif() ? null : false);

        Livewire::addPersistentMiddleware([
            PastikanAkunAktif::class,
            PastikanPeran::class,
        ]);
    }
}
