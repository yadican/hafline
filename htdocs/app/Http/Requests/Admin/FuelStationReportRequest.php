<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FuelStationReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.fuel-stations';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $exporting = $this->routeIs('admin.reports.fuel-stations.export');

        return [
            'fuel_station' => [
                Rule::requiredIf($exporting),
                'nullable',
                'uuid',
                Rule::exists('fuel_stations', 'id'),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
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
            'class' => 'araç tipi',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('year') || ! $this->filled('month')) {
            $previous = now()->subMonthNoOverflow();

            $this->merge([
                'year' => $this->filled('year') ? $this->input('year') : $previous->year,
                'month' => $this->filled('month') ? $this->input('month') : $previous->month,
            ]);
        }
    }

    /**
     * @return array{fuel_station: string, year: int, month: int, class?: string}|null
     */
    public function filters(): ?array
    {
        if (! $this->filled('fuel_station')) {
            return null;
        }

        $filters = [
            'fuel_station' => (string) $this->validated('fuel_station'),
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
        ];

        $class = $this->validated('class');

        if ($class !== null && $class !== '') {
            $filters['class'] = (string) $class;
        }

        return $filters;
    }

    /**
     * @return array{fuel_station: ?string, year: int, month: int, class: ?string}
     */
    public function displayFilters(): array
    {
        return [
            'fuel_station' => $this->validated('fuel_station'),
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
            'class' => $this->validated('class'),
        ];
    }
}
