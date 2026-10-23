<tbody>
    @forelse ($vehicles as $vehicle)
        <tr>
            <td><span class="panel-plate-text">{{ $vehicle->plate }}</span></td>
            <td>{{ $vehicle->currentOwnership?->party?->name ?: '—' }}</td>
            <td>{{ $vehicle->vehicleClass?->name ?: '—' }}</td>
            <td>{{ $vehicle->driver_name ?: '—' }}</td>
            <td><x-ui.active-badge :active="$vehicle->active" /></td>
            <x-table.actions
                :show="route('admin.vehicles.show', $vehicle)"
                :edit="auth()->user()->can('edit-vehicle') ? route('admin.vehicles.edit', $vehicle) : null"
                :delete="auth()->user()->can('delete-vehicle') ? route('admin.vehicles.destroy', $vehicle) : null"
                confirm="Araç silinsin mi?"
                :confirm-text="$vehicle->plate.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="6" icon="fa-solid fa-truck" title="Henüz araç yok." text="Taşıma fişlerinde plakayla seçmek için araçları ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.vehicles.create')">Yeni araç</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="6" :reset="route('admin.vehicles.index')" />
        @endif
    @endforelse
</tbody>
