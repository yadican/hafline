<?php

namespace App\Http\Requests\Admin;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DumpSiteRequest extends FormRequest
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
                'max:255',
                Rule::unique('dump_sites', 'name')->ignore($this->route('dump_site'))->withoutTrashed(),
            ],
            'party_id' => ['required', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()],
            'class_prices' => ['array'],
            'class_prices.*' => HaulRouteRequest::PRICE_RULES,
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'döküm yeri adı',
            'party_id' => 'işletmeci',
            'class_prices.*' => 'döküm ücreti',
            'active' => 'aktif',
        ];
    }
}
