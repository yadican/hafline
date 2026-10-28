@php($isPerson = $type === \App\Enums\PartyType::Person)

<div class="panel-liste-bar">
    <x-search.form :action="route($type->indexRoute())">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" :placeholder="$isPerson ? 'Ad soyad, telefon, TCKN' : 'Unvan, telefon, VKN'" maxlength="100" class="md:col-span-3" />
        <x-search.select searchable hide-search name="side" label="Taraf" placeholder="Tüm taraflar" :options="\App\Enums\PartySide::options()" :value="$filters['side'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="role" label="Rol" placeholder="Tüm roller" :options="\App\Enums\PartyRole::optionsFor($type)" :value="$filters['role'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />
        @if (! empty($tags))
            <x-search.select searchable name="tag" label="Etiket" placeholder="Tüm etiketler" :options="$tags" :value="$filters['tag'] ?? null" class="md:col-span-3" />
        @endif
        @if (! $isPerson && ! empty($groups))
            <x-search.select searchable name="group" label="Şirket grubu" placeholder="Tüm gruplar" :options="$groups" :value="$filters['group'] ?? null" class="md:col-span-3" />
        @endif

        <div class="flex flex-wrap gap-2 md:col-span-3">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route($type->indexRoute())" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            <x-ui.button variant="create" :icon="$isPerson ? 'fa-solid fa-user-plus' : 'fa-solid fa-building'" :href="route('admin.parties.create', ['type' => $type->value])" class="w-full justify-center sm:w-auto">{{ $isPerson ? 'Yeni kişi' : 'Yeni şirket' }}</x-ui.button>
        </div>
    </x-search.form>
</div>
