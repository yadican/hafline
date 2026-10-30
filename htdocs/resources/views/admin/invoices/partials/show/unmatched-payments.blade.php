@php($remaining = $invoice->remainingAmount())
<div class="pt-4" id="bosta-odemeler">
    <p class="panel-hint">Bu carinin henüz faturaya bağlanmamış {{ $paymentsWord }}. "Bağla" boşta kalan tutarı faturanın kalanına kadar bağlar; tutar değiştirilebilir.</p>
    <table class="panel-subtable">
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Belge no / açıklama</th>
                <th>Ödeme tipi</th>
                <th class="is-end">Tutar</th>
                <th class="is-end">Boşta</th>
                <th class="is-end">Bağla</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($unmatchedPayments as $row)
                @php($payment = $row['payment'])
                @php($text = $payment instanceof \App\Models\CompanyTransaction ? collect([$payment->document_no, $payment->description])->filter()->implode(' · ') : $row['description'])
                <tr>
                    <td>{{ $row['occurred_on']->format('d.m.Y') }}</td>
                    <td>
                        @if ($payment instanceof \App\Models\CompanyTransaction && auth()->user()?->can('view-cari-entry'))
                            <a href="{{ route('admin.cari-entries.show', ['kind' => $payment->role->value, 'entry' => $payment]) }}">{{ $text ?: $paymentLabel }}</a>
                        @else
                            {{ $text ?: $paymentLabel }}
                        @endif
                    </td>
                    <td>{{ $row['payment_method']?->label() ?? '—' }}</td>
                    <td class="is-end"><x-ui.money :value="$row['amount']" /></td>
                    <td class="is-end"><x-ui.money :value="$row['unmatched']" /></td>
                    <td class="is-end">
                        <form method="POST" action="{{ route('admin.invoices.matches.store', $invoice) }}" class="flex items-center justify-end gap-2">
                            @csrf
                            <input type="hidden" name="payment_type" value="{{ $payment->getMorphClass() }}">
                            <input type="hidden" name="payment_id" value="{{ $payment->getKey() }}">
                            <input
                                type="number"
                                name="amount"
                                step="0.01"
                                min="0.01"
                                inputmode="decimal"
                                value="{{ number_format(min($remaining, $row['unmatched']), 2, '.', '') }}"
                                class="slct w-32"
                                aria-label="Bağlanacak tutar"
                            >
                            <x-ui.button type="submit" variant="create" icon="fa-solid fa-link">Bağla</x-ui.button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <form method="POST" action="{{ route('admin.invoices.matches.distribute', $invoice) }}" class="flex justify-end pt-3">
        @csrf
        <x-ui.button type="submit" icon="fa-solid fa-wand-magic-sparkles">En eskiden otomatik dağıt</x-ui.button>
    </form>
</div>
