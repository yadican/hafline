<div class="panel-liste-bar">
    <x-search.form :action="route('admin.vehicle-owners.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Ad, soyad, telefon" maxlength="100" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.vehicle-owners.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.vehicle-owners.create')">Yeni araç sahibi</x-ui.button>
    </x-search.form>
</div>
