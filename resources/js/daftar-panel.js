/**
 * Daftar dan panel detail (Verifikasi, Keluhan kantor). Panel hanya ada di HTML bila ada item terpilih; di desktop
 * (>= 1024px) ia masuk dari kanan, ditutup dengan tombol × atau Esc, dan fokus berpindah mengikuti. Di layar lebih
 * kecil polanya tetap daftar lalu halaman detail. Dengan gerak dikurangi semua perubahan langsung.
 *
 * Komponen Livewire pemakainya punya properti layarLebar, kunci pilihan di URL (parameter), serta aksi pilih(id) dan
 * kembali().
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('daftarPanel', (parameter) => {
        const desktop = window.matchMedia('(min-width: 64rem)');
        const tanpaGerak = window.matchMedia('(prefers-reduced-motion: reduce)');
        let catatLayar = null;

        return {
            // false selama halaman dibuka, jadi panel yang datang dari URL tidak beranimasi.
            siap: false,
            menutup: false,

            init() {
                // Server memakai lebar layar untuk menentukan pilihan setelah Terima, Tolak, atau Tugaskan.
                catatLayar = () => {
                    this.$wire.layarLebar = desktop.matches;
                };
                catatLayar();
                desktop.addEventListener('change', catatLayar);
                this.$nextTick(() => {
                    this.siap = true;
                });
            },

            destroy() {
                desktop.removeEventListener('change', catatLayar);
            },

            async buka(id) {
                await this.$wire.pilih(id);

                if (desktop.matches) {
                    // Judul panel difokus supaya pembaca layar mengumumkannya.
                    this.$nextTick(() => this.$root.querySelector('#judul-panel')?.focus());
                }
            },

            async tutup() {
                const panel = this.$root.querySelector('[data-panel]');

                if (!panel || this.menutup) {
                    return;
                }

                const id = this.$wire[parameter];
                this.menutup = true;

                try {
                    if (!tanpaGerak.matches) {
                        panel.classList.remove('panel-geser');
                        panel.classList.add('panel-keluar');
                        await new Promise((selesai) => setTimeout(selesai, 200));
                    }

                    await this.$wire.kembali();
                    // Fokus kembali ke kartu yang tadi dipilih.
                    this.$nextTick(() => this.$root.querySelector(`[data-item="${id}"]`)?.focus());
                } finally {
                    panel.classList.remove('panel-keluar');
                    this.menutup = false;
                }
            },

            tekanEsc(peristiwa) {
                // Esc di dalam dialog hanya menutup dialog.
                if (desktop.matches && !peristiwa.defaultPrevented && !document.querySelector('dialog[open]')) {
                    this.tutup();
                }
            },
        };
    });
});
