@if ($entry->source_type === 'haul' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.hauls.show', $entry->source) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'machine_work' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.machine-works.show', $entry->source) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'fuel_receipt' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.fuel-receipts.show', $entry->source) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'shipment' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.shipments.show', $entry->source) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'fuel_tank_fill' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.fuel-stations.show', $entry->source->fuel_station_id) }}#stok">{{ $label }}</a>
@elseif (in_array($entry->source_type, ['company_transaction', 'vehicle_payment', 'vehicle_receivable', 'party_payment', 'dump_site_payment', 'opening_balance', 'party_transfer', 'site_bill', 'site_transaction'], true) && $entry->source && ! $entry->source->trashed())
    @php
        $cariKind = match ($entry->source_type) {
            'company_transaction' => $entry->source->role->value,
            'party_payment' => 'owner_payment',
            'dump_site_payment' => 'dump_payment',
            'opening_balance' => 'opening',
            'site_transaction' => 'site',
            default => $entry->source_type,
        };
    @endphp
    <a href="{{ route('admin.cari-entries.show', ['kind' => $cariKind, 'entry' => $entry->source_id]) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'financial_instrument_movement' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.financial-instruments.show', $entry->source->financial_instrument_id) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'employee_payment' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.employee-payments.show', $entry->source) }}">{{ $label }}</a>
@elseif ($entry->source_type === 'employee_bonus' && $entry->source && ! $entry->source->trashed())
    <a href="{{ route('admin.employee-bonuses.show', $entry->source) }}">{{ $label }}</a>
@else
    {{ $label }}
@endif
