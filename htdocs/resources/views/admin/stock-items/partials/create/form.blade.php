<form method="POST" action="{{ route('admin.stock-items.store') }}">
    @csrf
    <x-form.grid :model="['unit' => \App\Enums\StockItemUnit::Piece->value, 'sales_price' => '0']">
        <x-form.group legend="Stok kalemi" icon="fa-solid fa-boxes-stacked" description="Ad, birim, stok takibi ve varsayılanlar">
            <x-form.input name="name" label="Ad" maxlength="100" required autofocus />
            <x-form.radios name="unit" label="Birim" :options="\App\Enums\StockItemUnit::options()" />
            <x-form.checkbox name="tracks_stock" label="Stok takibi" text="Miktar takip edilsin (giriş/çıkış)" checked />
            <x-form.select searchable name="cash_category_id" label="Varsayılan gider kategorisi" placeholder="Seçilmedi" :options="$cashCategories" />
            <x-form.money name="sales_price" label="Varsayılan satış fiyatı" step="0.0001" placeholder="0,0000" />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.stock-items.index')" />
    </x-form.grid>
</form>
