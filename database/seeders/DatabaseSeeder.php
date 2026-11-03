<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PengaturanOperasionalSeeder::class,
            AkunAwalSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DataContohSeeder::class);
        }
    }
}
