<?php

namespace App\Http\Requests\Admin;

use App\Enums\InvoiceDirection;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\InvoiceRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceSearchRequest extends FormRequest
{
    use SortsResults;

    protected $redirectRoute = 'admin.invoices.index';

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return InvoiceRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'f' => ['nullable', 'array'],
            'f.direction' => ['nullable', Rule::enum(InvoiceDirection::class)],
            'f.party' => ['nullable', 'uuid'],
            'f.from' => ['nullable', 'date'],
            'f.to' => ['nullable', 'date'],
            'f.due_from' => ['nullable', 'date'],
            'f.due_to' => ['nullable', 'date'],
            'f.overdue' => ['nullable', Rule::in(['1'])],
            'f.draft' => ['nullable', Rule::in(['0', '1'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter(
            (array) $this->validated('f', []),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
