<tbody>
    @forelse ($usages as $usage)
        @php
            $quantity = rtrim(rtrim(number_format((float) $usage->quantity, 3, ',', '.'), '0'), ',');
        @endphp
        <tr>
            <td>{{ $usage->used_on->format('d.m.Y') }}</td>
            <td>{{ $usage->stockItem?->name }}</td>
            <td class="is-end">{{ $quantity }} {{ $usage->stockItem?->unit?->value }}</td>
            <td class="is-end"><x-ui.money :value="$usage->amount" /></td>
            <td>{{ $usage->site?->name ?? '—' }}</td>
            <td>{{ $usage->vehicle?->plate ?? '—' }}</td>
            <x-table.actions
                :show="route('admin.stock-usages.show', $usage)"
                :edit="auth()->user()->can('edit-stock-usage') ? route('admin.stock-usages.edit', $usage) : null"
                :delete="auth()->user()->can('delete-stock-usage') ? route('admin.stock-usages.destroy', $usage) : null"
                confirm="Stok kullanımı silinsin mi?"
                :confirm-text="($usage->stockItem?->name ?? 'Kullanım').' kaydı stoğa iade edilecek.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="7" icon="fa-solid fa-box-open" title="Henüz stok kullanımı yok." text="Şantiye veya kendi aracımız için stoktan düşülecek ilk kullanımı ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.stock-usages.create')">Yeni kullanım</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="7" :reset="route('admin.stock-usages.index')" />
        @endif
    @endforelse
</tbody>
