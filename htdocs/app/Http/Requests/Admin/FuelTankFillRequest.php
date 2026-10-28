<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FuelTankFillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => $this->decimal('quantity'),
            'unit_price' => $this->decimal('unit_price'),
            'document_no' => trim((string) $this->input('document_no')) ?: null,
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'party_id' => ['required', 'uuid', Rule::exists(Party::class, 'id')->where('type', PartyRole::Fuel->onlyFor()->value)->whereNull('system_key')->withoutTrashed()],
            'filled_on' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'unit_price' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:9999'],
            'document_no' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'party_id' => 'tedarikçi',
            'filled_on' => 'dolum tarihi',
            'quantity' => 'litre',
            'unit_price' => 'alış fiyatı',
            'document_no' => 'belge no',
            'description' => 'açıklama',
        ];
    }

    private function decimal(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }
}
