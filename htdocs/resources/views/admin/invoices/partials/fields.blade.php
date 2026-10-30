@php
    $current = $invoice ?? null;
    $blankLine = ['description' => '', 'quantity' => '1', 'unit_price' => '', 'vat_rate' => '0', 'cash_category_id' => '', 'stock_item_id' => ''];
    $formLines = collect(old('lines', $lines))
        ->filter(fn ($line) => is_array($line))
        ->values()
        ->map(fn (array $line, int $index) => [
            ...$blankLine,
            ...array_map(fn ($value) => (string) $value, array_intersect_key($line, $blankLine)),
            'errors' => collect(array_keys($blankLine))
                ->mapWithKeys(fn (string $field) => [$field => $errors->first("lines.{$index}.{$field}")])
                ->filter()
                ->all(),
        ]);

    if ($formLines->isEmpty()) {
        $formLines->push([...$blankLine, 'errors' => []]);
    }

    $canPayAtEntry = $current === null && isset($paymentOptions) && auth()->user()?->can('create-cari-entry');
    $paymentStatus = $canPayAtEntry ? (string) old('payment_status', \App\Enums\InvoicePaymentStatus::Unpaid->value) : \App\Enums\InvoicePaymentStatus::Unpaid->value;
@endphp

<div
    class="contents"
    x-data="{
        direction: @js((string) old('direction', $current?->direction->value ?? 'purchase')),
        recipient: @js((string) old('recipient', $current?->vehicle_id ? 'plate' : 'party')),
        partyId: @js((string) old('party_id', $current?->party_id ?? '')),
        returnsInvoiceId: @js((string) old('returns_invoice_id', $current?->returns_invoice_id ?? '')),
        categories: @js($options['categories']),
        stockItems: @js($options['stock_items']),
        stockItemMeta: @js($options['stock_item_meta']),
        returnableInvoices: @js($options['returnable_invoices'] ?? []),
        lines: @js($formLines->all()),
        vatOn: @js($formLines->contains(fn (array $line) => (float) $line['vat_rate'] > 0)),
        paymentStatus: @js($paymentStatus),
        paymentMethod: @js((string) old('payment.payment_method', '')),
        nextKey: 0,
        init() {
            this.lines = this.lines.map((line) => ({ ...line, key: this.nextKey++ }));
        },
        add() {
            this.lines.push({ description: '', quantity: '1', unit_price: '', vat_rate: this.vatOn ? '20' : '0', cash_category_id: '', stock_item_id: '', errors: {}, key: this.nextKey++ });
        },
        remove(index) {
            this.lines.splice(index, 1);

            if (this.lines.length === 0) {
                this.add();
            }
        },
        isReturn() {
            return this.direction === 'purchase_return' || this.direction === 'sale_return';
        },
        isSale() {
            return this.direction === 'sale';
        },
        isPlateSale() {
            return this.isSale() && this.recipient === 'plate';
        },
        setDirection(value) {
            this.direction = value;
            if (! this.isSale()) {
                this.recipient = 'party';
            }
            if (! this.isReturn()) {
                this.returnsInvoiceId = '';
            }
            if (this.isReturn() || this.isPlateSale()) {
                this.paymentStatus = 'unpaid';
            }
            this.lines.forEach((line) => {
                if (! (line.cash_category_id in (this.categories[value] || {}))) {
                    line.cash_category_id = '';
                }
            });
        },
        setRecipient(value) {
            this.recipient = value;
            if (value === 'plate') {
                this.paymentStatus = 'unpaid';
            }
        },
        partyReturns() {
            const bucket = this.direction === 'sale_return' ? 'sale' : 'purchase';

            return ((this.returnableInvoices[bucket] || {})[this.partyId]) || {};
        },
        returnTargetLabel() {
            return this.direction === 'sale_return' ? 'satış' : 'alış';
        },
        pickStockItem(line) {
            const item = this.stockItemMeta[line.stock_item_id];

            if (! item) {
                return;
            }

            if (! line.description) {
                line.description = item.name;
            }

            if (! line.unit_price && item.sales_price) {
                line.unit_price = item.sales_price;
            }

            if (item.cash_category_id && (item.cash_category_id in this.categories[this.direction])) {
                line.cash_category_id = item.cash_category_id;
            }
        },
        toggleVat() {
            this.lines.forEach((line) => {
                line.vat_rate = ! this.vatOn ? '0' : (this.number(line.vat_rate) > 0 ? line.vat_rate : '20');
            });
        },
        number(value) {
            const number = Number(String(value ?? '').replace(',', '.'));

            return Number.isFinite(number) ? number : 0;
        },
        round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        },
        subtotal(line) {
            return this.round2(this.number(line.quantity) * this.number(line.unit_price));
        },
        vat(line) {
            return this.vatOn ? this.round2(this.subtotal(line) * this.number(line.vat_rate) / 100) : 0;
        },
        sum(amount) {
            return this.round2(this.lines.reduce((total, line) => total + amount(line), 0));
        },
        money(value) {
            return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + ' TL';
        },
    }"
    x-on:change="if ($event.target.name === 'direction') { setDirection($event.target.value) }"
    x-on:change="if ($event.target.name === 'recipient') { setRecipient($event.target.value) }"
    x-on:change="if ($event.target.name === 'party_id') { partyId = $event.target.value; if (! (returnsInvoiceId in partyReturns())) { returnsInvoiceId = '' } }"
