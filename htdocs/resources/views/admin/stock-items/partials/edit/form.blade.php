<form method="POST" action="{{ route('admin.stock-items.update', $stockItem) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$stockItem">
        <x-form.group legend="Stok kalemi" icon="fa-solid fa-boxes-stacked" description="Ad, birim, stok takibi ve varsayılanlar">
            <x-form.input name="name" label="Ad" maxlength="100" required autofocus />
            <x-form.radios name="unit" label="Birim" :options="\App\Enums\StockItemUnit::options()" />
            <x-form.checkbox name="tracks_stock" label="Stok takibi" text="Miktar takip edilsin (giriş/çıkış)" />
            <x-form.select searchable name="cash_category_id" label="Varsayılan gider kategorisi" placeholder="Seçilmedi" :options="$cashCategories" />
            <x-form.money name="sales_price" label="Varsayılan satış fiyatı" step="0.0001" placeholder="0,0000" />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.stock-items.index')" />
    </x-form.grid>
</form>
