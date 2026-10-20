<div class="panel-liste-bar">
    <x-search.form :action="route('admin.vehicles.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Plaka veya şoför" maxlength="100" />
        <x-search.select searchable name="owner" label="Araç sahibi" placeholder="Tüm sahipler" :options="$options['owners']" :value="$filters['owner'] ?? null" />
        <x-search.select searchable hide-search name="class" label="Sınıf" placeholder="Tüm sınıflar" :options="$options['classes']" :value="$filters['class'] ?? null" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.vehicles.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.vehicles.create')">Yeni araç</x-ui.button>
    </x-search.form>
</div>
