<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('payment_types', 'name')->ignore($this->route('payment_type'))->withoutTrashed(),
            ],
            'kind' => ['required', Rule::enum(PaymentKind::class)],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ödeme tipi adı',
            'kind' => 'tür',
            'active' => 'aktif',
        ];
    }
}
