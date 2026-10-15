<thead>
    <tr>
        <th>Tarih</th>
        <th>İşlem</th>
        <th>Kime</th>
        <th>Açıklama</th>
        <th>Ödeme</th>
        <th class="is-end">Borç</th>
        <th class="is-end">Alacak</th>
        <th class="is-center is-actions">İşlem</th>
    </tr>
</thead>
<tbody>
    @forelse ($entries as $entry)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($entry->occurred_on)->format('d.m.Y') }}</td>
            <td>{{ \App\Enums\CariEntryKind::from($entry->kind)->label() }}</td>
            <td>{{ $entry->label ?: '—' }}</td>
            <td>{{ $entry->description ?: '—' }}</td>
            <td>{{ $entry->payment_method ? \App\Enums\PaymentMethod::from($entry->payment_method)->label() : '—' }}</td>
            <td class="is-end">{{ (float) $entry->debit ? number_format((float) $entry->debit, 2, ',', '.') : '' }}</td>
            <td class="is-end">{{ (float) $entry->credit ? number_format((float) $entry->credit, 2, ',', '.') : '' }}</td>
            <x-table.actions
                :edit="route('admin.cari-entries.edit', ['kind' => $entry->kind, 'entry' => $entry->id])"
                :delete="route('admin.cari-entries.destroy', ['kind' => $entry->kind, 'entry' => $entry->id])"
                confirm="Cari hareketi silinsin mi?"
                confirm-text="Kayıt silinecek. Cariye yazılmış tutar ters kayıtla kapatılır."
            />
        </tr>
    @empty
        <x-table.empty colspan="8" icon="fa-solid fa-right-left" title="Henüz cari hareketi yok." text="Satıcı, petrol, plaka ödemesi veya kasa gibi bir hareket ekleyin.">
            <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.cari-entries.create')">Yeni hareket</x-ui.button>
        </x-table.empty>
    @endforelse
</tbody>
