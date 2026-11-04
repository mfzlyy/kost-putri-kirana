<x-layouts::app.sidebar :title="$title ?? null">
    <main data-flux-main class="min-w-0 px-4 py-5 [grid-area:main] lg:px-12 lg:py-10">
        <div class="mx-auto flex w-full max-w-[1040px] flex-col gap-6">
            {{ $slot }}
        </div>
    </main>
</x-layouts::app.sidebar>
