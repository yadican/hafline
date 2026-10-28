<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = AccountType::tryFrom((string) $this->input('type'));
        $bankDetails = $type?->hasBankDetails() ?? true;
        $iban = preg_replace('/\s+/', '', mb_strtoupper((string) $this->input('iban'), 'UTF-8'));

        $this->merge([
            'active' => $this->boolean('active'),
            'opening_balance' => str_replace(',', '.', trim((string) $this->input('opening_balance'))) ?: '0',
            'opened_on' => $this->input('opened_on') ?: null,
            'branch' => $bankDetails ? ($this->input('branch') ?: null) : null,
            'iban' => $bankDetails && $iban !== '' ? $iban : null,
            'representative' => $bankDetails ? ($this->input('representative') ?: null) : null,
            'account_no' => $bankDetails ? ($this->input('account_no') ?: null) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'representative' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'regex:/^TR\d{24}$/'],
            'opening_balance' => ['required', 'numeric', 'decimal:0,2', 'min:-999999999999', 'max:999999999999'],
            'opened_on' => ['nullable', 'date'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'hesap türü',
            'name' => 'hesap adı',
            'branch' => 'şube',
            'representative' => 'temsilci',
            'phone' => 'telefon',
            'account_no' => 'hesap no',
            'iban' => 'IBAN',
            'opening_balance' => 'açılış bakiyesi',
            'opened_on' => 'açılış tarihi',
            'active' => 'aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iban.regex' => 'IBAN, TR ile başlayan 26 karakter olmalı.',
        ];
    }
}
