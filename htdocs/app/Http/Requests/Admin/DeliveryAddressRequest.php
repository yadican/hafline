<?php

namespace App\Http\Requests\Admin;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryAddressRequest extends FormRequest
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
            'party_id' => ['required', 'uuid', Rule::exists(Party::class, 'id')->whereNull('system_key')->withoutTrashed()],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('delivery_addresses', 'name')
                    ->where('party_id', $this->input('party_id'))
                    ->ignore($this->route('delivery_address'))
                    ->withoutTrashed(),
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
            'party_id' => 'müşteri',
            'name' => 'sevk yeri adı',
            'active' => 'aktif',
        ];
    }
}
