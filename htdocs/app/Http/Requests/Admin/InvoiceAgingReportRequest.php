<?php

namespace App\Http\Requests\Admin;

use App\Enums\InvoiceDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceAgingReportRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.invoice-aging';

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
        $active = $this->submitted || $this->routeIs('admin.reports.invoice-aging.export');

        return [
            'direction' => [
                Rule::requiredIf($active),
                'nullable',
                Rule::in(InvoiceDirection::values()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'direction' => 'tür',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->submitted = $this->query->count() > 0;
    }

    /**
     * @return array{direction: string}|null
     */
    public function filters(): ?array
    {
        if ((! $this->submitted && ! $this->routeIs('admin.reports.invoice-aging.export'))
            || ! $this->filled('direction')) {
            return null;
        }

        return [
            'direction' => (string) $this->validated('direction'),
        ];
    }

    /**
     * @return array{direction: ?string}
     */
    public function displayFilters(): array
    {
        return [
            'direction' => $this->validated('direction'),
        ];
    }
}
