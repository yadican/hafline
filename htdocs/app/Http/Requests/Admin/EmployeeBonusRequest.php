<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeBonusRequest extends FormRequest
{
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
            'employee_id' => ['required', 'uuid', Rule::exists('employees', 'id')->withoutTrashed()],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'occurred_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'personel',
            'amount' => 'prim',
            'occurred_on' => 'tarih',
            'description' => 'açıklama',
        ];
    }
}
