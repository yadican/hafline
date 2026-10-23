<tbody>
    @forelse ($payments as $payment)
        <tr>
            <td>{{ $payment->occurred_on?->format('d.m.Y') }}</td>
            <td>{{ $payment->employee?->name }}</td>
            <td>{{ $payment->period?->translatedFormat('F Y') }}</td>
            <td>{{ $payment->payment_method?->label() ?: '—' }}</td>
            <td>{{ $payment->description ?: '—' }}</td>
            <td class="is-num">{{ number_format((float) $payment->amount, 2, ',', '.') }} ₺</td>
            <x-table.actions
                :show="route('admin.employee-payments.show', $payment)"
                :edit="auth()->user()->can('edit-employee-payment') ? route('admin.employee-payments.edit', $payment) : null"
                :delete="auth()->user()->can('delete-employee-payment') ? route('admin.employee-payments.destroy', $payment) : null"
                confirm="Ödeme silinsin mi?"
                :confirm-text="number_format((float) $payment->amount, 2, ',', '.').' ₺ tutarındaki ödeme silinecek.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="7" icon="fa-solid fa-money-bill-transfer" title="Henüz ödeme yok." text="Personele yapılan ödemeleri buradan ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.employee-payments.create')">Yeni ödeme</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="7" :reset="route('admin.employee-payments.index')" />
        @endif
    @endforelse
</tbody>
