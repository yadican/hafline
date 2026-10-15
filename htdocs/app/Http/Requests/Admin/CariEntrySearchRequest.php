<?php

namespace App\Http\Requests\Admin;

use App\Enums\CariEntryKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CariEntrySearchRequest extends FormRequest
{
    protected $redirectRoute = 'admin.cari-entries.index';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(CariEntryKind::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['kind', 'from', 'to']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
