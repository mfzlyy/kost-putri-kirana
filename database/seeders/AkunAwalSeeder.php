<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AkunAwalSeeder extends Seeder
{
    private const PANJANG_PASSWORD_MINIMAL = 12;

    /**
     * Buat atau perbarui akun pemilik dan pengelola dari variabel SEED_* di .env (ASM-05).
     * Tidak ada password bawaan di kode.
     */
    public function run(): void
    {
        $akun = [
            PeranPengguna::Pemilik->value => $this->ambilKredensial('pemilik', 'SEED_PEMILIK'),
            PeranPengguna::Pengelola->value => $this->ambilKredensial('pengelola', 'SEED_PENGELOLA'),
        ];

        foreach ($akun as $peran => $kredensial) {
            User::query()->updateOrCreate(
                ['email' => $kredensial['email']],
                [
                    'nama' => $kredensial['nama'],
                    'password_hash' => $kredensial['password'],
                    'peran' => $peran,
                    'status_akun' => StatusAkun::Aktif,
                ],
            );
        }
    }

    /**
     * @return array{nama: string, email: string, password: string}
     */
    private function ambilKredensial(string $kunci, string $awalan): array
    {
        $nama = trim((string) config("seeder.{$kunci}.nama"));
        $email = trim((string) config("seeder.{$kunci}.email"));
        $password = (string) config("seeder.{$kunci}.password");

        if ($nama === '') {
            throw new RuntimeException("Variabel {$awalan}_NAMA di .env belum diisi.");
        }

        if ($email === '') {
            throw new RuntimeException("Variabel {$awalan}_EMAIL di .env belum diisi.");
        }

        if (mb_strlen($password) < self::PANJANG_PASSWORD_MINIMAL) {
            throw new RuntimeException(
                "Variabel {$awalan}_PASSWORD di .env belum diisi atau kurang dari ".self::PANJANG_PASSWORD_MINIMAL.' karakter.'
            );
        }

        return ['nama' => $nama, 'email' => $email, 'password' => $password];
    }
}
