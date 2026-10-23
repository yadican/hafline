<tbody>
    @forelse ($cashCategories as $cashCategory)
        <tr>
            <td>{{ $cashCategory->name }}</td>
            <td><x-ui.enum-badge :value="$cashCategory->direction" text /></td>
            <td><x-ui.active-badge :active="$cashCategory->active" /></td>
            <x-table.actions
                :show="route('admin.cash-categories.show', $cashCategory)"
                :edit="auth()->user()->can('edit-cash-category') ? route('admin.cash-categories.edit', $cashCategory) : null"
                :delete="auth()->user()->can('delete-cash-category') ? route('admin.cash-categories.destroy', $cashCategory) : null"
                confirm="Kasa kategorisi silinsin mi?"
                :confirm-text="$cashCategory->name.' kategorisi listeden kaldırılacak. Geçmiş kasa kayıtları etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-tags" title="Henüz kasa kategorisi yok." text="Kasa hareketlerini gruplamak için ilk kategoriyi ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.cash-categories.create')">Yeni kategori</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.cash-categories.index')" />
        @endif
    @endforelse
</tbody>
