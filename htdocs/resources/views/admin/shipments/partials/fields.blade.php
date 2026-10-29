@php
    $current = $shipment ?? null;
    $withholdingLabel = fn ($rate): string => \App\Enums\Withholding::tryFrom((string) $rate)?->label() ?? 'Yok';
@endphp

<x-form.group legend="1. Sefer bilgileri" icon="fa-solid fa-mountain" description="Ocak, kantar ve evrak bilgileri; fiyatlar net miktarın tonu üzerinden hesaplanır" class="is-wide">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-form.input name="shipped_on" label="Sefer tarihi" type="date" required />
        <x-form.input name="loading_place" label="Ocak (yükleme yeri)" maxlength="120" required placeholder="Ocak adı..." />
        <x-form.input name="net_kg" label="Net miktar (kg)" type="number" step="1" min="1" required data-shipment-amount />
        <x-form.input name="scale_ticket_no" label="Kantar fiş no" maxlength="30" />
        <x-form.input name="waybill_no" label="İrsaliye no" maxlength="30" placeholder="İRS-0001" />
        <x-form.input name="driver_name" label="Şoför" maxlength="80" placeholder="Şoför adı" />
    </div>
</x-form.group>

<x-form.group legend="2. Nakliyeci (alış)" icon="fa-solid fa-truck-moving" description="Nakliyeci, plakanın o günkü sahibidir; carisine alacak yazılır" class="flex flex-col [&>.panel-form-group-body]:flex-1">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-form.autocomplete
            name="plate"
            label="Araç plakası"
            :url="route('admin.shipments.lookups.vehicles')"
            :value="$current?->vehicle?->plate"
            placeholder="34 ABC 123"
            maxlength="20"
            required
            class="panel-plate"
            data-shipment-plate
        >
            <x-form.status data-shipment-carrier>{{ $current ? 'Nakliyeci: '.$current->party?->name : 'Plaka seçilince nakliyeci görünür' }}</x-form.status>
            @can('create-vehicle')
                <a href="{{ route('admin.vehicles.create') }}" target="_blank" rel="noopener" class="panel-hint">+ Plaka</a>
            @endcan
        </x-form.autocomplete>
        <x-form.money name="driver_amount" label="Şoför ücreti" step="0.01" data-shipment-amount>
            <x-form.status>Araç sahibine ayrı kalem olarak alacak yazılır</x-form.status>
        </x-form.money>
    </div>
    <x-form.money name="carrier_price" label="Nakliye fiyatı" step="0.0001" suffix="TL/ton" required data-shipment-amount />
    <div class="grid gap-3 sm:grid-cols-2">
        <x-form.select name="carrier_vat_rate" label="KDV oranı" :options="$options['vat']" :value="$current ? (string) (int) $current->carrier_vat_rate : '0'" data-shipment-tax />
        <x-form.select name="carrier_withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-tax />
    </div>
    <x-form.input name="carrier_note" label="Nakliye açıklaması" maxlength="255" placeholder="Nakliye notu..." />
    @include('admin.shipments.partials.totals', [
        'side' => 'carrier',
        'base' => $current?->carrier_amount,
        'vat' => $current?->carrier_vat_amount,
        'vatRate' => $current?->carrier_vat_rate,
        'withheld' => $current?->carrier_withholding_amount,
        'withholdingLabel' => $withholdingLabel($current?->carrier_withholding_rate),
        'net' => $current?->carrier_net_amount,
    ])
</x-form.group>

<x-form.group legend="3. Müşteri (satış)" icon="fa-solid fa-user-tie" description="Müşteri borçlanır; yeni sevkiyat adresi carisine kaydedilir" class="flex flex-col [&>.panel-form-group-body]:flex-1">
    <x-form.select name="customer_party_id" label="Müşteri cari" :options="$options['parties']" placeholder="Seçin" searchable required data-shipment-customer />
    <x-form.input name="delivery_address" label="Sevkiyat adresi" maxlength="255" :value="$current?->deliveryAddress?->name" list="shipment-addresses" placeholder="Önce cari, sonra adres" />
    <datalist id="shipment-addresses" data-shipment-addresses></datalist>
    <x-form.money name="sale_price" label="Nakliye hizmet bedeli" step="0.0001" suffix="TL/ton" required data-shipment-amount />
    <div class="grid gap-3 sm:grid-cols-2">
        <x-form.select name="vat_rate" label="KDV oranı" :options="$options['vat']" :value="$current ? (string) (int) $current->vat_rate : '20'" data-shipment-tax />
        <x-form.select name="withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-tax />
    </div>
    <x-form.input name="customer_note" label="Müşteri satış açıklaması" maxlength="255" placeholder="Müşteri notu..." />
    @include('admin.shipments.partials.totals', [
        'side' => 'customer',
        'base' => $current?->sale_amount,
        'vat' => $current?->vat_amount,
        'vatRate' => $current?->vat_rate ?? 20,
        'withheld' => $current?->withholding_amount,
        'withholdingLabel' => $withholdingLabel($current?->withholding_rate),
        'net' => $current?->customer_amount,
    ])
</x-form.group>

<x-form.group legend="4. Referans / hakediş (isteğe bağlı)" icon="fa-solid fa-handshake" description="Referans varsa hakedişi carisine alacak yazılır ve kârdan düşer" class="is-wide">
    <div class="grid gap-3 sm:grid-cols-3">
        <x-form.select name="commission_party_id" label="Referans cari" :options="$options['parties']" placeholder="Yönlendiren cari seçin..." searchable />
        <x-form.money name="commission_amount" label="Referans hakedişi" step="0.01" data-shipment-amount />
        <x-form.input name="commission_note" label="Referans açıklaması" maxlength="255" placeholder="Referans notu..." />
    </div>
    <x-form.status data-shipment-total>Sefer kârı hesaplanır</x-form.status>
</x-form.group>
