<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashDirection;
use App\Models\CashCategory;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'site_id' => $this->input('site_id') ?: null,
            'vehicle_id' => $this->input('vehicle_id') ?: null,
            'description' => $this->input('description') ?: null,
            'cash_category_id' => $this->input('cash_category_id') ?: null,
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
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999.999', 'decimal:0,3'],
            'used_on' => ['required', 'date'],
            'site_id' => ['nullable', 'uuid', Rule::exists(Site::class, 'id')->withoutTrashed()],
            'vehicle_id' => ['nullable', 'uuid', Rule::exists(Vehicle::class, 'id')->withoutTrashed()],
            'cash_category_id' => [
                'required',
                'uuid',
                Rule::exists(CashCategory::class, 'id')->withoutTrashed(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $category = CashCategory::query()->find($value);

                    if ($category && ! in_array($category->direction, [CashDirection::Expense, CashDirection::Both], true)) {
                        $fail('Kategori gider veya ortak yönlü olmalı.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('site_id') || $this->input('vehicle_id')) {
                return;
            }

            $validator->errors()->add('site_id', 'Şantiye veya kendi aracımızdan en az biri seçilmeli.');
            $validator->errors()->add('vehicle_id', 'Şantiye veya kendi aracımızdan en az biri seçilmeli.');
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stock_item_id' => 'stok kalemi',
            'quantity' => 'miktar',
            'used_on' => 'tarih',
            'site_id' => 'şantiye',
            'vehicle_id' => 'araç',
            'cash_category_id' => 'gider kategorisi',
            'description' => 'açıklama',
        ];
    }
}
