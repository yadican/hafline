<tbody>
    @forelse ($bonuses as $bonus)
        <tr>
            <td>{{ $bonus->occurred_on?->format('d.m.Y') }}</td>
            <td>{{ $bonus->employee?->name }}</td>
            <td>{{ $bonus->description ?: '—' }}</td>
            <td class="is-num">{{ number_format((float) $bonus->amount, 2, ',', '.') }} ₺</td>
            <x-table.actions
                :show="route('admin.employee-bonuses.show', $bonus)"
                :edit="auth()->user()->can('edit-employee-bonus') ? route('admin.employee-bonuses.edit', $bonus) : null"
                :delete="auth()->user()->can('delete-employee-bonus') ? route('admin.employee-bonuses.destroy', $bonus) : null"
                confirm="Prim silinsin mi?"
                :confirm-text="number_format((float) $bonus->amount, 2, ',', '.').' ₺ tutarındaki prim silinecek.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="5" icon="fa-solid fa-gift" title="Henüz prim yok." text="Personele verilen primleri buradan ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.employee-bonuses.create')">Yeni prim</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="5" :reset="route('admin.employee-bonuses.index')" />
        @endif
    @endforelse
</tbody>
