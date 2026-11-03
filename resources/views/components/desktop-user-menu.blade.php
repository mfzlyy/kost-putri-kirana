@php($pengguna = auth()->user())

<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="$pengguna->nama"
        :initials="$pengguna->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="$pengguna->nama"
                :initials="$pengguna->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $pengguna->nama }}</flux:heading>
                <flux:text class="truncate">{{ $pengguna->email }}</flux:text>
                <flux:text class="truncate text-xs">{{ $pengguna->peran->label() }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('security.edit')" icon="key" wire:navigate>
                Ganti Password
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    Keluar
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
