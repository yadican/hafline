@php($isPerson = $type === \App\Enums\PartyType::Person)

<thead>
    <tr>
        <x-table.th sort="name">{{ $isPerson ? 'Ad soyad' : 'Unvan' }}</x-table.th>
        <th class="is-id">{{ $isPerson ? 'TCKN' : 'Vergi no' }}</th>
        <th>Roller</th>
        <th class="is-phone">Telefon</th>
        <x-table.th sort="vehicles" first="desc" class="is-center is-count">Araç</x-table.th>
        <x-table.th sort="balance" first="desc" class="is-balance">Bakiye</x-table.th>
        <th class="is-status">Durum</th>
        <th class="is-center is-actions">İşlem</th>
    </tr>
</thead>
