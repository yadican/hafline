<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ChecksPaymentAccount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeePaymentRequest extends FormRequest
{
    use ChecksPaymentAccount;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_id' => $this->paymentMethod()?->accountTypes() ? ($this->input('bank_id') ?: null) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', Rule::exists('employees', 'id')->withoutTrashed()],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Cheque, PaymentMethod::PromissoryNote])],
            'bank_id' => ['nullable', 'uuid'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'occurred_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ($types = $this->paymentMethod()?->accountTypes())) {
                    $this->checkPaymentAccount($validator, $types);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'personel',
            'amount' => 'tutar',
            'payment_method' => 'ödeme tipi',
            'bank_id' => 'hesap',
            'year' => 'yıl',
            'month' => 'ay',
            'occurred_on' => 'tarih',
            'description' => 'açıklama',
        ];
    }

    private function paymentMethod(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom((string) $this->input('payment_method'));
    }
}
