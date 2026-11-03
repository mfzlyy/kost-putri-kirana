{{-- Pesan sengaja tetap; pesan exception dan data halaman tidak ditampilkan. --}}
<x-layouts::auth title="Akses Ditolak">
    <div class="flex flex-col items-center gap-6 text-center">
        <x-auth-header title="Akses Ditolak" description="Anda tidak memiliki akses ke halaman ini" />

        <flux:button variant="primary" href="{{ url('/beranda') }}">
            Kembali ke Beranda
        </flux:button>
    </div>
</x-layouts::auth>
