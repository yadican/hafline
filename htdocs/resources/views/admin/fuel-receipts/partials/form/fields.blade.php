<x-form.group legend="1. Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, tarih ve şantiye">
    <x-form.input name="ticket_no" label="Fiş No" maxlength="20" autofocus />
    <x-form.input name="issued_on" label="Tarih" type="date" :value="$receipt ? null : today()" required />
    <x-form.select searchable hide-search name="shift" label="Vardiya" placeholder="Vardiya yok" :options="$options['shifts']" />
    <x-form.select name="site_id" label="Şantiye" placeholder="Şantiye yok" :options="$options['sites']" searchable data-fuel-site />
</x-form.group>

<x-form.group legend="2. Veren: Yakıt yeri" icon="fa-solid fa-gas-pump" description="Yakıtın alındığı yer, litre ve alış fiyatı">
    <x-form.select name="fuel_station_id" label="Yakıt yeri" placeholder="İstasyon veya saha pompası seçin" :options="$options['stations']" required searchable data-fuel-station>
        <x-form.status data-fuel-station-hint>Seçilince yakıt yerinin fiyatları gelir</x-form.status>
    </x-form.select>
    <x-form.input name="quantity" label="Litre" type="number" step="0.01" min="0.01" required data-fuel-quantity />
    <div data-fuel-purchase-field>
        <x-form.money name="purchase_price" label="Alış fiyatı" step="0.0001" suffix="TL/lt" required data-fuel-purchase-price />
    </div>
    <div class="mt-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm">
        <div class="flex justify-between gap-3 font-bold text-red-700">
            <span data-fuel-cost-label>İSTASYONA ALACAK:</span>
            <span data-fuel-cost>—</span>
        </div>
        <div class="pt-1 text-red-600" data-fuel-cost-note>Litre × alış fiyatı istasyonun carisine alacak yazılır</div>
    </div>
</x-form.group>

<x-form.group legend="3. Alan: Araç" icon="fa-solid fa-truck" description="Plaka, araç sahibi ve satış fiyatı">
    <x-form.autocomplete
        name="plate"
        label="Plaka"
        :url="route('admin.fuel-receipts.lookups.vehicles')"
        :value="$receipt?->vehicle?->plate"
        placeholder="34 ABC 123"
        maxlength="20"
        required
        class="panel-plate"
        data-fuel-plate
    >
        <x-form.status data-fuel-plate-hint>Yazmaya başlayın; kayıtlı araçlar listelenir</x-form.status>
    </x-form.autocomplete>
    <x-form.money name="unit_price" label="Satış fiyatı" step="0.0001" suffix="TL/lt" data-fuel-price />
    <div class="mt-3 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm">
        <div class="flex justify-between gap-3 font-bold text-green-800">
            <span>ARAÇ SAHİBİNE BORÇ:</span>
            <span data-fuel-sale>—</span>
        </div>
        <div class="pt-1 text-green-700">Litre × satış fiyatı araç sahibinin carisine borç yazılır</div>
        <div class="mt-1 flex justify-between gap-3 border-t border-green-200 pt-2 font-bold text-gray-800" data-fuel-margin-row>
            <span>FARK (KOMİSYON):</span>
            <span data-fuel-margin>—</span>
        </div>
    </div>
</x-form.group>

<x-form.group legend="4. Diğer (isteğe bağlı)" icon="fa-solid fa-circle-info" description="Kilometre, alan kişi ve taşıma fiş adedi">
    <x-form.input name="km" label="Kilometre" type="number" step="1" min="0" />
    <x-form.input name="received_by" label="Alan kişi" maxlength="80" />
    <x-form.input name="haul_ticket_count" label="Taşıma fiş adedi" type="number" step="1" min="0" max="999">
        <x-form.status>Bu yakıtın karşılığı sayılan taşıma fişi. Raporlarda kullanılır; boş bırakılabilir.</x-form.status>
    </x-form.input>
</x-form.group>
