<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashDirection;
use App\Enums\StockItemUnit;
use App\Models\CashCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tracks_stock' => $this->boolean('tracks_stock'),
            'active' => $this->boolean('active'),
            'cash_category_id' => $this->input('cash_category_id') ?: null,
            'sales_price' => $this->input('sales_price') === '' || $this->input('sales_price') === null
                ? '0'
                : $this->input('sales_price'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('stock_items', 'name')->ignore($this->route('stock_item'))->withoutTrashed(),
            ],
            'unit' => ['required', Rule::enum(StockItemUnit::class)],
            'tracks_stock' => ['boolean'],
            'cash_category_id' => [
                'nullable',
                'uuid',
                Rule::exists(CashCategory::class, 'id')->withoutTrashed(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $category = CashCategory::query()->find($value);

                    if ($category && ! in_array($category->direction, [CashDirection::Expense, CashDirection::Both], true)) {
                        $fail('Varsayılan kategori gider veya ortak yönlü olmalı.');
                    }
                },
            ],
            'sales_price' => ['required', 'numeric', 'min:0', 'max:99999999.9999', 'decimal:0,4'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'kalem adı',
            'unit' => 'birim',
            'tracks_stock' => 'stok takibi',
            'cash_category_id' => 'varsayılan gider kategorisi',
            'sales_price' => 'varsayılan satış fiyatı',
            'active' => 'aktif',
        ];
    }
}
