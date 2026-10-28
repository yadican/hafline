<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use App\Enums\CariEntryKind;
use App\Enums\CashDirection;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ChecksPaymentAccount;
use App\Models\CashCategory;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\Site;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CariEntryRequest extends FormRequest
{
    use ChecksPaymentAccount;

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
            'counter_party_id' => $kind === CariEntryKind::PartyTransfer ? ($this->input('counter_party_id') ?: null) : null,
            'site_id' => $this->input('site_id') ?: null,
            'fuel_station_id' => $this->input('fuel_station_id') ?: null,
            'dump_site_id' => $this->input('dump_site_id') ?: null,
            'bank_id' => $this->accountApplies($kind) ? ($this->input('bank_id') ?: null) : null,
            'cash_category_id' => $kind?->movesMoney() === false ? null : ($this->input('cash_category_id') ?: null),
            'payment_method' => $kind?->movesMoney() === false ? null : ($this->input('payment_method') ?: null),
            'document_no' => trim((string) $this->input('document_no')) ?: null,
            'cheque_no' => trim((string) $this->input('cheque_no')) ?: null,
            'description' => trim((string) $this->input('description')) ?: null,
            'receiver_name' => $kind === CariEntryKind::VehiclePayment ? (trim((string) $this->input('receiver_name')) ?: null) : null,
            'receiver_national_id' => $kind === CariEntryKind::VehiclePayment ? (trim((string) $this->input('receiver_national_id')) ?: null) : null,
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
            'counter_party_id' => ['nullable', 'uuid'],
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
            'receiver_name' => ['nullable', 'string', 'max:120'],
            'receiver_national_id' => ['nullable', 'digits:11'],
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

                if ($kind->takesAnyParty() && ! Party::query()->whereKey($this->input('party_id'))->exists()) {
                    $validator->errors()->add('party_id', 'Cari seçin.');
                }

                if ($kind === CariEntryKind::Opening && $this->input('party_id')) {
                    $taken = OpeningBalance::query()
                        ->where('party_id', $this->input('party_id'))
                        ->when(is_string($this->route('entry')), fn ($query) => $query->whereKeyNot($this->route('entry')))
                        ->exists();

                    if ($taken) {
                        $validator->errors()->add('party_id', 'Bu carinin açılış bakiyesi zaten girilmiş; mevcut kaydı düzenleyin.');
                    }
                }

                if ($kind === CariEntryKind::PartyTransfer) {
                    if (! Party::query()->whereKey($this->input('counter_party_id'))->exists()) {
                        $validator->errors()->add('counter_party_id', 'Alacaklanacak cariyi seçin.');
                    } elseif ($this->input('counter_party_id') === $this->input('party_id')) {
                        $validator->errors()->add('counter_party_id', 'Virmanın iki tarafı farklı cari olmalı.');
                    }
                }

                if (in_array($kind, [CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable], true)) {
                    $vehicle = Vehicle::findByPlate((string) $this->input('plate'));

                    $ownership = $vehicle?->ownershipOn(Carbon::parse($this->input('occurred_on')));

                    if (! $vehicle) {
                        $validator->errors()->add('plate', 'Kayıtlı bir plaka girin.');
                    } elseif (! $ownership) {
                        $validator->errors()->add('plate', 'Bu tarihte aracın kayıtlı sahibi yok.');
                    } elseif ($kind === CariEntryKind::VehiclePayment && $ownership->party?->isTemporary()) {
                        if (! $this->input('receiver_name')) {
                            $validator->errors()->add('receiver_name', 'Plaka Geçici Cari\'de; parayı teslim alanın adını girin.');
                        }

                        if (! $this->input('receiver_national_id')) {
                            $validator->errors()->add('receiver_national_id', 'Plaka Geçici Cari\'de; teslim alanın TC kimlik numarasını girin.');
                        }
                    }
                }

                $this->checkAccount($validator, $kind);

                if ($kind === CariEntryKind::DumpPayment && ! $this->input('dump_site_id')) {
                    $validator->errors()->add('dump_site_id', 'Döküm yeri seçin.');
                }

                if (in_array($kind, [CariEntryKind::Site, CariEntryKind::SiteBill], true) && ! $this->input('site_id')) {
                    $validator->errors()->add('site_id', 'Şantiye seçin.');
                }

                if ($kind === CariEntryKind::SiteBill && $this->input('site_id') && ! Site::query()->whereKey($this->input('site_id'))->whereNotNull('party_id')->exists()) {
                    $validator->errors()->add('site_id', 'Bu şantiyenin carisi yok; önce şantiye kartından cari seçin.');
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
            'counter_party_id' => 'alacaklanacak cari',
            'plate' => 'plaka',
            'site_id' => 'şantiye',
            'fuel_station_id' => 'yakıt yeri',
            'dump_site_id' => 'döküm yeri',
            'bank_id' => 'hesap',
            'cash_category_id' => 'kasa kategorisi',
            'payment_method' => 'ödeme tipi',
            'document_no' => 'belge no',
            'description' => 'açıklama',
            'liters' => 'litre',
            'unit_price' => 'birim fiyat',
            'receiver_name' => 'teslim alan',
            'receiver_national_id' => 'teslim alanın TC kimlik no',
        ];
    }

    /**
     * A cash-box movement always names its account; a payment names one when its method moves
     * money through an account (cash, transfer, card). Cheque, note and offset leave it empty.
     */
    private function accountApplies(?CariEntryKind $kind): bool
    {
        if ($kind === CariEntryKind::Cash) {
            return true;
        }

        if ($kind === null || ! $kind->movesMoney()) {
            return false;
        }

        return (PaymentMethod::tryFrom((string) $this->input('payment_method'))?->accountTypes() ?? []) !== [];
    }

    private function checkAccount(Validator $validator, CariEntryKind $kind): void
    {
        if ($this->accountApplies($kind)) {
            $this->checkPaymentAccount($validator, $kind === CariEntryKind::Cash
                ? AccountType::cases()
                : PaymentMethod::from($this->input('payment_method'))->accountTypes());
        }
    }

    private function decimal(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }
}
