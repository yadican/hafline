<tbody>
    @forelse ($stockItems as $stockItem)
        <tr>
            <td>{{ $stockItem->name }}</td>
            <td>{{ $stockItem->unit->label() }}</td>
            <td>{{ $stockItem->tracks_stock ? 'Evet' : 'Hayır' }}</td>
            <td class="is-end"><x-ui.money :value="$stockItem->sales_price" :decimals="4" /></td>
            <td><x-ui.active-badge :active="$stockItem->active" /></td>
            <x-table.actions
                :show="route('admin.stock-items.show', $stockItem)"
                :edit="auth()->user()->can('edit-stock-item') ? route('admin.stock-items.edit', $stockItem) : null"
                :delete="auth()->user()->can('delete-stock-item') ? route('admin.stock-items.destroy', $stockItem) : null"
                confirm="Stok kalemi silinsin mi?"
                :confirm-text="$stockItem->name.' kalemi listeden kaldırılacak.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="6" icon="fa-solid fa-boxes-stacked" title="Henüz stok kalemi yok." text="Fatura ve stok hareketlerinde kullanmak için ilk kalemi ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.stock-items.create')">Yeni kalem</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="6" :reset="route('admin.stock-items.index')" />
        @endif
    @endforelse
</tbody>
