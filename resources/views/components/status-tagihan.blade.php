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
        {{-- Ponsel: tombol selebar kartu di bawah semua status (order-last); desktop: sebaris dengan status. --}}
        <div @class(['contents' => $ponsel, 'flex flex-wrap items-center gap-x-4 gap-y-2' => ! $ponsel])>
            <x-status jenis="belum">Belum Bayar{{ $ponsel ? ', '.$jatuhTempo : '' }}</x-status>
            <x-tombol varian="sekunder" ukuran="kecil" :href="route('tagihan-saya.unggah', $baris['id'])" wire:navigate :class="$ponsel ? 'order-last mt-1 self-stretch' : ''">Unggah<span class="sr-only"> {{ $baris['periode']->locale('id')->translatedFormat('F Y') }}</span></x-tombol>
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
