<tbody>
    @forelse ($banks as $bank)
        <tr>
            <td><i class="{{ $bank->type->icon() }} panel-muted-icon" aria-hidden="true"></i> {{ $bank->type->label() }}</td>
            <td>{{ $bank->name }}</td>
            <td>{{ $bank->branch ?: '—' }}</td>
            <td>{{ $bank->iban ?: ($bank->account_no ?: '—') }}</td>
            <td class="is-end"><x-ui.money :value="$balances[$bank->id] ?? (float) $bank->opening_balance" /></td>
            <td><x-ui.active-badge :active="$bank->active" /></td>
            <x-table.actions
                :show="route('admin.banks.show', $bank)"
                :edit="auth()->user()->can('edit-bank') ? route('admin.banks.edit', $bank) : null"
                :delete="auth()->user()->can('delete-bank') ? route('admin.banks.destroy', $bank) : null"
                confirm="Hesap silinsin mi?"
                :confirm-text="$bank->name.' listeden kaldırılacak. Geçmiş kayıtlar etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="7" icon="fa-solid fa-wallet" title="Henüz hesap yok." text="Kasa, banka, POS ve kredi kartı hesaplarınızı ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.banks.create')">Yeni hesap</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="7" :reset="route('admin.banks.index')" />
        @endif
    @endforelse
</tbody>
