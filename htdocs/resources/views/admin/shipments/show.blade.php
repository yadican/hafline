@extends('admin.layouts.app')

@section('content')
    @php
        $money = fn ($value): string => number_format((float) $value, 2, ',', '.').' TL';
        $price = fn ($value): string => number_format((float) $value, 4, ',', '.').' TL/ton';
        $withholding = fn ($rate): ?string => \App\Enums\Withholding::tryFrom((string) $rate)?->label();
    @endphp
    <x-panel.page
        heading="Sefer detayı"
        icon="fa-solid fa-truck-moving"
        :description="$shipment->shipped_on->format('d.m.Y').' · '.$shipment->vehicle?->plate"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Seferler' => route('admin.shipments.index'), 'Detay' => null]"
        :back="route('admin.shipments.index')"
    >
        <x-slot:actions>
            @can('edit-shipment')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.shipments.edit', $shipment)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        <div class="panel-form-grid">
            <x-form.group legend="1. Nakliyeci" icon="fa-solid fa-truck-moving" description="Nakliyeciye yazılan alacak">
                <x-form.field label="Nakliyeci"><div>{{ $shipment->party?->name }}</div></x-form.field>
                <x-form.field label="Plaka"><div class="panel-plate-text">{{ $shipment->vehicle?->plate }}</div></x-form.field>
                <x-form.field label="Şoför / şoför ücreti">
                    <div>{{ $shipment->driver_name ?: '—' }}{{ (float) $shipment->driver_amount > 0 ? ' · '.$money($shipment->driver_amount).' (araç sahibine ayrı kalem alacak)' : '' }}</div>
                </x-form.field>
                <x-form.field label="İrsaliye no"><div>{{ $shipment->waybill_no ?: '—' }}</div></x-form.field>
                <x-form.field label="Fiyat"><div>{{ $price($shipment->carrier_price) }} · matrah {{ $money($shipment->carrier_amount) }}</div></x-form.field>
                <x-form.field label="KDV / tevkifat"><div>%{{ (int) $shipment->carrier_vat_rate }} · {{ $money($shipment->carrier_vat_amount) }} / {{ $withholding($shipment->carrier_withholding_rate) }} · {{ $money($shipment->carrier_withholding_amount) }}</div></x-form.field>
                <x-form.field label="Nakliyeci net"><div><strong>{{ $money($shipment->carrier_net_amount) }}</strong></div></x-form.field>
                <x-form.field label="Açıklama"><div>{{ $shipment->carrier_note ?: '—' }}</div></x-form.field>
            </x-form.group>

            <x-form.group legend="2. Kum ocağı" icon="fa-solid fa-mountain" description="Yükleme ve kantar">
                <x-form.field label="Ocak (yükleme yeri)"><div>{{ $shipment->loading_place }}</div></x-form.field>
                <x-form.field label="Net miktar"><div>{{ \App\Models\Shipment::formatKg($shipment->net_kg) }} kg</div></x-form.field>
                <x-form.field label="Kantar fiş no"><div>{{ $shipment->scale_ticket_no ?: '—' }}</div></x-form.field>
            </x-form.group>

            <x-form.group legend="3. Müşteri" icon="fa-solid fa-user-tie" description="Müşteriye yazılan borç">
                <x-form.field label="Müşteri"><div>{{ $shipment->customer?->name }}</div></x-form.field>
                <x-form.field label="Sevkiyat adresi"><div>{{ $shipment->deliveryAddress?->name ?? '—' }}</div></x-form.field>
                <x-form.field label="Hizmet bedeli"><div>{{ $price($shipment->sale_price) }} · matrah {{ $money($shipment->sale_amount) }}</div></x-form.field>
                <x-form.field label="KDV / tevkifat"><div>%{{ (int) $shipment->vat_rate }} · {{ $money($shipment->vat_amount) }} / {{ $withholding($shipment->withholding_rate) }} · {{ $money($shipment->withholding_amount) }}</div></x-form.field>
                <x-form.field label="Müşteri net"><div><strong>{{ $money($shipment->customer_amount) }}</strong></div></x-form.field>
                <x-form.field label="Açıklama"><div>{{ $shipment->customer_note ?: '—' }}</div></x-form.field>
            </x-form.group>

            <x-form.group legend="4. Referans ve kâr" icon="fa-solid fa-handshake" description="Referans hakedişi ve sefer kârı (KDV hariç)">
                <x-form.field label="Referans"><div>{{ $shipment->commissionParty ? $shipment->commissionParty->name.' · '.$money($shipment->commission_amount) : '—' }}</div></x-form.field>
                <x-form.field label="Referans açıklaması"><div>{{ $shipment->commission_note ?: '—' }}</div></x-form.field>
                <x-form.field label="Sefer kârı"><div><strong>{{ $money($shipment->profit()) }}</strong></div></x-form.field>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
