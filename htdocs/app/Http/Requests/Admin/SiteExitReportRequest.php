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
        $byMonth = $this->input('period', 'month') === 'month';

        return [
            'site' => [
                Rule::requiredIf($exporting),
                'nullable',
                'uuid',
                Rule::exists('sites', 'id'),
            ],
            'period' => ['required', Rule::in(['month', 'range'])],
            'year' => [Rule::requiredIf($byMonth), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => [Rule::requiredIf($byMonth), 'nullable', 'integer', 'min:1', 'max:12'],
            'from' => [Rule::requiredIf(! $byMonth), 'nullable', 'date'],
            'to' => [Rule::requiredIf(! $byMonth), 'nullable', 'date', 'after_or_equal:from'],
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
            'period' => 'dönem',
            'year' => 'yıl',
            'month' => 'ay',
            'from' => 'başlangıç',
            'to' => 'bitiş',
            'class' => 'plaka tipi',
        ];
    }

    protected function prepareForValidation(): void
    {
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

        if (! $this->filled('from') || ! $this->filled('to')) {
            $this->merge([
                'from' => $this->filled('from') ? $this->input('from') : $previous->copy()->startOfMonth()->toDateString(),
                'to' => $this->filled('to') ? $this->input('to') : $previous->copy()->endOfMonth()->toDateString(),
            ]);
        }
    }

    /**
     * @return array{site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, class?: string}|null
     */
    public function filters(): ?array
    {
        if (! $this->filled('site')) {
            return null;
        }

        $filters = [
            'site' => (string) $this->validated('site'),
            'period' => (string) $this->validated('period'),
        ];

        if ($filters['period'] === 'month') {
            $filters['year'] = (int) $this->validated('year');
            $filters['month'] = (int) $this->validated('month');
        } else {
            $filters['from'] = (string) $this->validated('from');
            $filters['to'] = (string) $this->validated('to');
        }

        $class = $this->validated('class');

        if ($class !== null && $class !== '') {
            $filters['class'] = (string) $class;
        }

        return $filters;
    }

    /**
     * @return array{site: ?string, period: 'month'|'range', year: ?int, month: ?int, from: ?string, to: ?string, class: ?string}
     */
    public function displayFilters(): array
    {
        $period = (string) $this->validated('period');

        return [
            'site' => $this->validated('site'),
            'period' => $period,
            'year' => $period === 'month' ? (int) $this->validated('year') : null,
            'month' => $period === 'month' ? (int) $this->validated('month') : null,
            'from' => $period === 'range' ? $this->validated('from') : null,
            'to' => $period === 'range' ? $this->validated('to') : null,
            'class' => $this->validated('class'),
        ];
    }
}
