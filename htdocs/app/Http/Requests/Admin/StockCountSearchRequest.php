<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Models\StockItem;
use App\Repositories\StockCountRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockCountSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return StockCountRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'stock_item_id' => ['nullable', 'uuid', Rule::exists(StockItem::class, 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, stock_item_id?: string, from?: string, to?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'stock_item_id', 'from', 'to']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
