<?php

namespace App\Http\Requests\Admin;

use App\Enums\CariEntryKind;
use App\Enums\CashDirection;
use App\Enums\PaymentMethod;
use App\Models\CashCategory;
use App\Models\Party;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CariEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $kind = CariEntryKind::tryFrom((string) $this->input('kind'));

        $this->merge([
            'plate' => trim((string) $this->input('plate')) !== '' ? mb_strtoupper((string) $this->input('plate'), 'UTF-8') : null,
            'amount' => $this->decimal('amount'),
            'liters' => $this->decimal('liters'),
            'unit_price' => $this->decimal('unit_price'),
            'direction' => $kind && $kind->directionMode() !== 'choice' ? $kind->directionMode() : ($this->input('direction') ?: null),
            'party_id' => $this->input('party_id') ?: null,
            'site_id' => $this->input('site_id') ?: null,
            'fuel_station_id' => $this->input('fuel_station_id') ?: null,
            'dump_site_id' => $this->input('dump_site_id') ?: null,
            'bank_id' => $this->input('bank_id') ?: null,
            'cash_category_id' => $this->input('cash_category_id') ?: null,
            'payment_method' => $this->input('payment_method') ?: null,
            'document_no' => trim((string) $this->input('document_no')) ?: null,
            'cheque_no' => trim((string) $this->input('cheque_no')) ?: null,
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CariEntryKind::class)],
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            'occurred_on' => ['required', 'date'],
            'party_id' => ['nullable', 'uuid'],
            'plate' => ['nullable', 'string', 'max:20'],
            'site_id' => ['nullable', 'uuid'],
            'fuel_station_id' => ['nullable', 'uuid'],
            'dump_site_id' => ['nullable', 'uuid'],
            'bank_id' => ['nullable', 'uuid'],
            'cash_category_id' => ['nullable', 'uuid'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'document_no' => ['nullable', 'string', 'max:40'],
            'cheque_no' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'liters' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'unit_price' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:9999'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $kind = CariEntryKind::tryFrom((string) $this->input('kind'));

                if (! $kind || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $locked = $this->route('kind');

                if (is_string($locked) && $locked !== $kind->value) {
                    $validator->errors()->add('kind', 'Kaydın türü değiştirilemez.');
                }

                if ($role = $kind->partyRole()) {
                    $party = Party::query()->find($this->input('party_id'));

                    if (! $party || ! $party->hasRole($role)) {
                        $validator->errors()->add('party_id', 'Cari, "'.$role->label().'" rolüne sahip olmalı.');
                    }
                }

                if (in_array($kind, [CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable], true)) {
                    $vehicle = Vehicle::findByPlate((string) $this->input('plate'));

                    if (! $vehicle) {
                        $validator->errors()->add('plate', 'Kayıtlı bir plaka girin.');
                    } elseif (! $vehicle->ownershipOn(Carbon::parse($this->input('occurred_on')))) {
                        $validator->errors()->add('plate', 'Bu tarihte aracın kayıtlı sahibi yok.');
                    }
                }

                if ($kind === CariEntryKind::DumpPayment && ! $this->input('dump_site_id')) {
                    $validator->errors()->add('dump_site_id', 'Döküm yeri seçin.');
                }

                if ($kind === CariEntryKind::Site && ! $this->input('site_id')) {
                    $validator->errors()->add('site_id', 'Şantiye seçin.');
                }

                $category = CashCategory::query()->find($this->input('cash_category_id'));

                if ($category && $category->direction !== CashDirection::Both) {
                    $expects = $this->input('direction') === 'credit' ? CashDirection::Income : CashDirection::Expense;

                    if ($kind === CariEntryKind::Cash) {
                        $expects = $this->input('direction') === 'debit' ? CashDirection::Income : CashDirection::Expense;
                    }

                    if ($category->direction !== $expects) {
                        $validator->errors()->add('cash_category_id', 'Kategori bu hareketin yönüne uygun değil.');
                    }
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
            'kind' => 'işlem',
            'direction' => 'yön',
            'amount' => 'tutar',
            'occurred_on' => 'tarih',
            'party_id' => 'cari',
            'plate' => 'plaka',
            'site_id' => 'şantiye',
            'fuel_station_id' => 'yakıt yeri',
            'dump_site_id' => 'döküm yeri',
            'bank_id' => 'banka',
            'cash_category_id' => 'kasa kategorisi',
            'payment_method' => 'ödeme tipi',
            'document_no' => 'belge no',
            'description' => 'açıklama',
            'liters' => 'litre',
            'unit_price' => 'birim fiyat',
        ];
    }

    private function decimal(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }
}
