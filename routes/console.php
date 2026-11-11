<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// F-07 butir 5: kontrak yang tanggal berakhirnya sudah lewat diakhiri otomatis setiap hari.
Schedule::command('kontrak:akhiri-kedaluwarsa')
    ->dailyAt('00:10')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
