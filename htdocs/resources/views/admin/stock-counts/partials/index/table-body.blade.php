<tbody>
    @forelse ($counts as $count)
        @php
            $quantity = rtrim(rtrim(number_format((float) $count->quantity, 3, ',', '.'), '0'), ',');
            $difference = (float) $count->difference;
            $differenceLabel = ($difference > 0 ? '+' : '').rtrim(rtrim(number_format($difference, 3, ',', '.'), '0'), ',');
        @endphp
        <tr>
            <td>{{ $count->counted_on->format('d.m.Y') }}</td>
            <td>{{ $count->stockItem?->name }}</td>
            <td class="is-end">{{ $quantity }} {{ $count->stockItem?->unit?->value }}</td>
            <td class="is-end">{{ $differenceLabel }} {{ $count->stockItem?->unit?->value }}</td>
            <td>{{ $count->description ?: '—' }}</td>
            <x-table.actions
                :show="route('admin.stock-counts.show', $count)"
                :edit="auth()->user()->can('edit-stock-count') ? route('admin.stock-counts.edit', $count) : null"
                :delete="auth()->user()->can('delete-stock-count') ? route('admin.stock-counts.destroy', $count) : null"
                confirm="Stok sayımı silinsin mi?"
                :confirm-text="($count->stockItem?->name ?? 'Sayım').' farkı stoktan geri alınır.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="6" icon="fa-solid fa-clipboard-check" title="Henüz stok sayımı yok." text="Sayılan miktarı girerek defter stoğunu düzeltin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.stock-counts.create')">Yeni sayım</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="6" :reset="route('admin.stock-counts.index')" />
        @endif
    @endforelse
</tbody>
