<x-form.group legend="Stok sayımı" icon="fa-solid fa-clipboard-check" description="Kalem, sayım tarihi ve sayılan miktar; fark stoğu düzeltir, cariye yazılmaz">
    <x-form.select searchable name="stock_item_id" label="Stok kalemi" placeholder="Seçin" :options="$stock_items" required autofocus />
    <x-form.input name="counted_on" label="Sayım tarihi" type="date" required />
    <x-form.input name="quantity" label="Sayılan miktar" type="number" step="0.001" min="0" required />
    <p class="panel-hint">Fark = sayılan − o günün defter stoğu. Fazla giriş ortalama maliyetle; eksik çıkış kullanım gibi. Cariye yazılmaz.</p>
    <x-form.input name="description" label="Açıklama" maxlength="255" placeholder="Boş bırakılırsa otomatik doldurulur" />
</x-form.group>
