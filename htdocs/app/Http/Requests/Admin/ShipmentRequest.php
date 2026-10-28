<?php

namespace App\Http\Requests\Admin;

use App\Enums\Withholding;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $text = fn (string $key): ?string => trim((string) $this->input($key)) ?: null;
        $kg = preg_replace('/\D/', '', (string) $this->input('net_kg'));

        $this->merge([
            'plate' => mb_strtoupper((string) $this->input('plate'), 'UTF-8'),
            'net_kg' => $kg === '' ? null : $kg,
            'carrier_price' => $this->decimal('carrier_price'),
            'sale_price' => $this->decimal('sale_price'),
            'carrier_vat_rate' => (string) ($this->input('carrier_vat_rate') ?? '0'),
            'carrier_withholding_rate' => (string) ($this->input('carrier_withholding_rate') ?: Withholding::None->value),
            'vat_rate' => (string) ($this->input('vat_rate') ?? '0'),
            'withholding_rate' => (string) ($this->input('withholding_rate') ?: Withholding::None->value),
            'commission_party_id' => $this->input('commission_party_id') ?: null,
            'commission_amount' => $this->input('commission_party_id') ? $this->decimal('commission_amount') : null,
            'loading_place' => $text('loading_place'),
            'delivery_address' => $text('delivery_address'),
            'driver_party_id' => $this->input('driver_party_id') ?: null,
            'driver_amount' => $this->decimal('driver_amount'),
            'waybill_no' => $text('waybill_no'),
            'scale_ticket_no' => $text('scale_ticket_no'),
            'carrier_note' => $text('carrier_note'),
            'customer_note' => $text('customer_note'),
            'commission_note' => $text('commission_note'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $party = fn () => Rule::exists(Party::class, 'id')->withoutTrashed();

        return [
            'party_id' => ['required', 'uuid', $party()],
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin; yeni plakayı "+ Plaka" ile ekleyin.');
                }
            }],
            'waybill_no' => ['nullable', 'string', 'max:30'],
            'carrier_price' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999'],
            'carrier_vat_rate' => ['required', Rule::in(array_keys(Shipment::VAT_RATES))],
            'carrier_withholding_rate' => ['required', Rule::enum(Withholding::class)],
            'driver_party_id' => ['nullable', 'required_with:driver_amount', 'uuid', $party()->whereNull('system_key')],
            'driver_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999'],
            'carrier_note' => ['nullable', 'string', 'max:255'],
            'shipped_on' => ['required', 'date', 'before_or_equal:today'],
            'net_kg' => ['required', 'integer', 'min:1', 'max:9999999'],
            'loading_place' => ['required', 'string', 'max:120'],
            'scale_ticket_no' => ['nullable', 'string', 'max:30'],
            'customer_party_id' => ['required', 'uuid', $party()->whereNull('system_key')],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999'],
            'vat_rate' => ['required', Rule::in(array_keys(Shipment::VAT_RATES))],
            'withholding_rate' => ['required', Rule::enum(Withholding::class)],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'commission_party_id' => ['nullable', 'uuid', $party()->whereNull('system_key')],
            'commission_amount' => ['nullable', 'required_with:commission_party_id', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999'],
            'commission_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['plate', 'party_id', 'shipped_on'])) {
                    return;
                }

                $date = Carbon::parse($this->input('shipped_on'));
                $owner = Vehicle::findByPlate($this->string('plate')->value())?->ownershipOn($date)?->party_id;

                if ($owner !== $this->input('party_id')) {
                    $validator->errors()->add('plate', sprintf('Bu plaka %s tarihinde seçilen nakliyeciye ait değil.', $date->format('d.m.Y')));
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
            'party_id' => 'nakliyeci cari',
            'plate' => 'araç plakası',
            'waybill_no' => 'irsaliye no',
            'carrier_price' => 'nakliyeci fiyatı',
            'carrier_vat_rate' => 'nakliyeci KDV oranı',
            'carrier_withholding_rate' => 'nakliyeci tevkifatı',
            'driver_party_id' => 'şoför',
            'driver_amount' => 'sefer parası',
            'carrier_note' => 'nakliye açıklaması',
            'shipped_on' => 'sefer tarihi',
            'net_kg' => 'net miktar (kg)',
            'loading_place' => 'ocak (yükleme yeri)',
            'scale_ticket_no' => 'kantar fiş no',
            'customer_party_id' => 'müşteri cari',
            'delivery_address' => 'sevkiyat adresi',
            'sale_price' => 'nakliye hizmet bedeli',
            'vat_rate' => 'KDV oranı',
            'withholding_rate' => 'tevkifat',
            'customer_note' => 'müşteri satış açıklaması',
            'commission_party_id' => 'referans cari',
            'commission_amount' => 'referans hakedişi',
            'commission_note' => 'referans açıklaması',
        ];
    }

    private function decimal(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }
}
