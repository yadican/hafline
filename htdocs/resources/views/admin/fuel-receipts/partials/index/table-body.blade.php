<tbody>
    @forelse ($receipts as $receipt)
        <tr>
            <td class="is-center">{{ $receipts->firstItem() + $loop->index }}</td>
            <td>{{ $receipt->ticket_no ?: '—' }}</td>
            <td class="panel-plaka-cell">{{ $receipt->vehicle?->plate }}</td>
            <td>{{ $receipt->party?->name }}</td>
            <td>{{ $receipt->fuelStation?->name }}</td>
            <td>{{ $receipt->site?->name ?: '—' }}</td>
            <td class="is-end">{{ \App\Models\FuelReceipt::formatQuantity($receipt->quantity) }}</td>
            <td class="is-end">{{ number_format((float) $receipt->unit_price, 4, ',', '.') }}</td>
            <td class="panel-tutar">{{ number_format((float) $receipt->amount, 2, ',', '.') }} TL</td>
            <td>{{ $receipt->issued_on->translatedFormat('j.m.Y') }}</td>
            <td class="is-center"><x-ui.enum-badge :value="$receipt->shift" /></td>
            <x-table.actions
                :edit="route('admin.fuel-receipts.edit', $receipt)"
                :delete="route('admin.fuel-receipts.destroy', $receipt)"
                confirm="Yakıt fişi silinsin mi?"
                :confirm-text="($receipt->ticket_no ?: 'Bu').' fiş silinecek; araç sahibinin carisindeki borç ters kayıtla kapatılır.'"
            />
        </tr>
    @empty
        @if ($filters === [])
            <x-table.empty
                colspan="12"
                icon="fa-solid fa-gas-pump"
                :title="'Son '.\App\Repositories\FuelReceiptRepository::RECENT_DAYS.' günde girilmiş yakıt fişi yok.'"
                text="Daha eski fişler için Arama panelinden tarih aralığı seçin."
            >
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.fuel-receipts.create')">Yeni fiş</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="12" :reset="route('admin.fuel-receipts.index')" />
        @endif
    @endforelse
    @if ($receipts->isNotEmpty())
        <tr>
            <td colspan="6">Toplam</td>
            <td class="is-end">{{ \App\Models\FuelReceipt::formatQuantity($totals['quantity']) }}</td>
            <td></td>
            <td class="panel-tutar">{{ number_format($totals['amount'], 2, ',', '.') }} TL</td>
            <td colspan="3"></td>
        </tr>
    @endif
</tbody>
