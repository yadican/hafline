<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketControlReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.ticket-control';

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
        $exporting = $this->routeIs('admin.reports.ticket-control.export');
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
            'site' => ['nullable', 'uuid', Rule::exists('sites', 'id')],
            'dump_site' => ['nullable', 'uuid', Rule::exists('dump_sites', 'id')],
            'owner' => ['nullable', 'uuid', Rule::exists('parties', 'id')],
            'plate' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && Vehicle::findByPlate((string) $value) === null) {
                        $fail('Kayıtlı bir plaka girin.');
                    }
                },
            ],
            'group' => [
                Rule::requiredIf($active),
                'nullable',
                Rule::in(['none', 'site', 'owner', 'dump_site']),
            ],
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
            'site' => 'şantiye',
            'dump_site' => 'döküm yeri',
            'owner' => 'plaka sahibi',
            'plate' => 'plaka',
            'group' => 'gruplama',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->submitted = $this->query->count() > 0;

        if (! $this->filled('period')) {
            $this->merge(['period' => 'month']);
        }

        if (! $this->filled('group')) {
            $this->merge(['group' => 'none']);
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
     * @return array{
     *     period: 'month'|'range',
     *     year?: int,
     *     month?: int,
     *     from?: string,
     *     to?: string,
     *     site?: string,
     *     dump_site?: string,
     *     owner?: string,
     *     vehicle?: string,
     *     plate?: string,
     *     group: 'none'|'site'|'owner'|'dump_site'
     * }|null
     */
    public function filters(): ?array
    {
        if (! $this->submitted && ! $this->routeIs('admin.reports.ticket-control.export')) {
            return null;
        }

        $filters = [
            'period' => (string) $this->validated('period'),
            'group' => (string) $this->validated('group'),
        ];

        if ($filters['period'] === 'month') {
            $filters['year'] = (int) $this->validated('year');
            $filters['month'] = (int) $this->validated('month');
        } else {
            $filters['from'] = (string) $this->validated('from');
            $filters['to'] = (string) $this->validated('to');
        }

        foreach (['site', 'dump_site', 'owner'] as $key) {
            if ($this->filled($key)) {
                $filters[$key] = (string) $this->validated($key);
            }
        }

        if ($this->filled('plate')) {
            $vehicle = Vehicle::findByPlate((string) $this->validated('plate'));

            if ($vehicle !== null) {
                $filters['vehicle'] = $vehicle->id;
                $filters['plate'] = $vehicle->plate;
            }
        }

        return $filters;
    }

    /**
     * @return array{
     *     period: 'month'|'range',
     *     year: ?int,
     *     month: ?int,
     *     from: ?string,
     *     to: ?string,
     *     site: ?string,
     *     dump_site: ?string,
     *     owner: ?string,
     *     plate: ?string,
     *     group: string
     * }
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
            'site' => $this->validated('site'),
            'dump_site' => $this->validated('dump_site'),
            'owner' => $this->validated('owner'),
            'plate' => $this->validated('plate'),
            'group' => (string) ($this->validated('group') ?? 'none'),
        ];
    }
}
