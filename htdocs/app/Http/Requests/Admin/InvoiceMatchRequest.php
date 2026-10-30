<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment already recorded for the invoice's party, matched to the invoice; without an amount
 * the largest possible part is matched.
 */
class InvoiceMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('amount', ''));

        $this->merge(['amount' => $amount === '' ? null : str_replace(',', '.', $amount)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_type' => ['required', 'string', Rule::in(array_keys(Relation::morphMap()))],
            'payment_id' => ['required', 'uuid'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_type' => 'ödeme',
            'payment_id' => 'ödeme',
            'amount' => 'tutar',
        ];
    }
}
