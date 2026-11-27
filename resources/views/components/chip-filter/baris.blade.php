{{--
    Baris chip filter (docs/design/DESIGN.md, bagian Components). Mulai 640px chip membungkus ke baris berikutnya.
    Di ponsel satu baris yang digulir menyamping tanpa scrollbar, sampai tepi layar, dengan gradasi pudar di sisi yang
    masih menyimpan chip (gaya di app.css, .baris-chip). Atribut diteruskan ke pembungkus luar (jarak, visibilitas).

    wire:ignore.self menjaga atribut data-pudar-* pasangan Alpine tidak dihapus saat Livewire memuat ulang chip.
--}}
@props([
    'label',
])

<div {{ $attributes->class('min-w-0') }}>
    <div role="group" aria-label="{{ $label }}"
        class="baris-chip max-sm:overflow-x-auto max-sm:snap-x"
        wire:ignore.self
        x-data="{
            kiri: false,
            kanan: false,
            ukur() {
                const baris = this.$root;
                this.kiri = baris.scrollLeft > 1;
                this.kanan = baris.scrollWidth - baris.clientWidth - baris.scrollLeft > 1;
            },
        }"
        x-init="$nextTick(() => {
            $root.querySelector('[aria-pressed=true]')?.scrollIntoView({ inline: 'nearest', block: 'nearest' });
            ukur();
            new ResizeObserver(() => ukur()).observe($root);
            new MutationObserver(() => ukur()).observe($root, { childList: true, characterData: true, subtree: true });
            document.fonts?.ready.then(() => ukur());
        })"
        x-on:scroll.passive="ukur()"
        x-bind:data-pudar-kiri="kiri"
        x-bind:data-pudar-kanan="kanan"
    >
        {{ $slot }}
    </div>
</div>
