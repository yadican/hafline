<?php

namespace App\Http\Requests\Admin;

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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'site_ids' => array_values(array_filter((array) $this->input('site_ids', []))),
        ]);
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
                'max:255',
                Rule::unique('fuel_stations', 'name')->ignore($this->route('fuel_station'))->withoutTrashed(),
            ],
            'party_id' => ['required', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()],
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
            'name' => 'istasyon adı',
            'party_id' => 'işletmeci',
            'unit_price' => 'birim fiyat',
            'site_ids.*' => 'şantiye',
            'active' => 'aktif',
        ];
    }
}
