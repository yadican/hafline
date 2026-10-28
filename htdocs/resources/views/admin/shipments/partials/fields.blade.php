@php
    $current = $shipment ?? null;
@endphp

<x-form.group legend="1. Nakliyeci bilgileri" icon="fa-solid fa-truck-moving" description="Nakliyeci cari seçilince plakalar o cariye ait olanlarla sınırlanır">
    <x-form.select name="party_id" label="Nakliyeci cari" :options="$options['carriers']" placeholder="Seçin" searchable required data-shipment-carrier />
    <x-form.autocomplete
        name="plate"
        label="Araç plakası"
        :url="route('admin.shipments.lookups.vehicles', ['party' => old('party_id', $current?->party_id)])"
        :value="$current?->vehicle?->plate"
        placeholder="34 ABC 123"
        maxlength="20"
        required
        class="panel-plate"
        data-shipment-plate
    >
        @can('create-vehicle')
            <a href="{{ route('admin.vehicles.create') }}" target="_blank" rel="noopener" class="panel-hint">+ Plaka</a>
        @endcan
    </x-form.autocomplete>
    <x-form.input name="waybill_no" label="İrsaliye no" maxlength="30" placeholder="İRS-0001" />
    <x-form.money name="carrier_price" label="Fiyat (TL / ton)" step="0.0001" required data-shipment-amount />
    <x-form.select name="carrier_vat_rate" label="KDV oranı" :options="$options['vat']" :value="$current ? (string) (int) $current->carrier_vat_rate : '0'" data-shipment-tax />
    <x-form.select name="carrier_withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-tax />
    <x-form.select name="driver_party_id" label="Şoför" :options="$options['parties']" placeholder="Şoför seçin" searchable />
    <x-form.money name="driver_amount" label="Sefer parası (TL)" step="0.01" data-shipment-amount>
        <x-form.status>Girilirse şoförün carisine alacak yazılır, plakaya bağlanır ve kârdan düşer</x-form.status>
    </x-form.money>
    <x-form.input name="carrier_note" label="Nakliye açıklaması" maxlength="255" placeholder="Nakliye notu..." />
    <x-form.status data-shipment-carrier-total>Nakliyeci net toplamı hesaplanır</x-form.status>
</x-form.group>

<x-form.group legend="2. Kum ocağı bilgileri" icon="fa-solid fa-mountain" description="Tarih, kantardaki net miktar ve yükleme yeri">
    <x-form.input name="shipped_on" label="Sefer tarihi" type="date" required />
    <x-form.input name="net_kg" label="Net miktar (kg)" type="number" step="1" min="1" required data-shipment-amount />
    <x-form.input name="loading_place" label="Ocak (yükleme yeri)" maxlength="120" required placeholder="Ocak adı..." />
    <x-form.input name="scale_ticket_no" label="Kantar fiş no" maxlength="30" />
</x-form.group>

<x-form.group legend="3. Müşteri (satış) bilgileri" icon="fa-solid fa-user-tie" description="Yeni sevkiyat adresi yazılırsa müşteriye kaydedilir">
    <x-form.select name="customer_party_id" label="Müşteri cari" :options="$options['parties']" placeholder="Seçin" searchable required data-shipment-customer />
    <x-form.input name="delivery_address" label="Sevkiyat adresi" maxlength="255" :value="$current?->deliveryAddress?->name" list="shipment-addresses" placeholder="Önce cari, sonra adres" />
    <datalist id="shipment-addresses" data-shipment-addresses></datalist>
    <x-form.money name="sale_price" label="Nakliye hizmet bedeli (TL / ton)" step="0.0001" required data-shipment-amount />
    <x-form.select name="vat_rate" label="KDV oranı" :options="$options['vat']" :value="$current ? (string) (int) $current->vat_rate : '20'" data-shipment-tax />
    <x-form.select name="withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-tax />
    <x-form.input name="customer_note" label="Müşteri satış açıklaması" maxlength="255" placeholder="Müşteri notu..." />
    <x-form.status data-shipment-customer-total>Müşteri satış net toplamı hesaplanır</x-form.status>
</x-form.group>

<x-form.group legend="4. Referans / hakediş bilgileri (isteğe bağlı)" icon="fa-solid fa-handshake" description="Referans varsa hakedişi carisine alacak yazılır">
    <x-form.select name="commission_party_id" label="Referans cari" :options="$options['parties']" placeholder="Yönlendiren cari seçin..." searchable />
    <x-form.money name="commission_amount" label="Referans hakedişi (TL)" step="0.01" data-shipment-amount>
        <x-form.status>Bu tutar referans carisine alacak olarak yazılır ve kârdan düşer</x-form.status>
    </x-form.money>
    <x-form.input name="commission_note" label="Referans açıklaması" maxlength="255" placeholder="Referans notu..." />
    <x-form.status data-shipment-total>Sefer kârı hesaplanır</x-form.status>
</x-form.group>
