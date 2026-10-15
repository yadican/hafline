<div class="panel-liste-bar">
    <x-search.form :action="route('admin.cari-entries.index')">
        <x-search.select name="kind" label="İşlem" placeholder="Tüm işlemler" :options="$kinds" :value="$filters['kind'] ?? null" />
        <x-form.input name="from" label="" type="date" :value="$filters['from'] ?? null" aria-label="Başlangıç" />
        <x-form.input name="to" label="" type="date" :value="$filters['to'] ?? null" aria-label="Bitiş" />
        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if ($filters !== [])
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.cari-entries.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.cari-entries.create')">Yeni hareket</x-ui.button>
    </x-search.form>
</div>
