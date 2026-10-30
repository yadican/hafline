<div class="panel-form-grid is-detail">
    <x-form.group legend="Stok kalemi" icon="fa-solid fa-boxes-stacked" description="Ad, birim, stok takibi ve varsayılanlar">
        <x-form.field label="Ad">
            <div>{{ $stockItem->name }}</div>
        </x-form.field>
        <x-form.field label="Birim">
            <div>{{ $stockItem->unit->label() }}</div>
        </x-form.field>
        <x-form.field label="Stok takibi">
            <div>{{ $stockItem->tracks_stock ? 'Evet' : 'Hayır' }}</div>
        </x-form.field>
        <x-form.field label="Varsayılan gider kategorisi">
            <div>{{ $stockItem->cashCategory?->fullName() ?? '—' }}</div>
        </x-form.field>
        <x-form.field label="Varsayılan satış fiyatı">
            <div><x-ui.money :value="$stockItem->sales_price" :decimals="4" /></div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$stockItem->active" /></div>
        </x-form.field>
    </x-form.group>
</div>
