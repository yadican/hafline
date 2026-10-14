<div class="panel-liste-bar">
    <x-search.form :action="route('admin.banks.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Banka, temsilci, telefon, hesap no" maxlength="100" />
        <x-search.select name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.banks.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.banks.create')">Yeni banka</x-ui.button>
    </x-search.form>
</div>
