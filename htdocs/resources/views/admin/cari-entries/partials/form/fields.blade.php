<input type="hidden" name="kind" :value="kind">

<x-form.group legend="Hareket" icon="fa-solid fa-right-left" description="Eski kasa menüsündeki para girişleri">
    <x-form.field label="İşlem" for="kind">
        <select id="kind" class="slct" x-model="kind" @disabled(! empty($locked))>
            @foreach ($options['kinds'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </x-form.field>
    <div x-show="['supplier', 'customer', 'fuel', 'market', 'site', 'cash'].includes(kind)" x-cloak>
        <x-form.radios name="direction" label="Yön" :options="['debit' => 'Borç', 'credit' => 'Alacak']" />
        <p class="panel-hint" x-show="kind !== 'cash'">Borç: cari bize borçlu veya biz ödedik. Alacak: biz cariye borçluyuz.</p>
        <p class="panel-hint" x-show="kind === 'cash'" x-cloak>Kasada borç, kasaya giren paradır. Alacak, kasadan çıkan paradır.</p>
    </div>
    <x-form.money name="amount" label="Tutar" required />
    <x-form.input name="occurred_on" label="Tarih" type="date" required />
    <div x-show="kind !== 'cash'" x-cloak>
        <x-form.select name="payment_method" label="Ödeme tipi" placeholder="Seçilmedi" :options="$options['methods']" />
    </div>
    <x-form.select name="cash_category_id" label="Kasa kategorisi" placeholder="Kategori yok" :options="$options['categories']" />
    <x-form.input name="description" label="Açıklama" maxlength="255" />
</x-form.group>

<x-form.group legend="Bağ" icon="fa-solid fa-link" description="İşleme göre cari, plaka veya yer">
    @foreach (['supplier' => 'Satıcı', 'customer' => 'Alıcı', 'fuel' => 'Akaryakıt firması', 'market' => 'Piyasa firması', 'owner_payment' => 'Araç sahibi'] as $roleKind => $partyLabel)
        <div x-show="kind === '{{ $roleKind }}'" x-cloak>
            <x-form.field :label="$partyLabel" for="party-{{ $roleKind }}" name="party_id">
                <select id="party-{{ $roleKind }}" name="party_id" class="slct" data-cari-party x-bind:disabled="kind !== '{{ $roleKind }}'">
                    <option value="">{{ $partyLabel }} seçin</option>
                    @foreach ($options['parties'][$roleKind] ?? [] as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('party_id', $form['party_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </x-form.field>
        </div>
    @endforeach
    <div x-show="['supplier', 'customer', 'fuel', 'market', 'owner_payment'].includes(kind)" x-cloak>
        <div class="panel-cari-summary is-idle" data-cari-party-status aria-live="polite">Firma veya kişi seçilince cari durumu gelir</div>
    </div>
    <div x-show="['vehicle_payment', 'vehicle_receivable', 'market'].includes(kind)" x-cloak>
        <x-form.autocomplete
            name="plate"
            label="Plaka"
            :url="route('admin.fuel-receipts.lookups.vehicles')"
            :value="$form['plate'] ?? null"
            placeholder="34 ABC 123"
            maxlength="20"
            class="panel-plate"
        />
    </div>
    <div x-show="['vehicle_payment', 'vehicle_receivable'].includes(kind)" x-cloak>
        <div class="panel-cari-summary is-idle" data-cari-plate-status aria-live="polite">Plaka girilince araç sahibinin cari durumu gelir</div>
    </div>
    <div x-show="kind === 'dump_payment'" x-cloak>
        <x-form.select name="dump_site_id" label="Döküm yeri" placeholder="Döküm yeri seçin" :options="$options['dumpSites']" />
        <x-form.input name="cheque_no" label="Çek no" maxlength="20" />
    </div>
    <div x-show="['supplier', 'customer', 'fuel', 'market', 'site'].includes(kind)" x-cloak>
        <x-form.select name="site_id" label="Şantiye" placeholder="Şantiye yok" :options="$options['sites']" />
    </div>
    <div x-show="['supplier', 'customer', 'fuel'].includes(kind)" x-cloak>
        <x-form.select name="fuel_station_id" label="Yakıt yeri" placeholder="Yakıt yeri yok" :options="$options['stations']" />
    </div>
    <div x-show="kind === 'fuel'" x-cloak>
        <x-form.input name="liters" label="Litre" type="number" step="0.01" min="0" />
        <x-form.money name="unit_price" label="Birim fiyat" step="0.0001" />
    </div>
    <div x-show="['supplier', 'customer', 'fuel', 'market'].includes(kind)" x-cloak>
        <x-form.input name="document_no" label="Belge no" maxlength="40" />
    </div>
    <div x-show="kind === 'cash'" x-cloak>
        <x-form.select name="bank_id" label="Banka" placeholder="Banka yok" :options="$options['banks']" />
    </div>
    <p class="panel-hint">Fiş teslimi, yakıt kontrolü, döküm kontrolü, plaka cezası ve personel maaş/pirim bu ekranda yok; kendi kayıtlarıdır. Çek ve senet, ödeme tipi olarak seçilir.</p>
</x-form.group>
