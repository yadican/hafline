<input type="hidden" name="kind" :value="kind">

<x-form.group legend="Hareket" icon="fa-solid fa-right-left" description="Eski kasa menüsündeki para girişleri">
    <x-form.field label="İşlem" for="kind">
        <select data-searchable-select id="kind" class="slct" x-model="kind" @disabled(! empty($locked))>
            @foreach ($options['kinds'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </x-form.field>
    <div x-show="['supplier', 'customer', 'fuel', 'market', 'site', 'cash', 'opening'].includes(kind)" x-cloak x-data="{ directions: @js($options['directions']) }">
        @php($direction = old('direction', $form['direction'] ?? null))
        <x-form.field label="Yön" name="direction">
            <div class="panel-choices" role="radiogroup">
                @foreach (['debit', 'credit'] as $side)
                    <label class="panel-choice">
                        <input type="radio" name="direction" value="{{ $side }}" @checked($direction === $side)>
                        <span x-text="directions[kind]?.{{ $side }}">{{ $side === 'debit' ? 'Borç' : 'Alacak' }}</span>
                    </label>
                @endforeach
            </div>
        </x-form.field>
        <p class="panel-hint" x-show="['supplier', 'customer', 'fuel', 'market'].includes(kind)">Fatura için <a href="{{ route('admin.invoices.index') }}">Faturalar</a> ekranını kullanın. Burada yalnız ödeme / tahsilat girilir; ödeme tipi zorunludur.</p>
        <p class="panel-hint" x-show="kind === 'opening'" x-cloak>Açılış bakiyesi kasayı etkilemez; yalnızca carinin başlangıç durumunu yazar.</p>
    </div>
    <x-form.money name="amount" label="Tutar" required />
    <x-form.input name="occurred_on" label="Tarih" type="date" required />
    @php($method = old('payment_method', ($form['payment_method'] ?? null) instanceof \BackedEnum ? $form['payment_method']->value : ($form['payment_method'] ?? '')))
    <div x-show="! ['cash', 'opening', 'party_transfer', 'site_bill', 'account_transfer'].includes(kind)" x-cloak x-data="{ method: @js((string) $method) }">
        <x-form.select searchable name="payment_method" label="Ödeme tipi" placeholder="Seçin" :options="$options['methods']" x-model="method" x-bind:required="['supplier', 'customer', 'fuel', 'market'].includes(kind)" />
        <div x-show="['cheque', 'promissory_note'].includes(method) && kind !== 'vehicle_receivable'" x-cloak>
            <x-form.input name="cheque_no" label="Çek / senet no" maxlength="20" />
            <x-form.input name="due_on" label="Vade" type="date" x-bind:required="['cheque', 'promissory_note'].includes(method) && kind !== 'vehicle_receivable'" />
            <div x-show="method === 'cheque'">
                <x-form.input name="instrument_bank" label="Çekin bankası" maxlength="100" />
            </div>
            <x-form.input name="instrument_drawer" label="Keşideci" maxlength="120" />
            <x-form.field label="Resim" for="instrument-photos" name="photos">
                <input id="instrument-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="panel-file">
            </x-form.field>
            <p class="panel-hint">Cari bu kayıtla hemen kapanır, çek / senet portföye girer. Tahsil, ciro, ödeme ve karşılıksız işlemleri Çek / Senet ekranından yapılır.</p>
            @if ($instrument = $form['instrument'] ?? null)
                <p class="panel-hint">Portföy durumu: <a href="{{ route('admin.financial-instruments.show', $instrument) }}">{{ $instrument->status->label() }}</a>. İşlem görmüş bir çek / senedin tutarı, yönü, carisi ve ödeme tipi değiştirilemez.</p>
            @endif
        </div>
    </div>
    <div x-show="! ['opening', 'party_transfer', 'site_bill', 'account_transfer'].includes(kind)" x-cloak>
        <x-form.select searchable name="bank_id" label="Hesap" placeholder="Hesap seçin" :options="$options['banks']" x-bind:disabled="kind === 'account_transfer'" />
        <p class="panel-hint">Nakitte kasa, havalede banka, kartta POS veya kredi kartı hesabı zorunludur. Çek, senet ve mahsupta boş bırakılır.</p>
    </div>
    <div x-show="kind === 'account_transfer'" x-cloak>
        @foreach (['bank_id' => 'Çıkan hesap', 'counter_bank_id' => 'Giren hesap'] as $accountField => $accountLabel)
            <x-form.field :label="$accountLabel" for="transfer-{{ $accountField }}" :name="$accountField">
                <select data-searchable-select data-placeholder="{{ $accountLabel }} seçin" id="transfer-{{ $accountField }}" name="{{ $accountField }}" class="slct" x-bind:disabled="kind !== 'account_transfer'">
                    <option value="">{{ $accountLabel }} seçin</option>
                    @foreach ($options['banks'] as $id => $name)
                        <option value="{{ $id }}" @selected((string) old($accountField, $form[$accountField] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </x-form.field>
        @endforeach
    </div>
    <div x-show="! ['opening', 'party_transfer', 'site_bill', 'account_transfer'].includes(kind)" x-cloak>
        <x-form.select searchable name="cash_category_id" label="Kasa kategorisi" placeholder="Kategori yok" :options="$options['categories']" />
    </div>
    <p class="panel-hint" x-show="kind === 'site_bill'" x-cloak>Şantiyenin dönem hakedişi: şantiye carisini bu tutarla borçlandırır. Ödeme değildir; tahsilat "Şantiye Gelir / Gider" ile kasa girişi olarak girilir.</p>
    <p class="panel-hint" x-show="kind === 'site'" x-cloak>Kasa girişi: şantiyeden alınan ödeme; şantiyenin carisi varsa ona yazılır. Kasa çıkışı: şantiyede yapılan gider; hiçbir cariye yazılmaz.</p>
    <p class="panel-hint" x-show="kind === 'fuel'" x-cloak>Yakıt fişleri istasyonu alış fiyatıyla kendiliğinden alacaklandırır; saha pompasının yakıt dolumu da pompa kartından girilir. Buraya sadece fişe bağlı olmayan alımları ve istasyona yapılan ödemeleri girin, yoksa aynı litre iki kez yazılır.</p>
    <p class="panel-hint" x-show="kind === 'opening'" x-cloak>Carinin sisteme geçmeden önceki bakiyesi. Her cari için bir kez girilir; para hareketi değildir, kasayı etkilemez.</p>
    <p class="panel-hint" x-show="kind === 'party_transfer'" x-cloak>Mahsup: borçlanan carinin bakiyesi artar, alacaklanan carinin bakiyesi aynı tutarda azalır. Kasa ve banka etkilenmez.</p>
    <p class="panel-hint" x-show="kind === 'account_transfer'" x-cloak>Kendi hesaplarımız arasında para aktarımı: bankadan nakit çekim, kasadan bankaya yatırma, POS'un bankaya geçmesi, kredi kartı borcunun ödenmesi. Tutar çıkan hesaptan düşer, giren hesaba eklenir; hiçbir cari etkilenmez.</p>
    <x-form.input name="description" label="Açıklama" maxlength="255" />
</x-form.group>

<x-form.group legend="Bağ" icon="fa-solid fa-link" description="İşleme göre cari, plaka veya yer">
    @foreach (['supplier' => 'Satıcı', 'customer' => 'Alıcı', 'fuel' => 'Akaryakıt firması', 'market' => 'Piyasa firması', 'owner_payment' => 'Araç sahibi', 'opening' => 'Cari', 'party_transfer' => 'Borçlanacak cari'] as $roleKind => $partyLabel)
        <div x-show="kind === '{{ $roleKind }}'" x-cloak>
            <x-form.field :label="$partyLabel" for="party-{{ $roleKind }}" name="party_id">
                <select data-searchable-select id="party-{{ $roleKind }}" name="party_id" class="slct" data-cari-party x-bind:disabled="kind !== '{{ $roleKind }}'">
                    <option value="">{{ $partyLabel }} seçin</option>
                    @foreach ($options['parties'][$roleKind] ?? [] as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('party_id', $form['party_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </x-form.field>
        </div>
    @endforeach
    <div x-show="kind === 'party_transfer'" x-cloak>
        <x-form.field label="Alacaklanacak cari" for="counter-party" name="counter_party_id">
            <select data-searchable-select id="counter-party" name="counter_party_id" class="slct" data-cari-counter-party x-bind:disabled="kind !== 'party_transfer'">
                <option value="">Alacaklanacak cari seçin</option>
                @foreach ($options['parties']['party_transfer'] ?? [] as $id => $name)
                    <option value="{{ $id }}" @selected((string) old('counter_party_id', $form['counter_party_id'] ?? '') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </x-form.field>
    </div>
    <div x-show="['supplier', 'customer', 'fuel', 'market', 'owner_payment', 'opening', 'party_transfer'].includes(kind)" x-cloak>
        <div class="panel-cari-summary is-idle" data-cari-party-status aria-live="polite">Firma veya kişi seçilince cari durumu gelir</div>
    </div>
    <div x-show="kind === 'party_transfer'" x-cloak>
        <div class="panel-cari-summary is-idle" data-cari-counter-status aria-live="polite">Alacaklanacak cari seçilince cari durumu gelir</div>
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
    <div x-show="kind === 'vehicle_payment'" x-cloak>
        <x-form.input name="receiver_name" label="Teslim alan" maxlength="120" />
        <x-form.input name="receiver_national_id" label="Teslim alanın TC kimlik no" maxlength="11" inputmode="numeric" />
        <p class="panel-hint">Plaka Geçici Cari'deyse parayı teslim alanın adı ve TC kimlik numarası zorunludur.</p>
    </div>
    <div x-show="kind === 'dump_payment'" x-cloak>
        <x-form.select searchable name="dump_site_id" label="Döküm yeri" placeholder="Döküm yeri seçin" :options="$options['dumpSites']" />
    </div>
    <div x-show="['supplier', 'customer', 'fuel', 'market', 'site', 'site_bill'].includes(kind)" x-cloak>
        <x-form.select searchable name="site_id" label="Şantiye" placeholder="Şantiye yok" :options="$options['sites']" />
    </div>
    <div x-show="['supplier', 'customer', 'fuel'].includes(kind)" x-cloak>
        <x-form.select searchable name="fuel_station_id" label="Yakıt yeri" placeholder="Yakıt yeri yok" :options="$options['stations']" />
    </div>
    <div x-show="kind === 'fuel'" x-cloak>
        <x-form.input name="liters" label="Litre" type="number" step="0.01" min="0" />
        <x-form.money name="unit_price" label="Birim fiyat" step="0.0001" />
    </div>
    <div x-show="['supplier', 'customer', 'fuel', 'market'].includes(kind)" x-cloak>
        <x-form.input name="document_no" label="Belge no" maxlength="40" />
    </div>
    <p class="panel-hint">Fiş teslimi, yakıt kontrolü, döküm kontrolü, plaka cezası ve personel maaş/pirim bu ekranda yok; kendi kayıtlarıdır. Çek ve senet, ödeme tipi olarak seçilir ve Çek / Senet portföyünden takip edilir.</p>
</x-form.group>
