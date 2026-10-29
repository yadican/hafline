<?php

namespace App\Http\Requests\Admin;

use App\Enums\CariEntryKind;
use App\Enums\InstrumentDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Party;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A cheque or note entered on its own screen. The cari it is chosen under decides which cari
 * entry carries it, so the party is settled exactly as if the entry had been typed by hand.
 */
class FinancialInstrumentRequest extends FormRequest
{
    /**
     * Cari kinds a cheque or note can be entered under, in the order the form groups them.
     *
     * @return array<string, CariEntryKind>
     */
    public static function kinds(): array
    {
        return [
            PartyRole::Customer->value => CariEntryKind::Customer,
            PartyRole::Supplier->value => CariEntryKind::Supplier,
            PartyRole::Fuel->value => CariEntryKind::Fuel,
            PartyRole::Market->value => CariEntryKind::Market,
            PartyRole::VehicleOwner->value => CariEntryKind::OwnerPayment,
        ];
    }

    /**
     * Entering a cheque writes a cari entry, so it needs that right too.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create-cari-entry');
    }

    protected function prepareForValidation(): void
    {
        [$role, $party] = array_pad(explode('|', (string) $this->input('counterparty'), 2), 2, null);

        $this->merge([
            'role' => $role ?: null,
            'party_id' => $party ?: null,
            'amount' => ($amount = trim((string) $this->input('amount'))) === '' ? null : str_replace(',', '.', $amount),
            'cheque_no' => trim((string) $this->input('cheque_no')) ?: null,
            'instrument_bank' => $this->input('payment_method') === PaymentMethod::Cheque->value ? (trim((string) $this->input('instrument_bank')) ?: null) : null,
            'instrument_drawer' => trim((string) $this->input('instrument_drawer')) ?: null,
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(InstrumentDirection::class)],
            'payment_method' => ['required', Rule::in([PaymentMethod::Cheque->value, PaymentMethod::PromissoryNote->value])],
            'counterparty' => ['required', 'string'],
            'role' => ['required', Rule::in(array_keys(self::kinds()))],
            'party_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            'occurred_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:occurred_on'],
            'cheque_no' => ['nullable', 'string', 'max:20'],
            'instrument_bank' => ['nullable', 'string', 'max:100'],
            'instrument_drawer' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:15360'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $role = PartyRole::from($this->input('role'));
                $party = Party::query()->find($this->input('party_id'));

                if (! $party || ! $party->hasRole($role)) {
                    $validator->errors()->add('counterparty', 'Cari, "'.$role->label().'" rolüne sahip olmalı.');
                } elseif ($party->isSystem()) {
                    $validator->errors()->add('counterparty', 'Sistem carisine (Geçici Cari) çek / senet yazılamaz; plakaya çekle ödeme Cari hareketleri ekranından Plaka Ödeme ile girilir.');
                }

                if ($role === PartyRole::VehicleOwner && $this->input('direction') === InstrumentDirection::Received->value) {
                    $validator->errors()->add('counterparty', 'Araç sahibine yalnızca çek / senet verilir. Araç sahibinden alınan çeki Alıcı ya da Satıcı carisinden girin.');
                }
            },
        ];
    }

    /**
     * The cari entry that settles the party and carries the cheque or note.
     *
     * @return array<string, mixed>
     */
    public function cariEntry(): array
    {
        $kind = self::kinds()[$this->validated('role')];

        return [
            'kind' => $kind->value,
            'direction' => $this->validated('direction') === InstrumentDirection::Received->value ? 'credit' : 'debit',
            ...$this->safe()->only(['party_id', 'amount', 'occurred_on', 'payment_method', 'due_on', 'cheque_no', 'instrument_bank', 'instrument_drawer', 'description', 'photos']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'direction' => 'yön',
            'payment_method' => 'tür',
            'counterparty' => 'cari',
            'role' => 'cari',
            'party_id' => 'cari',
            'amount' => 'tutar',
            'occurred_on' => 'işlem tarihi',
            'due_on' => 'vade',
            'cheque_no' => 'çek / senet no',
            'instrument_bank' => 'çekin bankası',
            'instrument_drawer' => 'keşideci',
            'photos' => 'çek / senet resmi',
            'photos.*' => 'çek / senet resmi',
            'description' => 'açıklama',
        ];
    }
}
