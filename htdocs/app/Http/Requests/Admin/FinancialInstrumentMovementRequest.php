<?php

namespace App\Http\Requests\Admin;

use App\Enums\InstrumentMovementType;
use App\Http\Requests\Concerns\ChecksPaymentAccount;
use App\Models\Party;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinancialInstrumentMovementRequest extends FormRequest
{
    use ChecksPaymentAccount;

    /**
     * Endorsing and a return unpaid write to the cari, so they need that right too.
     */
    public function authorize(): bool
    {
        $type = InstrumentMovementType::tryFrom((string) $this->input('type'));

        return ! in_array($type, [InstrumentMovementType::Endorse, InstrumentMovementType::Bounce], true)
            || (bool) $this->user()?->can('create-cari-entry');
    }

    protected function prepareForValidation(): void
    {
        $type = InstrumentMovementType::tryFrom((string) $this->input('type'));

        $this->merge([
            'bank_id' => $type?->accountTypes() ? ($this->input('bank_id') ?: null) : null,
            'party_id' => $type === InstrumentMovementType::Endorse ? ($this->input('party_id') ?: null) : null,
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InstrumentMovementType::class)],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'bank_id' => ['nullable', 'uuid'],
            'party_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = InstrumentMovementType::tryFrom((string) $this->input('type'));

                if (! $type || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($type->accountTypes() !== []) {
                    $this->checkPaymentAccount($validator, $type->accountTypes());
                }

                if ($type === InstrumentMovementType::Endorse && ! Party::query()->where('active', true)->whereNull('system_key')->whereKey($this->input('party_id'))->exists()) {
                    $validator->errors()->add('party_id', 'Çekin verileceği cariyi seçin.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'işlem',
            'occurred_on' => 'tarih',
            'bank_id' => 'hesap',
            'party_id' => 'cari',
            'description' => 'açıklama',
        ];
    }
}
