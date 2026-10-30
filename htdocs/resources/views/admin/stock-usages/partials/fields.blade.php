<x-form.group legend="Stok kullanımı" icon="fa-solid fa-box-open" description="Kalem, miktar, tarih; şantiye veya kendi aracımız ve gider kategorisi">
    <x-form.select searchable name="stock_item_id" label="Stok kalemi" placeholder="Seçin" :options="$stock_items" required autofocus />
    <x-form.input name="quantity" label="Miktar" type="number" step="0.001" min="0.001" required />
    <x-form.input name="used_on" label="Tarih" type="date" required />
    <x-form.select searchable name="site_id" label="Şantiye" placeholder="Seçilmedi" :options="$sites" />
    <x-form.select searchable name="vehicle_id" label="Kendi aracımız" placeholder="Seçilmedi" :options="$vehicles" />
    <p class="panel-hint">Şantiye veya araçtan en az biri zorunlu. Cariye yazılmaz; maliyet giderdir.</p>
    <x-form.select searchable name="cash_category_id" label="Gider kategorisi" placeholder="Seçin" :options="$categories" required />
    <x-form.input name="description" label="Açıklama" maxlength="255" placeholder="Boş bırakılırsa otomatik doldurulur" />
</x-form.group>
