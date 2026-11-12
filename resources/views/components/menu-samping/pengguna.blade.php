{{-- Blok pengguna di bawah sidebar. Dipakai untuk semua peran, di desktop dan menu geser ponsel. --}}
@php($pengguna = auth()->user())

<div class="mt-3 flex shrink-0 flex-col gap-0.5 border-t border-primary-container/15 px-3 pt-4 pendek:pt-3">
    <span class="line-clamp-2 font-semibold text-white">{{ $pengguna->nama }}</span>
    <span class="text-[13px]">{{ $pengguna->peran->label() }}</span>

    <div class="mt-2 flex flex-col pendek:mt-1 pendek:flex-row pendek:gap-4">
        <a href="{{ route('security.edit') }}" wire:navigate class="tautan-sidebar underline">Ganti Password</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="tautan-sidebar underline" data-test="logout-button">Keluar</button>
        </form>
    </div>
</div>
