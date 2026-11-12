{{-- Judul halaman: h1 lebar 125%, 34px di desktop dan 26px di ponsel. Slot bawaan = aksi (kanan di desktop, di bawah judul di ponsel). --}}
@props([
    'judul',
    'deskripsi' => null,
])

<header {{ $attributes->class(['flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div class="min-w-0">
        <h1 class="m-0 text-[26px] leading-[1.1] font-[750] font-stretch-expanded tracking-[-0.01em] lg:text-[34px] lg:leading-[1.05]">{{ $judul }}</h1>
        @if (filled($deskripsi))
            <p class="mt-1.5 mb-0 text-sm text-ink-muted lg:mt-2 lg:text-[15px]" data-test="deskripsi-judul">{{ $deskripsi }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="flex flex-col gap-2 sm:flex-row lg:shrink-0">
            {{ $slot }}
        </div>
    @endif
</header>
