<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\Vehicle;
use App\Repositories\StockUsageRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockUsageSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return StockUsageRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'stock_item_id' => ['nullable', 'uuid', Rule::exists(StockItem::class, 'id')],
            'site_id' => ['nullable', 'uuid', Rule::exists(Site::class, 'id')],
            'vehicle_id' => ['nullable', 'uuid', Rule::exists(Vehicle::class, 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, stock_item_id?: string, site_id?: string, vehicle_id?: string, from?: string, to?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'stock_item_id', 'site_id', 'vehicle_id', 'from', 'to']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
