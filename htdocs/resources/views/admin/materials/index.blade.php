@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Malzemeler"
        icon="fa-solid fa-cubes-stacked"
        description="Nakliyede taşınan ve satılan malzemeler; seferde miktar malzemenin biriminde girilir."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Malzemeler' => null]"
    >
        <div class="panel-liste-bar">
            <x-search.form :action="route('admin.materials.index')">
                <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Malzeme adı" maxlength="100" class="md:col-span-5" />
                <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-3" />
                <div class="flex flex-wrap gap-2 md:col-span-4">
                    <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
                    @if (! empty($filters))
                        <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.materials.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
                    @endif
                    @can('create-material')
                        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.materials.create')" class="w-full justify-center sm:w-auto">Yeni malzeme</x-ui.button>
                    @endcan
                </div>
            </x-search.form>
        </div>
        <x-table.list>
            <thead>
                <tr>
                    <x-table.th sort="name">Malzeme</x-table.th>
                    <th>Birim</th>
                    <th class="is-status">Durum</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($materials as $material)
                    <tr>
                        <td>{{ $material->name }}</td>
                        <td>{{ $material->unit->label() }}</td>
                        <td><x-ui.active-badge :active="$material->active" /></td>
                        <x-table.actions
                            :edit="auth()->user()->can('edit-material') ? route('admin.materials.edit', $material) : null"
                            :delete="auth()->user()->can('delete-material') ? route('admin.materials.destroy', $material) : null"
                            confirm="Malzeme silinsin mi?"
                            :confirm-text="$material->name.' listeden kaldırılacak. Seferde kullanıldıysa silme engellenir.'"
                        />
                    </tr>
                @empty
                    @if (empty($filters))
                        <x-table.empty colspan="4" icon="fa-solid fa-cubes-stacked" title="Henüz malzeme yok." text="Seferde seçmek için kum, mıcır gibi malzemeleri ekleyin." />
                    @else
                        <x-table.no-results colspan="4" :reset="route('admin.materials.index')" />
                    @endif
                @endforelse
            </tbody>
        </x-table.list>
        <x-table.pager :items="$materials" />
    </x-panel.page>
@endsection
