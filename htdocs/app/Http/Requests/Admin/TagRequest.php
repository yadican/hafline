<?php

namespace App\Http\Requests\Admin;

use App\Enums\TagTone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
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
                'max:60',
                Rule::unique('tags', 'name')->ignore($this->route('tag'))->withoutTrashed(),
            ],
            'tone' => ['required', Rule::enum(TagTone::class)],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'etiket adı',
            'tone' => 'renk',
            'active' => 'aktif',
        ];
    }
}
