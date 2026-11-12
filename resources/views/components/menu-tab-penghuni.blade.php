{{-- Menu tab bawah layar ponsel khusus penghuni (< md). Desktop memakai sidebar. --}}
<nav
    aria-label="Menu penghuni"
    class="fixed inset-x-0 bottom-0 z-20 grid h-[calc(68px+env(safe-area-inset-bottom))] grid-cols-4 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)] md:hidden"
>
    @foreach (\App\Support\MenuNavigasi::tabPenghuni() as $tab)
        @php($aktif = request()->routeIs($tab['route'], \Illuminate\Support\Str::beforeLast($tab['route'], '.').'.*'))
        <a
            href="{{ route($tab['route']) }}"
            wire:navigate
            @if ($aktif) aria-current="page" @endif
            @class([
                'flex min-h-11 flex-col items-center justify-center gap-[3px] text-[12px] font-semibold leading-none no-underline',
                'text-primary' => $aktif,
                'text-ink-muted' => ! $aktif,
            ])
        >
            <flux:icon :name="$tab['ikon']" class="size-6" />
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
