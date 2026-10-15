<x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, araç ve tarih">
    <x-form.input name="ticket_no" label="Fiş No" maxlength="20" autofocus />
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
    <x-form.input name="issued_on" label="Tarih" type="date" :value="$receipt ? null : today()" required />
    <x-form.select name="shift" label="Vardiya" placeholder="Vardiya yok" :options="$options['shifts']" />
</x-form.group>

<x-form.group legend="Yakıt" icon="fa-solid fa-gas-pump" description="Yakıt yeri, litre ve birim fiyat">
    <x-form.select name="fuel_station_id" label="Yakıt yeri" placeholder="Yakıt yeri seçin" :options="$options['stations']" required searchable data-fuel-station>
        <x-form.status data-fuel-station-hint>Seçilince istasyonun birim fiyatı gelir</x-form.status>
    </x-form.select>
    <x-form.select name="site_id" label="Şantiye" placeholder="Şantiye yok" :options="$options['sites']" searchable data-fuel-site />
    <x-form.input name="quantity" label="Litre" type="number" step="0.01" min="0.01" required data-fuel-quantity />
    <x-form.money name="unit_price" label="Birim fiyat" step="0.0001" data-fuel-price>
        <x-form.status data-fuel-total>Litre × birim fiyat araç sahibine borç yazılır</x-form.status>
    </x-form.money>
    <x-form.input name="km" label="Kilometre" type="number" step="1" min="0" />
    <x-form.input name="received_by" label="Alan kişi" maxlength="80" />
    <x-form.input name="haul_ticket_count" label="Taşıma fiş adedi" type="number" step="1" min="0" max="999">
        <x-form.status>Bu yakıtın karşılığı sayılan taşıma fişi. Raporlarda kullanılır; boş bırakılabilir.</x-form.status>
    </x-form.input>
</x-form.group>
