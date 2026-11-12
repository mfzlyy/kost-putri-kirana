{{--
    Kepala halaman (docs/design/DESIGN.md, bagian Layout): judul (h1) di kiri, aksi halaman (slot) di kanan, sejajar tengah,
    satu baris bila muat. Slot hanya untuk aksi tingkat halaman (Tambah, Buat, segmen periode dan Terbitkan);
    filter ada di baris berikutnya. $lebar: kontrol lebar (segmen periode) turun ke baris sendiri selebar layar di ponsel.
--}}
@props([
    'judul',
    'lebar' => false,
])

<header {{ $attributes->class(['flex flex-wrap items-center justify-between gap-4']) }}>
    <h1 class="teks-judul m-0 min-w-0">{{ $judul }}</h1>

    @if (! $slot->isEmpty())
        <div @class(['flex flex-wrap items-center gap-3', 'max-sm:w-full max-sm:[&>*]:flex-1' => $lebar, 'shrink-0' => ! $lebar]) data-test="aksi-halaman">
            {{ $slot }}
        </div>
    @endif
</header>
