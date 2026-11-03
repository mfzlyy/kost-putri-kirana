{{-- Menerima $judul dan $kode dari route. Tidak menampilkan data apa pun. --}}
<x-layouts::app :title="$judul">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl" level="1">{{ $judul }}</flux:heading>
        <flux:subheading>Halaman ini sedang disiapkan.</flux:subheading>
        <flux:text class="text-sm">Fitur {{ $kode }}</flux:text>
    </div>
</x-layouts::app>
