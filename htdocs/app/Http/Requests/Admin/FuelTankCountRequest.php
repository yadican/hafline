<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FuelTankCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $quantity = trim((string) $this->input('quantity'));

        $this->merge([
            'quantity' => $quantity === '' ? null : str_replace(',', '.', $quantity),
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_on' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counted_on' => 'sayım tarihi',
            'quantity' => 'sayılan litre',
            'description' => 'açıklama',
        ];
    }
}
