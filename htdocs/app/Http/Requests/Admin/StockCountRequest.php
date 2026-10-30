<?php

namespace App\Http\Requests\Admin;

use App\Models\StockItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $quantity = trim((string) $this->input('quantity'));

        $this->merge([
            'quantity' => $quantity === '' ? null : str_replace(',', '.', $quantity),
            'description' => $this->input('description') ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stock_item_id' => [
                'required',
                'uuid',
                Rule::exists(StockItem::class, 'id')->withoutTrashed()->where(fn ($query) => $query->where('tracks_stock', true)),
            ],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999.999', 'decimal:0,3'],
            'counted_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stock_item_id' => 'stok kalemi',
            'quantity' => 'sayılan miktar',
            'counted_on' => 'sayım tarihi',
            'description' => 'açıklama',
        ];
    }
}
