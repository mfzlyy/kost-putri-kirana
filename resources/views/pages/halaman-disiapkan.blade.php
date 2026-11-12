{{-- Menerima $judul dan $kode dari route. Tidak menampilkan data apa pun. --}}
<x-layouts::app :title="$judul">
    <x-judul-halaman :judul="$judul" />
    <p class="m-0 rounded-2xl border border-border bg-surface p-5 text-ink-muted" data-test="pesan-disiapkan">Halaman ini sedang disiapkan.</p>
</x-layouts::app>
