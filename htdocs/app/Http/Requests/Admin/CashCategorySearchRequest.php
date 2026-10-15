<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashDirection;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\CashCategoryRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashCategorySearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return CashCategoryRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', Rule::enum(CashDirection::class)],
            'active' => ['nullable', Rule::in(['1', '0'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, direction?: string, active?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'direction', 'active']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
