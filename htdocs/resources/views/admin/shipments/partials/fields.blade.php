<x-form.group legend="1. Nakliyeci" icon="fa-solid fa-truck-moving" description="Plaka girilince nakliyeci o günkü sahibinden gelir; nakliyeci faturalıysa KDV ve tevkifat seçin">
    <x-form.radios name="kind" label="Sefer türü" :options="$options['kinds']" />
    <x-form.autocomplete
        name="plate"
        label="Araç plakası"
        :url="route('admin.shipments.lookups.vehicles')"
        :value="$shipment?->vehicle?->plate"
        placeholder="34 ABC 123"
        maxlength="20"
        required
        class="panel-plate"
    >
        <x-form.status>Tanımsız plakayı araç kartından Geçici Cari'ye ekleyin</x-form.status>
    </x-form.autocomplete>
    <x-form.input name="waybill_no" label="İrsaliye no" maxlength="30" />
    <x-form.money name="carrier_price" label="Fiyat (TL / birim)" step="0.0001" data-shipment-price="carrier_price" />
    <x-form.select name="carrier_vat_rate" label="KDV oranı" :options="$options['vat']" :value="$shipment ? (string) (int) $shipment->carrier_vat_rate : '0'" data-shipment-tax />
    <x-form.select name="carrier_withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-tax />
    <x-form.status data-shipment-carrier-total>Nakliyeci net toplamı hesaplanır</x-form.status>
</x-form.group>

<x-form.group legend="2. Ocak (yükleme)" icon="fa-solid fa-mountain" description="Tarih, yükleme yeri, malzeme ve kantar bilgisi">
    <x-form.input name="shipped_on" label="Sefer tarihi" type="date" required data-shipment-price-key />
    <x-form.select name="quarry_id" label="Ocak / yükleme yeri" :options="$options['quarries']" placeholder="Seçin" searchable required data-shipment-price-key />
    <x-form.select name="material_id" label="Malzeme" :options="$options['materials']" placeholder="Seçin" searchable required data-shipment-price-key />
    <div x-show="units[material] !== 'm3'">
        <x-form.input name="net_kg" label="Net miktar (kg)" type="number" step="1" min="1" data-shipment-kg />
    </div>
    <div x-show="units[material] === 'm3'" x-cloak>
        <x-form.input name="quantity" label="Miktar (m³)" type="number" step="0.001" min="0.001" data-shipment-quantity />
    </div>
    <x-form.input name="scale_ticket_no" label="Kantar fiş no" maxlength="30" />
    <div x-show="kind === 'material_sale'" x-cloak>
        <x-form.money name="quarry_price" label="Ocak alış fiyatı (TL / birim)" step="0.0001" data-shipment-price="quarry_price" />
        <x-form.input name="quarry_invoice_no" label="Ocak fatura no" maxlength="30" />
    </div>
</x-form.group>

<x-form.group legend="3. Müşteri (satış)" icon="fa-solid fa-user-tie" description="Sevk yeri seçilince müşteri gelir; birim fiyat KDV hariçtir">
    <x-form.select name="delivery_address_id" label="Müşteri · sevk yeri" :options="$options['addresses']" placeholder="Seçin" searchable required data-shipment-price-key />
    <x-form.money name="sale_price" label="Satış / nakliye hizmet bedeli (TL / birim)" step="0.0001" data-shipment-price="sale_price" />
    <x-form.select name="vat_rate" label="KDV oranı" :options="$options['vat']" :value="$shipment ? (string) (int) $shipment->vat_rate : '20'" data-shipment-tax />
    <x-form.select name="withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-tax />
    <x-form.status data-shipment-customer-total>Müşteri net toplamı hesaplanır</x-form.status>
</x-form.group>

<x-form.group legend="4. Referans / komisyon (isteğe bağlı)" icon="fa-solid fa-handshake" description="Referans varsa hakedişi carisine alacak yazılır">
    <x-form.select name="commission_party_id" label="Referans cari" :options="$options['commissions']" placeholder="Referans yok" searchable />
    <x-form.money name="commission_amount" label="Referans hakedişi (TL)" step="0.01" data-shipment-commission />
    <x-form.input name="description" label="Açıklama" maxlength="255" />
    <x-form.status data-shipment-total>Sefer kârı hesaplanır</x-form.status>
</x-form.group>
