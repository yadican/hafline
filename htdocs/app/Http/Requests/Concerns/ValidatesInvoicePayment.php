<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A payment of an invoice: amount, date, method, the account its money goes through and, for a
 * cheque or note, its number and due date. The fields sit at the top level, or under a prefix such
 * as "payment." when the payment is part of a larger form.
 */
trait ValidatesInvoicePayment
{
    use ChecksPaymentAccount;

    /**
     * Comma decimals become dots; the account is kept only when the method moves money through one,
     * the number and due date only for a cheque or note.
     *
     * @param  array<string, mixed>  $input
     * @return array{amount: ?string, occurred_on: mixed, payment_method: mixed, bank_id: mixed, cheque_no: ?string, due_on: mixed, description: ?string}
     */
    protected function normalizeInvoicePayment(array $input): array
    {
        $method = PaymentMethod::tryFrom((string) ($input['payment_method'] ?? ''));
        $tracksInstrument = (bool) $method?->hasDueDate();
        $amount = trim((string) ($input['amount'] ?? ''));

        return [
            'amount' => $amount === '' ? null : str_replace(',', '.', $amount),
            'occurred_on' => ($input['occurred_on'] ?? null) ?: null,
            'payment_method' => ($input['payment_method'] ?? null) ?: null,
            'bank_id' => $method?->accountTypes() ? (($input['bank_id'] ?? null) ?: null) : null,
            'cheque_no' => $tracksInstrument ? (trim((string) ($input['cheque_no'] ?? '')) ?: null) : null,
            'due_on' => $tracksInstrument ? (($input['due_on'] ?? null) ?: null) : null,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function invoicePaymentRules(string $prefix = ''): array
    {
        return [
            $prefix.'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            $prefix.'occurred_on' => ['required', 'date'],
            $prefix.'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            $prefix.'bank_id' => ['nullable', 'uuid'],
            $prefix.'cheque_no' => ['nullable', 'string', 'max:20'],
            $prefix.'due_on' => [Rule::requiredIf(fn (): bool => (bool) $this->invoicePaymentMethod($prefix)?->hasDueDate()), 'nullable', 'date', 'after_or_equal:'.$prefix.'occurred_on'],
            $prefix.'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The account must suit the method, and the payment cannot be more than what is left of the invoice.
     */
    protected function checkInvoicePayment(Validator $validator, float $remaining, string $prefix = ''): void
    {
        if ($types = $this->invoicePaymentMethod($prefix)?->accountTypes()) {
            $this->checkPaymentAccount($validator, $types, $prefix.'bank_id');
        }

        if ($remaining <= 0) {
            $validator->errors()->add($prefix.'amount', 'Faturanın kalanı yok.');
        } elseif (round((float) $this->input($prefix.'amount'), 2) > round($remaining, 2)) {
            $validator->errors()->add($prefix.'amount', sprintf('Tutar faturanın kalanını (%s TL) aşamaz.', number_format($remaining, 2, ',', '.')));
        }
    }

    /**
     * @return array<string, string>
     */
    protected function invoicePaymentAttributes(string $prefix = ''): array
    {
        return [
            $prefix.'amount' => 'tutar',
            $prefix.'occurred_on' => 'tarih',
            $prefix.'payment_method' => 'ödeme tipi',
            $prefix.'bank_id' => 'hesap',
            $prefix.'cheque_no' => 'çek / senet no',
            $prefix.'due_on' => 'vade',
            $prefix.'description' => 'açıklama',
        ];
    }

    private function invoicePaymentMethod(string $prefix): ?PaymentMethod
    {
        return PaymentMethod::tryFrom((string) $this->input($prefix.'payment_method'));
    }
}
