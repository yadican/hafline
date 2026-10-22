<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\EmployeeBonusRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeBonusSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return EmployeeBonusRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'uuid', Rule::exists('employees', 'id')->withoutTrashed()],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{employee_id?: string, from?: string, to?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['employee_id', 'from', 'to']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
