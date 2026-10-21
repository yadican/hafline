<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleMonthlyReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.vehicle-monthly';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $exporting = $this->routeIs('admin.reports.vehicle-monthly.export');

        return [
            'plate' => [
                Rule::requiredIf($exporting),
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && Vehicle::findByPlate((string) $value) === null) {
                        $fail('Kayıtlı bir plaka girin.');
                    }
                },
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plate' => 'plaka',
            'year' => 'yıl',
            'month' => 'ay',
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
     * @return array{vehicle: string, plate: string, year: int, month: int}|null
     */
    public function filters(): ?array
    {
        if (! $this->filled('plate')) {
            return null;
        }

        $vehicle = Vehicle::findByPlate((string) $this->validated('plate'));

        if ($vehicle === null) {
            return null;
        }

        return [
            'vehicle' => $vehicle->id,
            'plate' => $vehicle->plate,
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
        ];
    }

    /**
     * @return array{plate: ?string, year: int, month: int}
     */
    public function displayFilters(): array
    {
        return [
            'plate' => $this->validated('plate'),
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
        ];
    }
}
