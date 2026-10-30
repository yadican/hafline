@php
    $accountlessMethods = collect(\App\Enums\PaymentMethod::cases())->filter(fn ($method) => $method->accountTypes() === [])->map->value->values()->all();
    $instrumentMethods = collect(\App\Enums\PaymentMethod::cases())->filter->hasDueDate()->map->value->values()->all();
@endphp
<div
    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
    x-data="{ accountless: @js($accountlessMethods), instruments: @js($instrumentMethods) }"
    x-show="['paid', 'partial'].includes(paymentStatus)"
    x-cloak
>
    <div x-show="paymentStatus === 'partial'" x-cloak>
        <x-form.money name="payment.amount" label="Tutar" x-bind:required="paymentStatus === 'partial'" />
    </div>
    <x-form.select searchable name="payment.payment_method" label="Ödeme tipi" placeholder="Ödeme tipi seçin" :options="$paymentOptions['methods']" x-model="paymentMethod" x-bind:required="['paid', 'partial'].includes(paymentStatus)" />
    <div x-show="! accountless.includes(paymentMethod)">
        <x-form.select searchable name="payment.bank_id" label="Hesap" placeholder="Hesap seçin" :options="$paymentOptions['accounts']" />
    </div>
    <div x-show="instruments.includes(paymentMethod)" x-cloak>
        <x-form.input name="payment.cheque_no" label="Çek / senet no" maxlength="20" />
    </div>
    <div x-show="instruments.includes(paymentMethod)" x-cloak>
        <x-form.input name="payment.due_on" label="Çek / senet vadesi" type="date" x-bind:required="['paid', 'partial'].includes(paymentStatus) && instruments.includes(paymentMethod)" />
    </div>
</div>
<p class="panel-hint" x-show="paymentStatus === 'paid'" x-cloak>Fatura toplamı fatura tarihinde cari hareketi olarak yazılır. Nakitte kasa, havalede banka, kartta POS veya kredi kartı hesabı seçilir; çek / senet portföye girer.</p>
<p class="panel-hint" x-show="paymentStatus === 'partial'" x-cloak>Ödenen tutar fatura tarihinde cari hareketi olarak yazılır; kalan için vade tarihi girilebilir. Tutar faturadan büyük olamaz, toplama eşitse fatura "Ödendi" sayılır.</p>
