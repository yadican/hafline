@php
    $paymentLabel = $invoice->direction->paymentLabel();
    $paymentWord = mb_strtolower($paymentLabel, 'UTF-8');
    $paymentsWord = $paymentLabel === 'Ödeme' ? 'ödemeler' : 'tahsilatlar';
    $canMatch = auth()->user()?->can('edit-invoice') ?? false;
@endphp
<div
    class="panel-form-grid panel-form-grid-after"
    id="odemeler"
    x-data="{ open: @js(old('payment_method') !== null), method: @js((string) old('payment_method', '')) }"
    x-on:invoice-payment-open.window="open = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth' }))"
>
    <x-form.group :legend="$paymentLabel === 'Ödeme' ? 'Ödemeler' : 'Tahsilatlar'" icon="fa-solid fa-hand-holding-dollar" :description="'Faturaya bağlanan '.$paymentsWord.'; her biri cari hareketi olarak yazılır'" class="is-wide">
        @if ($invoice->payments->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Ödeme tipi</th>
                        <th>Cari hareketi</th>
                        <th class="is-end">Bağlanan</th>
                        @if ($canMatch)
                            <th class="is-end"><span class="sr-only">İşlem</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $match)
                        @php($payment = $match->payment)
                        <tr>
                            <td>{{ $payment?->occurred_on?->format('d.m.Y') ?? '—' }}</td>
                            <td>{{ $payment?->payment_method instanceof \App\Enums\PaymentMethod ? $payment->payment_method->label() : '—' }}</td>
                            <td>
                                @if ($payment instanceof \App\Models\CompanyTransaction)
                                    @can('view-cari-entry')
                                        <a href="{{ route('admin.cari-entries.show', ['kind' => $payment->role->value, 'entry' => $payment]) }}">{{ $payment->description ?: $paymentLabel }}</a>
                                    @else
                                        {{ $payment->description ?: $paymentLabel }}
                                    @endcan
                                @else
                                    {{ $payment?->description ?: '—' }}
                                @endif
                            </td>
                            <td class="is-end"><x-ui.money :value="$match->amount" /></td>
                            @if ($canMatch)
                                <td class="is-end">
                                    <form method="POST" action="{{ route('admin.invoices.matches.destroy', [$invoice, $match]) }}" class="js-confirm" data-confirm-title="Bağlantı kaldırılsın mı?" data-confirm-text="{{ $paymentLabel }} silinmez; faturanın kalanı bu tutar kadar artar ve {{ $paymentWord }} boşta bekler." data-confirm-button="Kaldır">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="panel-act panel-act-del" title="Bağlantıyı kaldır">
                                            <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                                            <span class="sr-only">Bağlantıyı kaldır</span>
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="panel-hint">{{ $paymentLabel }} bağlı fatura silinemez. Bağlı {{ $paymentWord }} cari hareketlerinden silinirse ya da tutarı düşürülürse fatura yeniden açılır.</p>
        @else
            <p class="panel-hint">Henüz {{ $paymentWord }} yok.</p>
        @endif

        @if ($canMatch && $unmatchedPayments !== [])
            @include('admin.invoices.partials.show.unmatched-payments')
        @endif

        @if ($canPay)
            <div class="flex justify-end pt-3" x-show="! open">
                <x-ui.button variant="create" icon="fa-solid fa-hand-holding-dollar" x-on:click="open = true">{{ $invoice->direction->payAction() }}</x-ui.button>
            </div>
            <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}" class="pt-3" x-show="open" x-cloak>
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-form.money name="amount" label="Tutar" :value="number_format($invoice->remainingAmount(), 2, '.', '')" required />
                    <x-form.input name="occurred_on" label="Tarih" type="date" :value="today()" required />
                    <x-form.select searchable name="payment_method" label="Ödeme tipi" placeholder="Ödeme tipi seçin" :options="$paymentOptions['methods']" x-model="method" required />
                    <div x-show="! ['cheque', 'promissory_note', 'offset'].includes(method)">
                        <x-form.select searchable name="bank_id" label="Hesap" placeholder="Hesap seçin" :options="$paymentOptions['accounts']" />
                    </div>
                    <div x-show="['cheque', 'promissory_note'].includes(method)" x-cloak>
                        <x-form.input name="cheque_no" label="Çek / senet no" maxlength="20" />
                    </div>
                    <div x-show="['cheque', 'promissory_note'].includes(method)" x-cloak>
                        <x-form.input name="due_on" label="Vade" type="date" x-bind:required="['cheque', 'promissory_note'].includes(method)" />
                    </div>
                    <x-form.input name="description" label="Açıklama" maxlength="255" :placeholder="$invoice->direction->label().' faturası '.$invoice->invoice_no" />
                </div>
                <p class="panel-hint">Nakitte kasa, havalede banka, kartta POS veya kredi kartı hesabı seçilir. Çek / senet portföye girer; tahsil ve ödeme Çek / Senet ekranından yapılır.</p>
                <div class="flex justify-end gap-2 pt-3">
                    <x-ui.button x-on:click="open = false">Vazgeç</x-ui.button>
                    <x-ui.button type="submit" variant="create" icon="fa-solid fa-check">Kaydet</x-ui.button>
                </div>
            </form>
        @endif
    </x-form.group>
</div>
