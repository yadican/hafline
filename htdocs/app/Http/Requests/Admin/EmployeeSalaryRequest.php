<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12', $this->pastPeriodRule()],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'uuid', Rule::exists('employees', 'id')->withoutTrashed()],
            'rows.*.base_salary' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'rows.*.worked_days' => ['required', 'integer', 'min:0', 'max:31'],
        ];
    }

    private function pastPeriodRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $period = Carbon::create((int) $this->input('year'), (int) $this->input('month'), 1)->startOfMonth();

            if ($period->greaterThanOrEqualTo(now()->startOfMonth())) {
                $fail('İçinde bulunulan veya gelecek ay için maaş kartı oluşturulamaz.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year' => 'yıl',
            'month' => 'ay',
            'rows' => 'satırlar',
            'rows.*.employee_id' => 'personel',
            'rows.*.base_salary' => 'maaş',
            'rows.*.worked_days' => 'gün sayısı',
        ];
    }
}
