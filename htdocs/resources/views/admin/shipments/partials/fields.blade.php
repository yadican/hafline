<x-form.group legend="Sefer" icon="fa-solid fa-truck-moving" description="Tür, tarih ve plaka; nakliyeci plakanın o günkü sahibidir">
    <x-form.radios name="kind" label="Tür" :options="$options['kinds']" />
    <x-form.input name="shipped_on" label="Tarih" type="date" required data-shipment-price-key />
    <x-form.autocomplete
        name="plate"
        label="Plaka"
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
    <x-form.input name="scale_ticket_no" label="Kantar fiş no" maxlength="30" />
    <div x-show="kind === 'material_sale'" x-cloak>
        <x-form.input name="quarry_invoice_no" label="Ocak fatura no" maxlength="30" />
    </div>
</x-form.group>

<x-form.group legend="Güzergah ve malzeme" icon="fa-solid fa-route" description="Yüklendiği yer, malzeme, sevk yeri ve miktar; müşteri sevk yerinden gelir">
    <x-form.select name="quarry_id" label="Ocak / yükleme yeri" :options="$options['quarries']" placeholder="Seçin" searchable required data-shipment-price-key />
    <x-form.select name="material_id" label="Malzeme" :options="$options['materials']" placeholder="Seçin" searchable required data-shipment-price-key />
    <x-form.select name="delivery_address_id" label="Sevk yeri (müşteri)" :options="$options['addresses']" placeholder="Seçin" searchable required data-shipment-price-key />
    <x-form.input name="quantity" label="Miktar (malzemenin biriminde)" type="number" step="0.001" min="0.001" required data-shipment-quantity />
</x-form.group>

<x-form.group legend="Fiyat" icon="fa-solid fa-tags" description="Birim fiyatlar fiyat listesinden gelir, değiştirilebilir; boş bırakılırsa liste fiyatı kullanılır">
    <x-form.money name="sale_price" label="Müşteri birim fiyatı (KDV hariç)" step="0.0001" data-shipment-price="sale_price" />
    <x-form.money name="carrier_price" label="Nakliyeci birim fiyatı" step="0.0001" data-shipment-price="carrier_price" />
    <div x-show="kind === 'material_sale'" x-cloak>
        <x-form.money name="quarry_price" label="Ocak alış fiyatı" step="0.0001" data-shipment-price="quarry_price" />
    </div>
    <x-form.select name="vat_rate" label="KDV" :options="$options['vat']" :value="$shipment ? (string) (int) $shipment->vat_rate : '20'" data-shipment-vat />
    <x-form.select name="withholding_rate" label="Tevkifat" :options="$options['withholding']" data-shipment-withholding />
    <x-form.status data-shipment-total>Miktar ve fiyatlar girilince cari tutarları hesaplanır</x-form.status>
</x-form.group>

<x-form.group legend="Komisyon ve not" icon="fa-solid fa-handshake" description="Komisyoncu varsa tutarı komisyoncunun carisine alacak yazılır">
    <x-form.select name="commission_party_id" label="Komisyoncu" :options="$options['commissions']" placeholder="Komisyoncu yok" searchable />
    <x-form.money name="commission_amount" label="Komisyon tutarı" step="0.01" data-shipment-commission />
    <x-form.input name="description" label="Açıklama" maxlength="255" />
</x-form.group>
