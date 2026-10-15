<?php

namespace App\Http\Requests\Admin;

use App\Enums\Shift;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\FuelReceiptRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FuelReceiptSearchRequest extends FormRequest
{
    use SortsResults;

    protected $redirectRoute = 'admin.fuel-receipts.index';

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return FuelReceiptRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'f' => ['nullable', 'array'],
            'f.ticket' => ['nullable', 'string', 'max:20'],
            'f.plate' => ['nullable', 'string', 'max:20'],
            'f.site' => ['nullable', 'uuid'],
            'f.station' => ['nullable', 'uuid'],
            'f.owner' => ['nullable', 'uuid'],
            'f.shift' => ['nullable', Rule::enum(Shift::class)],
            'f.creator' => ['nullable', 'uuid'],
            'f.issued_from' => ['nullable', 'date'],
            'f.issued_to' => ['nullable', 'date'],
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
