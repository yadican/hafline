@php
    $previous = now()->subMonthNoOverflow();
    $defaultYear = old('year', isset($payment) ? $payment->period?->year : $previous->year);
    $defaultMonth = old('month', isset($payment) ? $payment->period?->month : $previous->month);
@endphp

<x-form.group legend="Ödeme" icon="fa-solid fa-money-bill-transfer" description="Personel, dönem, tutar ve hesap" class="is-wide">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-form.select searchable name="employee_id" label="Personel" :options="$options['employees']" required autofocus data-employee-balance />
        <x-form.select searchable hide-search name="month" label="Ay" :options="$options['months']" :value="$defaultMonth" required />
        <x-form.select searchable hide-search name="year" label="Yıl" :options="$options['years']" :value="$defaultYear" required />
        <div class="lg:row-span-2">
            <div class="panel-cari-summary is-idle" data-employee-balance-status aria-live="polite">Personel seçilince maaş / prim / ödeme bakiyesi gelir</div>
        </div>
        <x-form.input name="amount" label="Tutar" type="number" step="0.01" min="0.01" required />
        <x-form.input name="occurred_on" label="Tarih" type="date" :value="old('occurred_on', isset($payment) ? $payment->occurred_on?->toDateString() : now()->toDateString())" required />
        <x-form.select searchable hide-search name="payment_method" label="Ödeme tipi" :options="$options['payment_methods']" placeholder="Seçiniz" />
        <x-form.select searchable name="bank_id" label="Hesap" :options="$options['accounts']" placeholder="Hesap seçin">
            <p class="panel-hint">Nakitte kasa, havalede banka, kartta POS veya kredi kartı hesabı zorunludur.</p>
        </x-form.select>
        <div class="sm:col-span-2 lg:col-span-3">
            <x-form.textarea name="description" label="Açıklama" rows="2" maxlength="255" />
        </div>
    </div>
</x-form.group>
