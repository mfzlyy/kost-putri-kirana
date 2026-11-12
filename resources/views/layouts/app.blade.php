@php
    $tanpaTabPenghuni ??= false;
    // Isi halaman penghuni diberi ruang di bawah agar tidak tertutup tab (hanya < md, saat tab tampil).
    $adaTab = ! $tanpaTabPenghuni && auth()->user()?->peran === \App\Enums\PeranPengguna::Penghuni;
@endphp
<x-layouts::app.sidebar :title="$title ?? null" :tanpa-tab-penghuni="$tanpaTabPenghuni">
    <main data-flux-main @class(['min-w-0 px-4 py-5 [grid-area:main] lg:px-12 lg:py-10', 'max-md:pb-[calc(68px+env(safe-area-inset-bottom)+20px)]' => $adaTab])>
        <div class="mx-auto flex w-full max-w-[1040px] flex-col gap-4 lg:gap-6">
            {{ $slot }}
        </div>
    </main>
</x-layouts::app.sidebar>
