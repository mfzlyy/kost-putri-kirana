{{-- Pesan sengaja tetap; pesan exception dan data halaman tidak ditampilkan. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Akses ditolak'])
    </head>
    <body class="min-h-svh bg-background text-ink antialiased">
        <main class="flex min-h-svh items-center justify-center px-5 py-12">
            <div class="flex max-w-[460px] flex-col items-start gap-5">
                <svg width="64" height="77" viewBox="0 0 40 48" aria-hidden="true">
                    <rect x="2" y="2" width="36" height="44" rx="9" fill="none" stroke="#CFCBC1" stroke-width="1.6" stroke-dasharray="3 3"/>
                    <circle cx="20" cy="9.5" r="3" fill="none" stroke="#CFCBC1" stroke-width="1.6"/>
                    <path d="M14 22h12M14 28h12" stroke="#CFCBC1" stroke-width="1.6" stroke-linecap="round"/>
                </svg>

                <h1 class="teks-judul m-0">Akses ditolak</h1>
                <p class="teks-badan m-0 text-ink-muted">Anda tidak memiliki akses ke halaman ini.</p>

                <a href="{{ url('/beranda') }}" class="tombol tombol-utama">
                    Beranda
                </a>

                <p class="m-0 teks-bantuan">Kode 403</p>
            </div>
        </main>
    </body>
</html>
