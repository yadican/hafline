<?php

namespace App\Http\Requests\Admin;

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
            'class_prices.*' => 'döküm ücreti',
            'active' => 'aktif',
        ];
    }
}
