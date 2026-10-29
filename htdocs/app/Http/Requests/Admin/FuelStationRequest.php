<?php

namespace App\Http\Requests\Admin;

use App\Enums\FuelProduct;
use App\Enums\FuelSourceType;
use App\Enums\PartyRole;
use App\Models\FuelStation;
use App\Models\Party;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FuelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The type is fixed once the source exists: it decides how its past receipts were posted.
     */
    protected function prepareForValidation(): void
    {
        $existing = $this->route('fuel_station');
        $type = $existing instanceof FuelStation ? $existing->type->value : ($this->input('type') ?: FuelSourceType::Station->value);
        $isTank = $type === FuelSourceType::Tank->value;

        $this->merge([
            'type' => $type,
            'product' => $this->input('product') ?: FuelProduct::Diesel->value,
            'party_id' => $isTank ? null : ($this->input('party_id') ?: null),
            'purchase_price' => $isTank ? '0' : $this->input('purchase_price'),
            'active' => $this->boolean('active'),
            'site_ids' => array_values(array_filter((array) $this->input('site_ids', []))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isStation = $this->input('type') === FuelSourceType::Station->value;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fuel_stations', 'name')->ignore($this->route('fuel_station'))->withoutTrashed(),
            ],
            'type' => ['required', Rule::enum(FuelSourceType::class)],
            'product' => ['required', Rule::enum(FuelProduct::class)],
            'party_id' => [
                Rule::requiredIf($isStation),
                'nullable',
                'uuid',
                Rule::exists(Party::class, 'id')->where('type', PartyRole::Fuel->onlyFor()->value)->withoutTrashed(),
            ],
            'purchase_price' => [
                'required',
                'numeric',
                $isStation ? 'gt:0' : 'min:0',
                'max:99999999.9999',
                'decimal:0,4',
            ],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.9999', 'decimal:0,4'],
            'site_ids' => ['array'],
            'site_ids.*' => ['distinct', 'uuid', Rule::exists(Site::class, 'id')->withoutTrashed()],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'yakıt yeri adı',
            'type' => 'tür',
            'product' => 'ürün',
            'party_id' => 'akaryakıt firması',
            'purchase_price' => 'alış fiyatı',
            'unit_price' => 'satış fiyatı',
            'site_ids.*' => 'şantiye',
            'active' => 'aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_id.exists' => 'Akaryakıt firması, silinmemiş bir şirket cari kartı olmalı.',
        ];
    }
}
