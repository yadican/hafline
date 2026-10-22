<?php

namespace App\Http\Requests\Admin;

use App\Enums\EmployeeOccupation;
use App\Enums\PartyType;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
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
        $creating = $this->employee() === null;

        return [
            'party_id' => [
                Rule::requiredIf($creating),
                Rule::excludeIf(! $creating),
                'nullable',
                'uuid',
                Rule::exists('parties', 'id')->where('type', PartyType::Person->value)->withoutTrashed(),
                Rule::unique('employees', 'party_id')->whereNull('deleted_at'),
            ],
            'started_on' => ['nullable', 'date'],
            'occupation' => ['nullable', Rule::enum(EmployeeOccupation::class)],
            'salary' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'bank_account_no' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
            'exit_on' => ['nullable', 'date'],
            'rehire_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'party_id' => 'kişi',
            'started_on' => 'işe başlama',
            'occupation' => 'görev',
            'salary' => 'maaş',
            'bank_account_no' => 'hesap no',
            'active' => 'aktif',
            'exit_on' => 'işten çıkış',
            'rehire_on' => 'yeniden giriş',
        ];
    }

    private function employee(): ?Employee
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee : null;
    }
}
