<div class="panel-liste-bar">
    <x-search.form :action="route('admin.banks.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Hesap, şube, IBAN, temsilci" maxlength="100" class="md:col-span-4" />
        <x-search.select searchable hide-search name="type" label="Tür" placeholder="Tüm türler" :options="\App\Enums\AccountType::options()" :value="$filters['type'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />

        <div class="flex flex-wrap gap-2 md:col-span-4">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.banks.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            @can('create-account-transfer-cari-entry')
                <x-ui.button icon="fa-solid fa-right-left" :href="route('admin.cari-entries.create', ['kind' => 'account_transfer'])" class="w-full justify-center sm:w-auto">Hesaplar arası virman</x-ui.button>
            @endcan
            <x-ui.button variant="create" icon="fa-solid fa-wallet" :href="route('admin.banks.create')" class="w-full justify-center sm:w-auto">Yeni hesap</x-ui.button>
        </div>
    </x-search.form>
</div>