>
    <x-form.group legend="1. Fatura bilgileri" icon="fa-solid fa-file-invoice" description="Alış tedarikçiye alacak, satış müşteriye borç (cari veya plaka), alış iadesi satıcıya borç, satış iadesi müşteriye alacak yazar; iade isteğe bağlı olarak açık faturanın kalanına düşer" class="is-wide">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.radios name="direction" label="Fatura türü" :options="$options['directions']" inline />
            <div x-show="isSale()" x-cloak>
                <x-form.radios name="recipient" label="Kime" :options="$options['recipients']" inline />
            </div>
            <template x-if="! isSale()">
                <input type="hidden" name="recipient" value="party">
            </template>
            <template x-if="! isPlateSale()">
                <div>
                    <x-form.select searchable name="party_id" label="Cari" :options="$options['parties']" placeholder="Cari seçin" required />
                </div>
            </template>
            <template x-if="isPlateSale()">
                <div>
                    <x-form.autocomplete
                        name="plate"
                        label="Plaka"
                        :url="route('admin.invoices.lookups.vehicles')"
                        :value="old('plate', $current?->vehicle?->plate)"
                        placeholder="34 ABC 123"
                        maxlength="20"
                        required
                        class="panel-plate"
                    >
                        <x-form.status>Yazmaya başlayın; kayıtlı araçlar listelenir. Cari, satış günündeki sahip olur.</x-form.status>
                    </x-form.autocomplete>
                </div>
            </template>
            <x-form.input name="invoice_no" label="Fatura no" maxlength="30" required placeholder="A-1023" />
            <x-form.input name="issued_on" label="Fatura tarihi" type="date" required />
            <x-form.select searchable name="site_id" label="Şantiye (isteğe bağlı)" :options="$options['sites']" placeholder="Şantiye yok" />
            <div x-show="isReturn()" x-cloak class="sm:col-span-2 lg:col-span-3">
                <x-form.field label="Bağlı fatura (isteğe bağlı)" name="returns_invoice_id">
                    <select name="returns_invoice_id" class="panel-input" x-model="returnsInvoiceId">
                        <option value="">Bağlama — yalnız cariye yaz</option>
                        <template x-for="(label, id) in partyReturns()" :key="id">
                            <option :value="id" x-text="label" :selected="returnsInvoiceId === id"></option>
                        </template>
                    </select>
                    <p class="panel-hint" x-text="'Seçilirse iade tutarı o ' + returnTargetLabel() + ' faturasının kalanından düşülür.'">Seçilirse iade tutarı bağlı faturanın kalanından düşülür.</p>
                </x-form.field>
            </div>
            @if ($canPayAtEntry)
                <x-form.field label="Ödeme durumu" name="payment_status" x-show="! isReturn() && ! isPlateSale()" x-cloak>
                    <div class="panel-choices is-inline" role="radiogroup">
                        @foreach ($paymentOptions['statuses'] as $status => $labels)
                            <label class="panel-choice">
                                <input type="radio" name="payment_status" value="{{ $status }}" x-model="paymentStatus" @checked($paymentStatus === $status)>
                                <span x-text="@js($labels)[direction]">{{ $labels[old('direction', 'purchase')] ?? reset($labels) }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-form.field>
                <template x-if="isReturn() || isPlateSale()">
                    <input type="hidden" name="payment_status" value="unpaid">
                </template>
            @endif
            <div x-show="paymentStatus !== 'paid'">
                <x-form.input name="due_on" label="Vade tarihi (isteğe bağlı)" type="date" />
            </div>
        </div>
        @if ($canPayAtEntry)
            <div x-show="! isReturn() && ! isPlateSale()" x-cloak>
                @include('admin.invoices.partials.entry-payment')
            </div>
        @endif
        <x-form.input name="description" label="Açıklama" maxlength="255" placeholder="Fatura notu..." />
    </x-form.group>

    @include('admin.invoices.partials.lines')

    @php($attachmentRoom = \App\Services\InvoiceService::MAX_ATTACHMENTS - ($current?->attachments_count ?? 0))
    <x-form.group legend="3. Dosyalar" icon="fa-solid fa-paperclip" description="Faturanın resmi ya da PDF'i (isteğe bağlı); eklenen dosyalar fatura detayından silinir" class="is-wide">
        @if ($attachmentRoom > 0)
            @include('admin.invoices.partials.attachment-input', ['label' => 'Dosya ekle', 'room' => $attachmentRoom])
        @else
            <p class="panel-hint">Bu faturada en fazla {{ \App\Services\InvoiceService::MAX_ATTACHMENTS }} dosya olabilir; yenisi için fatura detayından birini silin.</p>
        @endif
    </x-form.group>
</div>
