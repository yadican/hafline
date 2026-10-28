<?php

namespace App\Http\Requests\Admin;

use App\Enums\MaterialUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => mb_strtoupper(trim((string) $this->input('name')), 'UTF-8'),
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('materials', 'name')->ignore($this->route('material'))->withoutTrashed()],
            'unit' => ['required', Rule::enum(MaterialUnit::class)],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'malzeme adı',
            'unit' => 'birim',
            'active' => 'aktif',
        ];
    }
}
