<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeAnnualReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.employee-annual';

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
        $active = $this->submitted || $this->routeIs('admin.reports.employee-annual.export');

        return [
            'employee_id' => [Rule::requiredIf($active), 'nullable', 'uuid', Rule::exists('employees', 'id')->withoutTrashed()],
            'year' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'personel',
            'year' => 'yıl',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->submitted = $this->query->count() > 0;

        if (! $this->filled('year')) {
            $this->merge(['year' => now()->year]);
        }
    }

    /**
     * @return array{employee_id: string, year: int}|null
     */
    public function filters(): ?array
    {
        if (! $this->submitted && ! $this->routeIs('admin.reports.employee-annual.export')) {
            return null;
        }

        return [
            'employee_id' => (string) $this->validated('employee_id'),
            'year' => (int) $this->validated('year'),
        ];
    }

    /**
     * @return array{employee_id: ?string, year: int}
     */
    public function displayFilters(): array
    {
        return [
            'employee_id' => $this->validated('employee_id'),
            'year' => (int) ($this->validated('year') ?? now()->year),
        ];
    }
}
