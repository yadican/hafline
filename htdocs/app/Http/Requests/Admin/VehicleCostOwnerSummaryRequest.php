<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleCostOwnerSummaryRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.vehicle-cost.owners';

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
        $exporting = $this->routeIs('admin.reports.vehicle-cost.owners.export');
        $active = $this->submitted || $exporting;
        $period = $this->input('period', 'month');
        $byMonth = $period === 'month';
        $byRange = $period === 'range';

        return [
            'period' => [
                Rule::requiredIf($active),
                'nullable',
                Rule::in(['month', 'range']),
            ],
            'year' => [Rule::requiredIf($byMonth && $active), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => [Rule::requiredIf($byMonth && $active), 'nullable', 'integer', 'min:1', 'max:12'],
            'from' => [Rule::requiredIf($byRange && $active), 'nullable', 'date'],
            'to' => [Rule::requiredIf($byRange && $active), 'nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'period' => 'dönem',
            'year' => 'yıl',
            'month' => 'ay',
            'from' => 'başlangıç',
            'to' => 'bitiş',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->submitted = $this->query->count() > 0;

        if (! $this->filled('period')) {
            $this->merge(['period' => 'month']);
        }

        $previous = now()->subMonthNoOverflow();

        if ($this->input('period') === 'month') {
            if (! $this->filled('year') || ! $this->filled('month')) {
                $this->merge([
                    'year' => $this->filled('year') ? $this->input('year') : $previous->year,
                    'month' => $this->filled('month') ? $this->input('month') : $previous->month,
                ]);
            }

            return;
        }

        if ($this->input('period') === 'range' && (! $this->filled('from') || ! $this->filled('to'))) {
            $this->merge([
                'from' => $this->filled('from') ? $this->input('from') : $previous->copy()->startOfMonth()->toDateString(),
                'to' => $this->filled('to') ? $this->input('to') : $previous->copy()->endOfMonth()->toDateString(),
            ]);
        }
    }

    /**
     * @return array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}|null
     */
    public function filters(): ?array
    {
        if (! $this->submitted && ! $this->routeIs('admin.reports.vehicle-cost.owners.export')) {
            return null;
        }

        $filters = [
            'period' => (string) $this->validated('period'),
        ];

        if ($filters['period'] === 'month') {
            $filters['year'] = (int) $this->validated('year');
            $filters['month'] = (int) $this->validated('month');
        } else {
            $filters['from'] = (string) $this->validated('from');
            $filters['to'] = (string) $this->validated('to');
        }

        return $filters;
    }

    /**
     * @return array{period: 'month'|'range', year: ?int, month: ?int, from: ?string, to: ?string}
     */
    public function displayFilters(): array
    {
        $period = (string) ($this->validated('period') ?? 'month');

        return [
            'period' => $period,
            'year' => $period === 'month' ? (int) $this->validated('year') : null,
            'month' => $period === 'month' ? (int) $this->validated('month') : null,
            'from' => $period === 'range' ? $this->validated('from') : null,
            'to' => $period === 'range' ? $this->validated('to') : null,
        ];
    }
}
