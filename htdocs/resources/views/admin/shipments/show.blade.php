@extends('admin.layouts.app')

@section('content')
    @php
        $money = fn ($value): string => number_format((float) $value, 2, ',', '.').' TL';
        $unit = $shipment->material?->unit->label();
    @endphp
    <x-panel.page
        heading="Sefer detayı"
        icon="fa-solid fa-truck-moving"
        :description="$shipment->kind->label().' · '.$shipment->shipped_on->format('d.m.Y')"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Seferler' => route('admin.shipments.index'), 'Detay' => null]"
        :back="route('admin.shipments.index')"
    >
        <x-slot:actions>
            @can('edit-shipment')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.shipments.edit', $shipment)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        <div class="panel-form-grid">
            <x-form.group legend="Sefer" icon="fa-solid fa-truck-moving" description="Plaka, nakliyeci ve belgeler">
                <x-form.field label="Plaka"><div class="panel-plate-text">{{ $shipment->vehicle?->plate }}</div></x-form.field>
                <x-form.field label="Nakliyeci"><div>{{ $shipment->party?->name }}</div></x-form.field>
                <x-form.field label="İrsaliye no"><div>{{ $shipment->waybill_no ?: '—' }}</div></x-form.field>
                <x-form.field label="Kantar fiş no"><div>{{ $shipment->scale_ticket_no ?: '—' }}</div></x-form.field>
                @if ($shipment->quarry_party_id)
                    <x-form.field label="Ocak fatura no"><div>{{ $shipment->quarry_invoice_no ?: '—' }}</div></x-form.field>
                @endif
                <x-form.field label="Açıklama"><div>{{ $shipment->description ?: '—' }}</div></x-form.field>
            </x-form.group>

            <x-form.group legend="Güzergah ve malzeme" icon="fa-solid fa-route" description="Nereden nereye, ne kadar">
                <x-form.field label="Ocak / yükleme yeri"><div>{{ $shipment->quarry?->name }}</div></x-form.field>
                <x-form.field label="Sevk yeri"><div>{{ $shipment->deliveryAddress?->name }}</div></x-form.field>
                <x-form.field label="Müşteri"><div>{{ $shipment->customer?->name }}</div></x-form.field>
                <x-form.field label="Malzeme"><div>{{ \App\Models\Shipment::formatQuantity($shipment->quantity) }} {{ $unit }} {{ $shipment->material?->name }}</div></x-form.field>
            </x-form.group>

            <x-form.group legend="Cariye yazılanlar" icon="fa-solid fa-scale-balanced" description="Borç ve alacaklar ile sefer kârı (KDV hariç)">
                <x-form.field label="Satış">
                    <div>{{ number_format((float) $shipment->sale_price, 4, ',', '.') }} TL/{{ $unit }} · {{ $money($shipment->sale_amount) }}</div>
                </x-form.field>
                <x-form.field label="KDV / tevkifat">
                    <div>%{{ (int) $shipment->vat_rate }} · {{ $money($shipment->vat_amount) }} / {{ \App\Enums\Withholding::tryFrom((string) $shipment->withholding_rate)?->label() }} · {{ $money($shipment->withholding_amount) }}</div>
                </x-form.field>
                <x-form.field label="Müşteriye borç"><div><strong>{{ $money($shipment->customer_amount) }}</strong> · {{ $shipment->customer?->name }}</div></x-form.field>
                <x-form.field label="Nakliyeciye alacak">
                    <div>{{ number_format((float) $shipment->carrier_price, 4, ',', '.') }} TL/{{ $unit }} · {{ $money($shipment->carrier_amount) }}</div>
                </x-form.field>
                @if ($shipment->quarry_party_id)
                    <x-form.field label="Ocağa alacak">
                        <div>{{ number_format((float) $shipment->quarry_price, 4, ',', '.') }} TL/{{ $unit }} · {{ $money($shipment->quarry_amount) }} · {{ $shipment->quarryParty?->name }}</div>
                    </x-form.field>
                @endif
                @if ($shipment->commission_party_id)
                    <x-form.field label="Komisyoncuya alacak"><div>{{ $money($shipment->commission_amount) }} · {{ $shipment->commissionParty?->name }}</div></x-form.field>
                @endif
                <x-form.field label="Sefer kârı"><div><strong>{{ $money($shipment->profit()) }}</strong></div></x-form.field>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
