<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockItemUnit;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\StockItemRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockItemSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return StockItemRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', Rule::enum(StockItemUnit::class)],
            'tracks_stock' => ['nullable', Rule::in(['1', '0'])],
            'active' => ['nullable', Rule::in(['1', '0'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, unit?: string, tracks_stock?: string, active?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'unit', 'tracks_stock', 'active']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
