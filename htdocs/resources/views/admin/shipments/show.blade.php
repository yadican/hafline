@extends('admin.layouts.app')

@section('content')
    @php
        $money = fn ($value): string => '₺'.number_format((float) $value, 2, ',', '.');
        $price = fn ($value): string => '₺'.number_format((float) $value, 4, ',', '.').' / ton';
        $withholdingLabel = fn ($rate): string => \App\Enums\Withholding::tryFrom((string) $rate)?->label() ?? 'Yok';
        $profit = $shipment->profit();
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

        <div class="panel-form-grid is-detail">
            <x-form.group legend="1. Sefer bilgileri" icon="fa-solid fa-mountain" description="Ocak, kantar ve evrak bilgileri; fiyatlar net miktarın tonu üzerinden hesaplanır" class="is-wide">
                <div class="panel-detail-columns">
                    <x-form.field label="Sefer tarihi"><div>{{ $shipment->shipped_on->format('d.m.Y') }}</div></x-form.field>
                    <x-form.field label="Ocak (yükleme yeri)"><div>{{ $shipment->loading_place }}</div></x-form.field>
                    <x-form.field label="Net miktar"><div>{{ \App\Models\Shipment::formatKg($shipment->net_kg) }} kg</div></x-form.field>
                    <x-form.field label="Kantar fiş no"><div>{{ $shipment->scale_ticket_no ?: '—' }}</div></x-form.field>
                    <x-form.field label="İrsaliye no"><div>{{ $shipment->waybill_no ?: '—' }}</div></x-form.field>
                    <x-form.field label="Şoför"><div>{{ $shipment->driver_name ?: '—' }}</div></x-form.field>
                </div>
            </x-form.group>

            <x-form.group legend="2. Nakliyeci (alış)" icon="fa-solid fa-truck-moving" description="Nakliyeci, plakanın o günkü sahibidir; carisine alacak yazılır" class="flex flex-col [&>.panel-form-group-body]:flex-1">
                <x-form.field label="Araç plakası">
                    <div class="panel-plate-text">{{ $shipment->vehicle?->plate }}</div>
                    <x-form.status state="ok">Nakliyeci: {{ $shipment->party?->name }}</x-form.status>
                </x-form.field>
                <x-form.field label="Şoför ücreti">
                    <div>{{ $money($shipment->driver_amount) }}</div>
                    <x-form.status>Araç sahibine ayrı kalem olarak alacak yazılır</x-form.status>
                </x-form.field>
                <x-form.field label="Nakliye fiyatı"><div>{{ $price($shipment->carrier_price) }}</div></x-form.field>
                <x-form.field label="KDV oranı"><div>%{{ (int) $shipment->carrier_vat_rate }}</div></x-form.field>
                <x-form.field label="Tevkifat"><div>{{ $withholdingLabel($shipment->carrier_withholding_rate) }}</div></x-form.field>
                <x-form.field label="Nakliye açıklaması"><div>{{ $shipment->carrier_note ?: '—' }}</div></x-form.field>
                <div class="mt-auto pt-3">
                    @include('admin.shipments.partials.totals', [
                        'side' => 'carrier',
                        'base' => $shipment->carrier_amount,
                        'vat' => $shipment->carrier_vat_amount,
                        'vatRate' => $shipment->carrier_vat_rate,
                        'withheld' => $shipment->carrier_withholding_amount,
                        'withholdingLabel' => $withholdingLabel($shipment->carrier_withholding_rate),
                        'net' => $shipment->carrier_net_amount,
                    ])
                </div>
            </x-form.group>

            <x-form.group legend="3. Müşteri (satış)" icon="fa-solid fa-user-tie" description="Müşteri borçlanır; sevkiyat adresi carisine kayıtlıdır" class="flex flex-col [&>.panel-form-group-body]:flex-1">
                <x-form.field label="Müşteri cari"><div>{{ $shipment->customer?->name }}</div></x-form.field>
                <x-form.field label="Sevkiyat adresi"><div>{{ $shipment->deliveryAddress?->name ?? '—' }}</div></x-form.field>
                <x-form.field label="Nakliye hizmet bedeli"><div>{{ $price($shipment->sale_price) }}</div></x-form.field>
                <x-form.field label="KDV oranı"><div>%{{ (int) $shipment->vat_rate }}</div></x-form.field>
                <x-form.field label="Tevkifat"><div>{{ $withholdingLabel($shipment->withholding_rate) }}</div></x-form.field>
                <x-form.field label="Müşteri satış açıklaması"><div>{{ $shipment->customer_note ?: '—' }}</div></x-form.field>
                <div class="mt-auto pt-3">
                    @include('admin.shipments.partials.totals', [
                        'side' => 'customer',
                        'base' => $shipment->sale_amount,
                        'vat' => $shipment->vat_amount,
                        'vatRate' => $shipment->vat_rate,
                        'withheld' => $shipment->withholding_amount,
                        'withholdingLabel' => $withholdingLabel($shipment->withholding_rate),
                        'net' => $shipment->customer_amount,
                    ])
                </div>
            </x-form.group>

            <x-form.group legend="4. Referans / hakediş" icon="fa-solid fa-handshake" description="Referans varsa hakedişi carisine alacak yazılır ve kârdan düşer" class="is-wide">
                <div class="panel-detail-columns">
                    <x-form.field label="Referans cari"><div>{{ $shipment->commissionParty?->name ?? '—' }}</div></x-form.field>
                    <x-form.field label="Referans hakedişi"><div>{{ $money($shipment->commission_amount) }}</div></x-form.field>
                    <x-form.field label="Referans açıklaması"><div>{{ $shipment->commission_note ?: '—' }}</div></x-form.field>
                </div>
                <div class="pt-3">
                    <x-form.status :state="$profit < 0 ? 'err' : 'ok'">Sefer kârı (KDV hariç): {{ $money($profit) }}</x-form.status>
                </div>
            </x-form.group>

            <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Seferi giren kullanıcı ve kayıt zamanı" class="is-wide">
                <div class="panel-detail-columns">
                    <x-form.field label="Giren"><div>{{ $shipment->creator?->name ?: '—' }}</div></x-form.field>
                    <x-form.field label="Kayıt zamanı"><div>{{ $shipment->created_at?->format('d.m.Y H:i') ?: '—' }}</div></x-form.field>
                </div>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
