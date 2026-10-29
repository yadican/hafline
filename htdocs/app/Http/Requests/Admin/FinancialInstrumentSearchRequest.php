<?php

namespace App\Http\Requests\Admin;

use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\FinancialInstrumentRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialInstrumentSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return FinancialInstrumentRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', Rule::in([PaymentMethod::Cheque->value, PaymentMethod::PromissoryNote->value])],
            'direction' => ['nullable', Rule::enum(InstrumentDirection::class)],
            'status' => ['nullable', Rule::enum(InstrumentStatus::class)],
            'party' => ['nullable', 'uuid'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'overdue' => ['nullable', Rule::in(['1'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, payment_method?: string, direction?: string, status?: string, party?: string, due_from?: string, due_to?: string, overdue?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'payment_method', 'direction', 'status', 'party', 'due_from', 'due_to', 'overdue']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
