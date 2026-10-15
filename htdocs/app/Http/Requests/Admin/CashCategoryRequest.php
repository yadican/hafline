<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashCategoryRequest extends FormRequest
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
                'max:100',
                Rule::unique('cash_categories', 'name')->ignore($this->route('cash_category'))->withoutTrashed(),
            ],
            'direction' => ['required', Rule::enum(CashDirection::class)],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'kategori adı',
            'direction' => 'yön',
            'active' => 'aktif',
        ];
    }
}
