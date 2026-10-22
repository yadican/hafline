<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EmployeeSalaryIndexRequest extends FormRequest
{
    protected $redirectRoute = 'admin.employee-salaries.index';

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
        $active = $this->submitted;

        return [
            'year' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:1', 'max:12', $this->pastPeriodRule()],
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

    private function pastPeriodRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->submitted || ! $this->filled('year') || ! $this->filled('month')) {
                return;
            }

            $period = Carbon::create((int) $this->input('year'), (int) $this->input('month'), 1)->startOfMonth();

            if ($period->greaterThanOrEqualTo(now()->startOfMonth())) {
                $fail('İçinde bulunulan veya gelecek ay için maaş kartı oluşturulamaz.');
            }
        };
    }

    /**
     * @return array{year: int, month: int}|null
     */
    public function filters(): ?array
    {
        if (! $this->submitted) {
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
