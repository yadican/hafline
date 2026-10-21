<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DumpSiteReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.dump-sites';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $exporting = $this->routeIs('admin.reports.dump-sites.export');

        return [
            'dump_site' => [
                Rule::requiredIf($exporting),
                'nullable',
                'uuid',
                Rule::exists('dump_sites', 'id'),
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
            'dump_site' => 'döküm yeri',
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
     * @return array{dump_site: string, year: int, month: int, class?: string}|null
     */
    public function filters(): ?array
    {
        if (! $this->filled('dump_site')) {
            return null;
        }

        $filters = [
            'dump_site' => (string) $this->validated('dump_site'),
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
     * @return array{dump_site: ?string, year: int, month: int, class: ?string}
     */
    public function displayFilters(): array
    {
        return [
            'dump_site' => $this->validated('dump_site'),
            'year' => (int) $this->validated('year'),
            'month' => (int) $this->validated('month'),
            'class' => $this->validated('class'),
        ];
    }
}
