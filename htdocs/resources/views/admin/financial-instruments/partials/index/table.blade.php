<thead>
    <tr>
        <x-table.th sort="due_on" class="is-date">Vade</x-table.th>
        <th>Tür</th>
        <th>Yön</th>
        <x-table.th sort="number">No</x-table.th>
        <th>Cari</th>
        <th>Banka / keşideci</th>
        <x-table.th sort="amount" class="is-end">Tutar</x-table.th>
        <th>Durum</th>
        <th class="is-center is-actions">İşlem</th>
    </tr>
</thead>
<tbody>
    @forelse ($instruments as $instrument)
        <tr>
            <td>
                {{ $instrument->due_on->format('d.m.Y') }}
                @if ($instrument->isOverdue())
                    <i class="fa-solid fa-triangle-exclamation panel-muted-icon" title="Vadesi geçti" aria-label="Vadesi geçti"></i>
                @endif
            </td>
            <td>{{ $instrument->payment_method->label() }}</td>
            <td>{{ $instrument->direction->label() }}</td>
            <td>{{ $instrument->number ?: '—' }}</td>
            <td>{{ $instrument->party?->name ?? '—' }}</td>
            <td>{{ collect([$instrument->bank_name, $instrument->drawer])->filter()->implode(' · ') ?: '—' }}</td>
            <td class="is-end"><x-ui.money :value="$instrument->amount" /></td>
            <td><x-ui.enum-badge :value="$instrument->status" text /></td>
            <x-table.actions :show="route('admin.financial-instruments.show', $instrument)" />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="9" icon="fa-solid fa-money-check" title="Portföyde çek veya senet yok." text="Alınan ya da verilen çeki / senedi buradan girin; cari hareketinde ödeme tipi çek / senet seçilen kayıtlar da burada görünür.">
                @can('create-financial-instrument')
                    <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.financial-instruments.create')">Yeni çek / senet</x-ui.button>
                @endcan
            </x-table.empty>
        @else
            <x-table.no-results colspan="9" :reset="route('admin.financial-instruments.index')" />
        @endif
    @endforelse
</tbody>
