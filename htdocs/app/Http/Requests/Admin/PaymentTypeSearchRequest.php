<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentKind;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\PaymentTypeRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentTypeSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return PaymentTypeRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', Rule::in(['1', '0'])],
            'kind' => ['nullable', Rule::enum(PaymentKind::class)],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, active?: string, kind?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'active', 'kind']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
