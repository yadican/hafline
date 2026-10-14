<tbody>
    @forelse ($vehicleOwners as $vehicleOwner)
        <tr>
            <td>{{ $vehicleOwner->full_name }}</td>
            <td>{{ $vehicleOwner->phone ?: '—' }}</td>
            <td>{{ $vehicleOwner->mobile ?: '—' }}</td>
            <td>{{ $vehicleOwner->address ?: '—' }}</td>
            <td class="is-center">
                @if ($vehicleOwner->vehicles_count)
                    <a href="{{ route('admin.vehicles.index', ['owner' => $vehicleOwner->id]) }}" title="Bu sahibin araçlarını göster">{{ $vehicleOwner->vehicles_count }}</a>
                @else
                    —
                @endif
            </td>
            <x-table.actions
                :edit="route('admin.vehicle-owners.edit', $vehicleOwner)"
                :delete="route('admin.vehicle-owners.destroy', $vehicleOwner)"
                confirm="Araç sahibi silinsin mi?"
                :confirm-text="$vehicleOwner->full_name.' listeden kaldırılacak. Kayıtlı aracı varsa silme engellenir.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="6" icon="fa-solid fa-id-card" title="Henüz araç sahibi yok." text="Araçları kaydetmeden önce sahiplerini ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.vehicle-owners.create')">Yeni araç sahibi</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="6" :reset="route('admin.vehicle-owners.index')" />
        @endif
    @endforelse
</tbody>
