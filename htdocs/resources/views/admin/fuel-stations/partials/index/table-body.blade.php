<tbody>
    @forelse ($fuelStations as $fuelStation)
        <tr>
            <td>{{ $fuelStation->name }}</td>
            <td>{{ $fuelStation->type->label() }} · {{ $fuelStation->product->label() }}</td>
            <td>{{ $fuelStation->party?->name ?? '—' }}</td>
            <td>{{ $fuelStation->sites->pluck('name')->join(', ') ?: '—' }}</td>
            <td class="panel-tutar">
                @if ($fuelStation->isTank())
                    Ortalama maliyet
                @else
                    <x-ui.money :value="$fuelStation->purchase_price" :decimals="4" suffix="TL/lt" />
                @endif
            </td>
            <td class="panel-tutar"><x-ui.money :value="$fuelStation->unit_price" :decimals="4" suffix="TL/lt" /></td>
            <td><x-ui.active-badge :active="$fuelStation->active" /></td>
            <x-table.actions
                :show="route('admin.fuel-stations.show', $fuelStation)"
                :edit="auth()->user()->can('edit-fuel-station') ? route('admin.fuel-stations.edit', $fuelStation) : null"
                :delete="auth()->user()->can('delete-fuel-station') ? route('admin.fuel-stations.destroy', $fuelStation) : null"
                confirm="Yakıt kaynağı silinsin mi?"
                :confirm-text="$fuelStation->name.' listeden kaldırılacak. Geçmiş yakıt fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="8" icon="fa-solid fa-gas-pump" title="Henüz yakıt kaynağı yok." text="Yakıt fişlerinde seçmek için istasyon ya da depo ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.fuel-stations.create')">Yeni kaynak</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="8" :reset="route('admin.fuel-stations.index')" />
        @endif
    @endforelse
</tbody>
