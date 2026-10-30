{{-- fields.blade.php içindeki x-data'yı kullanır: satırlar Alpine ile eklenir/silinir, toplamlar canlı hesaplanır --}}
<x-form.group legend="2. Fatura satırları" icon="fa-solid fa-list" description="Tutar = miktar × birim fiyat; KDV satır tutarı üzerinden hesaplanır" class="is-wide">
    <label class="panel-choice self-start">
        <input type="checkbox" x-model="vatOn" x-on:change="toggleVat()">
        KDV'li fatura (satır başına KDV oranı)
    </label>

    @error('lines')
        <div class="panel-field-error">{{ $message }}</div>
    @enderror

    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] border-separate border-spacing-x-2 border-spacing-y-1 text-sm">
            <thead class="text-left text-xs text-gray-500">
                <tr>
                    <th class="w-48 font-semibold">Stok kalemi</th>
                    <th class="font-semibold">Açıklama</th>
                    <th class="w-28 font-semibold">Miktar</th>
                    <th class="w-36 font-semibold">Birim fiyat</th>
                    <th class="w-24 font-semibold" x-show="vatOn" x-cloak>KDV %</th>
                    <th class="w-48 font-semibold">Kategori</th>
                    <th class="w-36 text-right font-semibold">Tutar</th>
                    <th class="w-10"><span class="sr-only">Sil</span></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(line, index) in lines" :key="line.key">
                    <tr class="align-top">
                        <td>
                            <select class="slct w-full" :name="`lines[${index}][stock_item_id]`" x-model="line.stock_item_id" x-on:change="pickStockItem(line)" aria-label="Stok kalemi">
                                <option value="">Kalem yok</option>
                                <template x-for="(name, id) in stockItems" :key="id">
                                    <option :value="id" x-text="name"></option>
                                </template>
                            </select>
                            <div class="panel-field-error" x-show="line.errors.stock_item_id" x-text="line.errors.stock_item_id" x-cloak></div>
                        </td>
                        <td>
                            <input type="text" class="slct w-full" maxlength="255" required placeholder="Malzeme / hizmet" :name="`lines[${index}][description]`" x-model="line.description" aria-label="Satır açıklaması">
                            <div class="panel-field-error" x-show="line.errors.description" x-text="line.errors.description" x-cloak></div>
                        </td>
                        <td>
                            <input type="number" class="slct w-full" step="0.001" min="0.001" required inputmode="decimal" :name="`lines[${index}][quantity]`" x-model="line.quantity" aria-label="Miktar">
                            <div class="panel-field-error" x-show="line.errors.quantity" x-text="line.errors.quantity" x-cloak></div>
                        </td>
                        <td>
                            <input type="number" class="slct w-full" step="0.0001" min="0" required inputmode="decimal" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" aria-label="Birim fiyat">
                            <div class="panel-field-error" x-show="line.errors.unit_price" x-text="line.errors.unit_price" x-cloak></div>
                        </td>
                        <td x-show="vatOn" x-cloak>
                            <input type="number" class="slct w-full" step="0.01" min="0" max="100" inputmode="decimal" x-model="line.vat_rate" aria-label="KDV oranı">
                            <div class="panel-field-error" x-show="line.errors.vat_rate" x-text="line.errors.vat_rate" x-cloak></div>
                        </td>
                        <td>
                            <input type="hidden" :name="`lines[${index}][vat_rate]`" :value="vatOn ? line.vat_rate : '0'">
                            <select class="slct w-full" :name="`lines[${index}][cash_category_id]`" x-on:change="line.cash_category_id = $event.target.value" aria-label="Kategori">
                                <option value="">Kategori yok</option>
                                <template x-for="(name, id) in categories[direction]" :key="id">
                                    <option :value="id" :selected="id === line.cash_category_id" x-text="name"></option>
                                </template>
                            </select>
                            <div class="panel-field-error" x-show="line.errors.cash_category_id" x-text="line.errors.cash_category_id" x-cloak></div>
                        </td>
                        <td class="pt-2 text-right tabular-nums">
                            <strong x-text="money(subtotal(line) + vat(line))"></strong>
                            <div class="text-xs text-gray-500" x-show="vatOn && vat(line) > 0" x-text="'KDV ' + money(vat(line))" x-cloak></div>
                        </td>
                        <td>
                            <button type="button" class="panel-act panel-act-del" title="Satırı sil" x-on:click="remove(index)">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                <span class="sr-only">Satırı sil</span>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <x-ui.button icon="fa-solid fa-plus" x-on:click="add()">Satır ekle</x-ui.button>

        <div class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm sm:w-80">
            <template x-if="vatOn">
                <div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Ara toplam (matrah):</span>
                        <strong x-text="money(sum((line) => subtotal(line)))"></strong>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">+ KDV:</span>
                        <span x-text="money(sum((line) => vat(line)))"></span>
                    </div>
                </div>
            </template>
            <div class="flex justify-between font-bold" :class="vatOn && 'mt-1 border-t border-gray-200 pt-2'">
                <span>FATURA TOPLAMI:</span>
                <span x-text="money(sum((line) => subtotal(line) + vat(line)))"></span>
            </div>
        </div>
    </div>
</x-form.group>
