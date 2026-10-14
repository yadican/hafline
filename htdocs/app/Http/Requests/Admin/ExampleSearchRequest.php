<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExampleShift;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\ExampleRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExampleSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return ExampleRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', Rule::in(['1', '0'])],
            'shift' => ['nullable', Rule::enum(ExampleShift::class)],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => 'arama',
            'active' => 'durum',
            'shift' => 'vardiya',
        ];
    }

    /**
     * @return array{q?: string, active?: string, shift?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'active', 'shift']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
