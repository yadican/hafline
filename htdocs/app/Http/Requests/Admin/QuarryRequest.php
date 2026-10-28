<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyType;
use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuarryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => mb_strtoupper(trim((string) $this->input('name')), 'UTF-8'),
            'party_id' => $this->input('party_id') ?: null,
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('quarries', 'name')->ignore($this->route('quarry'))->withoutTrashed()],
            'party_id' => ['nullable', 'uuid', Rule::exists(Party::class, 'id')->where('type', PartyType::Company->value)->whereNull('system_key')->withoutTrashed()],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ocak / yükleme yeri adı',
            'party_id' => 'ocak carisi',
            'active' => 'aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_id.exists' => 'Ocak carisi, silinmemiş bir şirket cari kartı olmalı.',
        ];
    }
}
