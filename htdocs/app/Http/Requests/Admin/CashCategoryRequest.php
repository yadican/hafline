<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashDirection;
use App\Models\CashCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'parent_id' => $this->input('parent_id') ?: null,
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
                Rule::unique('cash_categories', 'name')->ignore($this->route('cash_category'))->withoutTrashed(),
            ],
            'direction' => ['required', Rule::enum(CashDirection::class)],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('cash_categories', 'id')->whereNull('parent_id')->withoutTrashed(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $category = $this->route('cash_category');

                    if (! $category instanceof CashCategory) {
                        return;
                    }

                    if ($value === $category->id) {
                        $fail('Kategori kendi grubu olamaz.');
                    } elseif ($category->children()->exists()) {
                        $fail('Altında kategori olan bir grup başka bir gruba bağlanamaz.');
                    }
                },
            ],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'kategori adı',
            'direction' => 'yön',
            'parent_id' => 'grup',
            'active' => 'aktif',
        ];
    }
}
