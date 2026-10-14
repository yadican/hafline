<tbody>
    @forelse ($fuelStations as $fuelStation)
        <tr>
            <td>{{ $fuelStation->name }}</td>
            <td>{{ $fuelStation->site?->name ?: '—' }}</td>
            <td class="panel-tutar"><x-ui.money :value="$fuelStation->unit_price" :decimals="4" suffix="TL/lt" /></td>
            <td><x-ui.active-badge :active="$fuelStation->active" /></td>
            <x-table.actions
                :edit="route('admin.fuel-stations.edit', $fuelStation)"
                :delete="route('admin.fuel-stations.destroy', $fuelStation)"
                confirm="Akaryakıt istasyonu silinsin mi?"
                :confirm-text="$fuelStation->name.' listeden kaldırılacak. Geçmiş yakıt fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="5" icon="fa-solid fa-gas-pump" title="Henüz akaryakıt istasyonu yok." text="Yakıt fişlerinde seçmek için istasyonları ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.fuel-stations.create')">Yeni istasyon</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="5" :reset="route('admin.fuel-stations.index')" />
        @endif
    @endforelse
</tbody>
