<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Models\Party;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'roles' => $this->input('roles', []),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $person = $this->partyType() === PartyType::Person;

        return [
            'type' => [Rule::requiredIf($this->party() === null), Rule::enum(PartyType::class)],
            'first_name' => [Rule::excludeIf(! $person), 'required', 'string', 'max:80'],
            'last_name' => [Rule::excludeIf(! $person), 'required', 'string', 'max:80'],
            'national_id' => [Rule::excludeIf(! $person), 'nullable', 'digits:11', $this->uniqueAmongActive('persons', 'national_id', 'Bu T.C. kimlik numarası başka bir cari kartta kayıtlı.')],
            'title' => [Rule::excludeIf($person), 'required', 'string', 'max:200'],
            'tax_office' => [Rule::excludeIf($person), 'nullable', 'string', 'max:100'],
            'tax_number' => [Rule::excludeIf($person), 'nullable', 'digits_between:10,11', $this->uniqueAmongActive('companies', 'tax_number', 'Bu vergi numarası başka bir cari kartta kayıtlı.')],
            'fax' => [Rule::excludeIf($person), 'nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'city_id' => ['nullable', 'uuid', Rule::exists('cities', 'id')->withoutTrashed()],
            'district_id' => ['nullable', 'uuid', Rule::exists('districts', 'id')->where('city_id', $this->input('city_id'))->withoutTrashed()],
            'neighborhood_id' => ['nullable', 'uuid', Rule::exists('neighborhoods', 'id')->where('district_id', $this->input('district_id'))->withoutTrashed()],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'roles' => ['array'],
            'roles.*' => [
                Rule::enum(PartyRole::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $role = PartyRole::tryFrom((string) $value);

                    if ($role !== null && ! $role->allowedFor($this->partyType())) {
                        $fail("{$role->label()} yalnızca kişi cari kartlarına verilebilir.");
                    }
                },
            ],
            'active' => ['boolean'],
        ];
    }

    public function partyType(): PartyType
    {
        return $this->party()?->type ?? PartyType::tryFrom((string) $this->input('type')) ?? PartyType::Person;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'ad',
            'last_name' => 'soyad',
            'national_id' => 'T.C. kimlik no',
            'title' => 'unvan',
            'tax_office' => 'vergi dairesi',
            'tax_number' => 'vergi no',
            'fax' => 'faks',
            'phone' => 'telefon',
            'mobile' => 'cep telefonu',
            'email' => 'e-posta',
            'city_id' => 'il',
            'district_id' => 'ilçe',
            'neighborhood_id' => 'semt',
            'address' => 'adres',
            'notes' => 'not',
            'roles' => 'roller',
            'roles.*' => 'rol',
        ];
    }

    private function party(): ?Party
    {
        $party = $this->route('party');

        return $party instanceof Party ? $party : null;
    }

    private function uniqueAmongActive(string $table, string $column, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table, $column, $message): void {
            if (blank($value)) {
                return;
            }

            $taken = DB::table($table)
                ->join('parties', 'parties.id', '=', "{$table}.party_id")
                ->whereNull('parties.deleted_at')
                ->where("{$table}.{$column}", $value)
                ->when($this->party(), fn ($query, Party $party) => $query->where('parties.id', '<>', $party->id))
                ->exists();

            if ($taken) {
                $fail($message);
            }
        };
    }
}
