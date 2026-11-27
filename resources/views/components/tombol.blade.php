{{--
    Tombol aksi (docs/design/DESIGN.md, bagian Components). Semua tombol aksi di semua halaman dirender di sini,
    supaya view tidak menulis kelas warna tombol sendiri.

      varian : utama | sekunder | positif | bahaya | bahaya-penuh (hanya tombol konfirmasi di dialog)
      ukuran : normal (44px: aksi halaman, form, dialog) | kecil (36px: baris tabel dan kartu daftar)
               sedang dan persegi (40px, persegi = tombol ikon 40x40): hanya x-pemilih-periode
      href   : bila diisi, dirender sebagai <a> bergaya tombol (aksi yang membuka halaman lain, misalnya Ubah)
      elemen : "span" untuk tampilan tombol di dalam <label> (pemilih berkas)

    Atribut lain (wire:click, type, disabled, data-test, class tata letak) diteruskan ke elemennya. Tombol dengan
    wire:click memudar dan tidak bisa diklik ulang selama permintaan berjalan (data-sibuk).
--}}
@props([
    'varian' => 'utama',
    'ukuran' => 'normal',
    'type' => 'button',
    'href' => null,
    'elemen' => null,
])

@php
    if (! in_array($varian, ['utama', 'sekunder', 'positif', 'bahaya', 'bahaya-penuh'], true)) {
        throw new \InvalidArgumentException("Varian tombol tidak dikenal: {$varian}");
    }

    $kelasUkuran = ['normal' => 'tombol', 'sedang' => 'tombol-sedang', 'kecil' => 'tombol-kecil', 'persegi' => 'tombol-persegi'];

    if (! array_key_exists($ukuran, $kelasUkuran)) {
        throw new \InvalidArgumentException("Ukuran tombol tidak dikenal: {$ukuran}");
    }

    $tag = $elemen ?? ($href !== null ? 'a' : 'button');
    $aksi = $attributes->get('wire:click');
    $sibuk = $tag === 'button' && filled($aksi) && ! $attributes->has('wire:loading.attr')
        ? ['wire:loading.attr' => 'data-sibuk', 'wire:target' => $aksi]
        : [];
@endphp

<{{ $tag }}
    @if ($tag === 'a') href="{{ $href }}" @elseif ($tag === 'button') type="{{ $type }}" @endif
    data-varian="{{ $varian }}"
    data-ukuran="{{ $ukuran }}"
    {{ $attributes->class([$kelasUkuran[$ukuran], 'tombol-'.$varian])->merge($sibuk) }}>{{ $slot }}</{{ $tag }}>
