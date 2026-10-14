<div class="panel-liste-bar">
    <x-search.form :action="route('admin.examples.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Başlık, e-posta, telefon…" maxlength="100" />
        <x-search.select name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" />
        <x-search.select name="shift" label="Vardiya" placeholder="Tüm vardiyalar" :options="\App\Enums\ExampleShift::options()" :value="$filters['shift'] ?? null" />

        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        @if (! empty($filters))
            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.examples.index')">Temizle</x-ui.button>
        @endif
        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.examples.create')">Yeni kayıt</x-ui.button>
    </x-search.form>
</div>
