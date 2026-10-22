<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\EmployeePaymentRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeePaymentSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return EmployeePaymentRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'uuid', Rule::exists('employees', 'id')->withoutTrashed()],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{employee_id?: string, year?: int, month?: int}
     */
    public function filters(): array
    {
        $filters = array_filter(
            $this->safe()->only(['employee_id', 'year', 'month']),
            fn ($value) => $value !== null && $value !== '',
        );

        if (isset($filters['year'])) {
            $filters['year'] = (int) $filters['year'];
        }

        if (isset($filters['month'])) {
            $filters['month'] = (int) $filters['month'];
        }

        return $filters;
    }
}
