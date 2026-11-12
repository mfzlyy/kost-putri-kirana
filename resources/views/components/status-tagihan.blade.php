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
            <x-status jenis="belum">Belum bayar{{ $ponsel ? ', jatuh tempo '.$jatuhTempo : '' }}</x-status>
            <a
                href="{{ route('tagihan-saya.unggah', $baris['id']) }}"
                wire:navigate
                class="-my-1.5 inline-flex min-h-11 items-center text-sm font-semibold text-primary"
            >Unggah bukti<span class="sr-only"> {{ $baris['periode']->locale('id')->translatedFormat('F Y') }}</span></a>
        </div>
        @if ($baris['alasanDitolak'] !== null)
            <span class="text-sm text-danger" data-test="alasan-ditolak">Bukti ditolak{{ $baris['ditolakPada'] ? ' '.$baris['ditolakPada']->locale('id')->translatedFormat('j M') : '' }}: {{ $baris['alasanDitolak'] }}</span>
        @endif
    @elseif ($baris['status'] === \App\Enums\StatusTagihan::MenungguVerifikasi)
        <x-status jenis="tunggu">Bukti diunggah {{ $baris['diunggahPada']?->locale('id')->translatedFormat('j M') }}, menunggu dicek</x-status>
    @else
        <x-status jenis="ok">Lunas{{ $baris['tanggalBayar'] ? ', dibayar '.$baris['tanggalBayar']->locale('id')->translatedFormat('j M') : '' }}</x-status>
    @endif

    @if ($baris['telatHari'] > 0)
        <x-status jenis="telat" data-test="telat">Telat {{ $baris['telatHari'] }} hari</x-status>
    @endif

    @if ($baris['terlambatKirim'])
        <p class="m-0 mt-1 text-[13px] text-ink-muted" data-test="bukti-setelah-jatuh-tempo">Bukti dikirim setelah jatuh tempo {{ $jatuhTempo }}, jadi tetap tercatat telat sampai diterima pengelola.</p>
    @endif
</div>
