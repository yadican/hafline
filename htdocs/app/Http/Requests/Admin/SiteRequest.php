<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parties = (array) $this->input('parties', []);

        $this->merge([
            'active' => $this->boolean('active'),
            'parties' => collect(PartyRole::forSites())
                ->mapWithKeys(fn (PartyRole $role) => [$role->value => array_values(array_filter((array) ($parties[$role->value] ?? [])))])
                ->all(),
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
                Rule::unique('sites', 'name')->ignore($this->route('site'))->withoutTrashed(),
            ],
            'parties' => ['array'],
            'parties.*' => ['array'],
            'active' => ['boolean'],
            ...collect(PartyRole::forSites())
                ->mapWithKeys(fn (PartyRole $role) => [
                    'parties.'.$role->value.'.*' => ['distinct', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()],
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'şantiye adı',
            'parties.*.*' => 'firma',
            'active' => 'aktif',
        ];
    }
}
