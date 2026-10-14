<tbody>
    @forelse ($paymentTypes as $paymentType)
        <tr>
            <td>{{ $paymentType->name }}</td>
            <td>
                <i class="{{ $paymentType->kind->icon() }} panel-muted-icon" aria-hidden="true"></i>
                {{ $paymentType->kind->label() }}
            </td>
            <td><x-ui.active-badge :active="$paymentType->active" /></td>
            <x-table.actions
                :edit="route('admin.payment-types.edit', $paymentType)"
                :delete="route('admin.payment-types.destroy', $paymentType)"
                confirm="Ödeme tipi silinsin mi?"
                :confirm-text="$paymentType->name.' ödeme tipi listeden kaldırılacak. Geçmiş kayıtlardaki kullanımı etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-money-check-dollar" title="Henüz ödeme tipi yok." text="Nakit, çek, banka gibi ödeme tiplerini ekleyerek başlayın.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.payment-types.create')">Yeni ödeme tipi</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.payment-types.index')" />
        @endif
    @endforelse
</tbody>
