@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Ocaklar ve yükleme yerleri"
        icon="fa-solid fa-mountain"
        description="Malzemenin yüklendiği yerler. Ocağın carisi varsa malzeme satışlı seferde ocağa alış bedeli alacak yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Ocaklar ve yükleme yerleri' => null]"
    >
        <div class="panel-liste-bar">
            <x-search.form :action="route('admin.quarries.index')">
                <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Ocak / yükleme yeri adı" maxlength="100" class="md:col-span-5" />
                <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-3" />
                <div class="flex flex-wrap gap-2 md:col-span-4">
                    <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
                    @if (! empty($filters))
                        <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.quarries.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
                    @endif
                    @can('create-quarry')
                        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.quarries.create')" class="w-full justify-center sm:w-auto">Yeni ocak</x-ui.button>
                    @endcan
                </div>
            </x-search.form>
        </div>
        <x-table.list>
            <thead>
                <tr>
                    <x-table.th sort="name">Ocak / yükleme yeri</x-table.th>
                    <x-table.th sort="party">Ocak carisi</x-table.th>
                    <th class="is-status">Durum</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quarries as $quarry)
                    <tr>
                        <td>{{ $quarry->name }}</td>
                        <td>{{ $quarry->party?->name ?? 'Yok (sadece nakliye)' }}</td>
                        <td><x-ui.active-badge :active="$quarry->active" /></td>
                        <x-table.actions
                            :edit="auth()->user()->can('edit-quarry') ? route('admin.quarries.edit', $quarry) : null"
                            :delete="auth()->user()->can('delete-quarry') ? route('admin.quarries.destroy', $quarry) : null"
                            confirm="Ocak / yükleme yeri silinsin mi?"
                            :confirm-text="$quarry->name.' listeden kaldırılacak. Sefer girildiyse silme engellenir.'"
                        />
                    </tr>
                @empty
                    @if (empty($filters))
                        <x-table.empty colspan="4" icon="fa-solid fa-mountain" title="Henüz ocak veya yükleme yeri yok." text="Seferde seçmek için ocakları ve yükleme yerlerini ekleyin." />
                    @else
                        <x-table.no-results colspan="4" :reset="route('admin.quarries.index')" />
                    @endif
                @endforelse
            </tbody>
        </x-table.list>
        <x-table.pager :items="$quarries" />
    </x-panel.page>
@endsection
