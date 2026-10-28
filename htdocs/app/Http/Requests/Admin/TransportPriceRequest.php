<?php

namespace App\Http\Requests\Admin;

use App\Enums\TransportPriceKind;
use App\Models\DeliveryAddress;
use App\Models\Material;
use App\Models\Quarry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransportPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $price = trim((string) $this->input('price'));

        $this->merge([
            'price' => $price === '' ? null : str_replace(',', '.', $price),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kind = TransportPriceKind::tryFrom((string) $this->input('kind'));

        return [
            'kind' => ['required', Rule::enum(TransportPriceKind::class)],
            'quarry_id' => [Rule::requiredIf($kind !== TransportPriceKind::Sale), 'nullable', 'uuid', Rule::exists(Quarry::class, 'id')->withoutTrashed()],
            'delivery_address_id' => [Rule::requiredIf($kind !== TransportPriceKind::Quarry), 'nullable', 'uuid', Rule::exists(DeliveryAddress::class, 'id')->withoutTrashed()],
            'material_id' => [Rule::requiredIf($kind !== TransportPriceKind::Freight), 'nullable', 'uuid', Rule::exists(Material::class, 'id')->withoutTrashed()],
            'price' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:99999999'],
            'valid_from' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => 'fiyat türü',
            'quarry_id' => 'ocak / yükleme yeri',
            'delivery_address_id' => 'sevk yeri',
            'material_id' => 'malzeme',
            'price' => 'birim fiyat',
            'valid_from' => 'geçerlilik başlangıcı',
        ];
    }
}
