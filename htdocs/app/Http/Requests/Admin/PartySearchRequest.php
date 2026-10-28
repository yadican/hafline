<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Enums\PartySide;
use App\Enums\PartyType;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\PartyRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartySearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return PartyRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = PartyType::tryFrom((string) $this->route('type')) ?? PartyType::Person;
        $allowedRoles = array_map(
            fn (PartyRole $role) => $role->value,
            PartyRole::forType($type),
        );

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in($allowedRoles)],
            'side' => ['nullable', Rule::enum(PartySide::class)],
            'active' => ['nullable', Rule::in(['1', '0'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, role?: string, side?: string, active?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'role', 'side', 'active']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
