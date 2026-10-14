<tbody>
    @forelse ($banks as $bank)
        <tr>
            <td>{{ $bank->name }}</td>
            <td>{{ $bank->representative ?: '—' }}</td>
            <td>{{ $bank->phone ?: '—' }}</td>
            <td>{{ $bank->account_no ?: '—' }}</td>
            <td><x-ui.active-badge :active="$bank->active" /></td>
            <x-table.actions
                :edit="route('admin.banks.edit', $bank)"
                :delete="route('admin.banks.destroy', $bank)"
                confirm="Banka silinsin mi?"
                :confirm-text="$bank->name.' listeden kaldırılacak. Geçmiş çek ve senet kayıtları etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="6" icon="fa-solid fa-building-columns" title="Henüz banka yok." text="Çek ve senet kayıtlarında seçmek için bankaları ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.banks.create')">Yeni banka</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="6" :reset="route('admin.banks.index')" />
        @endif
    @endforelse
</tbody>
