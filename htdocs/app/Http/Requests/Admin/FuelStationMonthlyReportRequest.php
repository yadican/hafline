<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FuelStationMonthlyReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.fuel-stations-monthly';

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
        $exporting = $this->routeIs('admin.reports.fuel-stations-monthly.export');
        $active = $this->submitted || $exporting;

        return [
            'fuel_station' => [
                Rule::requiredIf($active),
                'nullable',
                'uuid',
                Rule::exists('fuel_stations', 'id'),
            ],
            'year' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => [Rule::requiredIf($active), 'nullable', 'integer', 'min:1', 'max:12'],
            'day_from' => ['nullable', 'integer', 'min:1', 'max:31'],
            'day_to' => ['nullable', 'integer', 'min:1', 'max:31', 'gte:day_from'],
            'class' => ['nullable', 'uuid', Rule::exists('vehicle_classes', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fuel_station' => 'yakıt yeri',
            'year' => 'yıl',
            'month' => 'ay',
            'day_from' => 'başlangıç günü',
            'day_to' => 'bitiş günü',
            'class' => 'araç tipi',
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
     * @return array{fuel_station: string, year: int, month: int, day_from?: int, day_to?: int, class?: string}|null
     */
    public function filters(): ?array
    {
        if ((! $this->submitted && ! $this->routeIs('admin.reports.fuel-stations-monthly.export'))
            || ! $this->filled('fuel_station')) {
            return null;
        }

        $filters = [
            'fuel_station' => (string) $this->validated('fuel_station'),
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
        ];

        if ($this->filled('day_from')) {
            $filters['day_from'] = (int) $this->validated('day_from');
        }

        if ($this->filled('day_to')) {
            $filters['day_to'] = (int) $this->validated('day_to');
        }

        if ($this->filled('class')) {
            $filters['class'] = (string) $this->validated('class');
        }

        return $filters;
    }

    /**
     * @return array{fuel_station: ?string, year: ?int, month: ?int, day_from: ?int, day_to: ?int, class: ?string}
     */
    public function displayFilters(): array
    {
        return [
            'fuel_station' => $this->validated('fuel_station'),
            'year' => (int) ($this->validated('year') ?? now()->subMonthNoOverflow()->year),
            'month' => (int) ($this->validated('month') ?? now()->subMonthNoOverflow()->month),
            'day_from' => $this->filled('day_from') ? (int) $this->validated('day_from') : null,
            'day_to' => $this->filled('day_to') ? (int) $this->validated('day_to') : null,
            'class' => $this->validated('class'),
        ];
    }
}
