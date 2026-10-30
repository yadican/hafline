<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesInvoicePayment;
use App\Models\Invoice;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InvoicePaymentRequest extends FormRequest
{
    use ValidatesInvoicePayment;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeInvoicePayment($this->all()));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->invoicePaymentRules();
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $invoice = $this->route('invoice');

                if ($validator->errors()->isEmpty() && $invoice instanceof Invoice) {
                    $this->checkInvoicePayment($validator, $invoice->remainingAmount());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->invoicePaymentAttributes();
    }
}
