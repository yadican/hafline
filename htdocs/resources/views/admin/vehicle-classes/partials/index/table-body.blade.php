<tbody>
    @forelse ($vehicleClasses as $vehicleClass)
        <tr>
            <td>{{ $vehicleClass->name }}</td>
            <td class="is-center">
                @if ($vehicleClass->vehicles_count)
                    <a href="{{ route('admin.vehicles.index', ['class' => $vehicleClass->id]) }}" title="Bu sınıftaki araçları göster">{{ $vehicleClass->vehicles_count }}</a>
                @else
                    —
                @endif
            </td>
            <td><x-ui.active-badge :active="$vehicleClass->active" /></td>
            <x-table.actions
                :edit="route('admin.vehicle-classes.edit', $vehicleClass)"
                :delete="route('admin.vehicle-classes.destroy', $vehicleClass)"
                confirm="Araç sınıfı silinsin mi?"
                :confirm-text="$vehicleClass->name.' listeden kaldırılacak. Bu sınıfta araç varsa silme engellenir.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-layer-group" title="Henüz araç sınıfı yok." text="Araçları sınıflandırmak için TIR, kamyon gibi sınıfları ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.vehicle-classes.create')">Yeni araç sınıfı</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.vehicle-classes.index')" />
        @endif
    @endforelse
</tbody>
