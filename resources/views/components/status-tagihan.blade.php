{{-- Status satu tagihan di daftar "Semua tagihan" penghuni (F-11 butir 1-3). $baris dari pages::tagihan-saya.index. --}}
@props([
    'baris',
    'ponsel' => false,
])

@php
    $jatuhTempo = $baris['jatuhTempo']->locale('id')->translatedFormat('j M');
@endphp

<div class="flex flex-col items-start gap-1">
    @if ($baris['status'] === \App\Enums\StatusTagihan::BelumDibayar)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5">
            <x-status jenis="belum">Belum Bayar{{ $ponsel ? ', '.$jatuhTempo : '' }}</x-status>
            <a
                href="{{ route('tagihan-saya.unggah', $baris['id']) }}"
                wire:navigate
                class="-my-1.5 inline-flex min-h-11 items-center text-primary teks-tombol-kecil"
            >Unggah<span class="sr-only"> {{ $baris['periode']->locale('id')->translatedFormat('F Y') }}</span></a>
        </div>
        @if ($baris['alasanDitolak'] !== null)
            <span class="teks-galat" data-test="alasan-ditolak">Ditolak{{ $baris['ditolakPada'] ? ', '.$baris['ditolakPada']->locale('id')->translatedFormat('j M') : '' }}: {{ $baris['alasanDitolak'] }}</span>
        @endif
    @elseif ($baris['status'] === \App\Enums\StatusTagihan::MenungguVerifikasi)
        <x-status jenis="tunggu">Menunggu, {{ $baris['diunggahPada']?->locale('id')->translatedFormat('j M') }}</x-status>
    @else
        <x-status jenis="ok">Lunas{{ $baris['tanggalBayar'] ? ', '.$baris['tanggalBayar']->locale('id')->translatedFormat('j M') : '' }}</x-status>
    @endif

    @if ($baris['telatHari'] > 0)
        <x-status jenis="telat" data-test="telat">Telat {{ $baris['telatHari'] }} Hari</x-status>
    @endif
</div>
