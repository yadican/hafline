<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Repositories\Reports\CompanyBalanceReportRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyLedgerReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.company-ledger';

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
        $exporting = $this->routeIs('admin.reports.company-ledger.export');
        $active = $this->submitted || $exporting;
        $period = $this->input('period', 'month');
        $byMonth = $period === 'month';
        $byRange = $period === 'range';
        $roleValues = array_map(
            fn (PartyRole $role) => $role->value,
            CompanyBalanceReportRepository::reportRoles(),
        );
        $role = (string) $this->input('role', '');

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
            'role' => [
                Rule::requiredIf($active),
                'nullable',
                Rule::in($roleValues),
            ],
            'party' => [
                'nullable',
                'uuid',
                Rule::when(
                    $role !== '' && in_array($role, $roleValues, true),
                    Rule::exists('party_roles', 'party_id')->where('role', $role),
                    Rule::exists('parties', 'id'),
                ),
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
            'role' => 'cari türü',
            'party' => 'firma',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party.exists' => 'Seçilen firma bu cari türüne ait değil.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->submitted = $this->query->count() > 0;

        if (! $this->filled('period')) {
            $this->merge(['period' => 'month']);
        }

        if ($this->input('party') === '') {
            $this->merge(['party' => null]);
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
     * @return array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string, party: ?string}|null
     */
    public function filters(): ?array
    {
        if ((! $this->submitted && ! $this->routeIs('admin.reports.company-ledger.export'))
            || ! $this->filled('role')) {
            return null;
        }

        $filters = [
            'period' => (string) $this->validated('period'),
            'role' => (string) $this->validated('role'),
            'party' => $this->validated('party'),
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
     * @return array{period: 'month'|'range', year: ?int, month: ?int, from: ?string, to: ?string, role: ?string, party: ?string}
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
            'role' => $this->validated('role'),
            'party' => $this->validated('party'),
        ];
    }
}
