@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Sevk yerleri"
        icon="fa-solid fa-location-dot"
        description="Müşterinin teslim aldığı yerler; buraya giden her sefer müşterinin carisine borç yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Sevk yerleri' => null]"
    >
        <div class="panel-liste-bar">
            <x-search.form :action="route('admin.delivery-addresses.index')">
                <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="Sevk yeri adı" maxlength="100" class="md:col-span-4" />
                <x-search.select searchable name="party" label="Müşteri" placeholder="Tüm müşteriler" :options="$customers" :value="$filters['party'] ?? null" class="md:col-span-3" />
                <x-search.select searchable hide-search name="active" label="Durum" placeholder="Tüm durumlar" :options="['1' => 'Aktif', '0' => 'Pasif']" :value="$filters['active'] ?? null" class="md:col-span-2" />
                <div class="flex flex-wrap gap-2 md:col-span-3">
                    <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
                    @if (! empty($filters))
                        <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.delivery-addresses.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
                    @endif
                    @can('create-delivery-address')
                        <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.delivery-addresses.create')" class="w-full justify-center sm:w-auto">Yeni sevk yeri</x-ui.button>
                    @endcan
                </div>
            </x-search.form>
        </div>
        <x-table.list>
            <thead>
                <tr>
                    <x-table.th sort="name">Sevk yeri</x-table.th>
                    <x-table.th sort="party">Müşteri</x-table.th>
                    <th class="is-status">Durum</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($addresses as $address)
                    <tr>
                        <td>{{ $address->name }}</td>
                        <td>{{ $address->party?->name }}</td>
                        <td><x-ui.active-badge :active="$address->active" /></td>
                        <x-table.actions
                            :edit="auth()->user()->can('edit-delivery-address') ? route('admin.delivery-addresses.edit', $address) : null"
                            :delete="auth()->user()->can('delete-delivery-address') ? route('admin.delivery-addresses.destroy', $address) : null"
                            confirm="Sevk yeri silinsin mi?"
                            :confirm-text="$address->name.' listeden kaldırılacak. Sefer girildiyse silme engellenir.'"
                        />
                    </tr>
                @empty
                    @if (empty($filters))
                        <x-table.empty colspan="4" icon="fa-solid fa-location-dot" title="Henüz sevk yeri yok." text="Seferde seçmek için müşterilerin teslim yerlerini ekleyin." />
                    @else
                        <x-table.no-results colspan="4" :reset="route('admin.delivery-addresses.index')" />
                    @endif
                @endforelse
            </tbody>
        </x-table.list>
        <x-table.pager :items="$addresses" />
    </x-panel.page>
@endsection
