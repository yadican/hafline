<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Enums\SiteBilling;
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
            'party_id' => $this->input('party_id') ?: null,
            'billing_mode' => $this->input('billing_mode') ?: SiteBilling::Progress->value,
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
            'party_id' => [
                Rule::requiredIf($this->input('billing_mode') === SiteBilling::PerTrip->value),
                'nullable',
                'uuid',
                Rule::exists(Party::class, 'id')->where('type', PartyRole::Site->onlyFor()->value)->withoutTrashed(),
            ],
            'billing_mode' => ['required', Rule::enum(SiteBilling::class)],
            'parties' => ['array'],
            'parties.*' => ['array'],
            'active' => ['boolean'],
            ...collect(PartyRole::forSites())
                ->mapWithKeys(fn (PartyRole $role) => [
                    'parties.'.$role->value.'.*' => [
                        'distinct',
                        'uuid',
                        Rule::exists(Party::class, 'id')
                            ->when($role->onlyFor(), fn ($rule, PartyType $type) => $rule->where('type', $type->value))
                            ->withoutTrashed(),
                    ],
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
            'party_id' => 'şantiye carisi',
            'billing_mode' => 'faturalama',
            'parties.*.*' => 'firma',
            'active' => 'aktif',
        ];
    }
}
