<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteExitReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.site-exits';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $exporting = $this->routeIs('admin.reports.site-exits.export');

        return [
            'site' => [
                Rule::requiredIf($exporting),
                'nullable',
                'uuid',
                Rule::exists('sites', 'id'),
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
            'site' => 'şantiye',
            'year' => 'yıl',
            'month' => 'ay',
            'class' => 'plaka tipi',
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
     * @return array{site: string, year: int, month: int, class?: string}|null
     */
    public function filters(): ?array
    {
        if (! $this->filled('site')) {
            return null;
        }

        $filters = [
            'site' => (string) $this->validated('site'),
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
     * @return array{site: ?string, year: int, month: int, class: ?string}
     */
    public function displayFilters(): array
    {
        return [
            'site' => $this->validated('site'),
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
            'class' => $this->validated('class'),
        ];
    }
}
