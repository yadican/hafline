<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeMonthlyReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.employee-monthly';

    private bool $submitted = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $active = $this->submitted || $this->routeIs('admin.reports.employee-monthly.export');

        return [
            'year' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year' => 'yıl',
            'month' => 'ay',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->submitted = $this->query->count() > 0;
        $previous = now()->subMonthNoOverflow();

        if (! $this->filled('year') || ! $this->filled('month')) {
            $this->merge([
                'year' => $this->filled('year') ? $this->input('year') : $previous->year,
                'month' => $this->filled('month') ? $this->input('month') : $previous->month,
            ]);
        }
    }

    /**
     * @return array{year: int, month: int}|null
     */
    public function filters(): ?array
    {
        if (! $this->submitted && ! $this->routeIs('admin.reports.employee-monthly.export')) {
            return null;
        }

        return [
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
        ];
    }

    /**
     * @return array{year: int, month: int}
     */
    public function displayFilters(): array
    {
        return [
            'year' => (int) ($this->validated('year') ?? now()->subMonthNoOverflow()->year),
            'month' => (int) ($this->validated('month') ?? now()->subMonthNoOverflow()->month),
        ];
    }
}
