<?php

namespace App\Http\Requests\Admin;

use App\Enums\PartyRole;
use App\Enums\ShipmentKind;
use App\Enums\Withholding;
use App\Models\DeliveryAddress;
use App\Models\Material;
use App\Models\Party;
use App\Models\Quarry;
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

        $this->merge([
            'plate' => mb_strtoupper((string) $this->input('plate'), 'UTF-8'),
            'quantity' => $this->decimal('quantity'),
            'sale_price' => $this->decimal('sale_price'),
            'carrier_price' => $this->decimal('carrier_price'),
            'quarry_price' => $this->input('kind') === ShipmentKind::MaterialSale->value ? $this->decimal('quarry_price') : null,
            'vat_rate' => (string) ($this->input('vat_rate') ?? '0'),
            'withholding_rate' => (string) ($this->input('withholding_rate') ?: Withholding::None->value),
            'commission_party_id' => $this->input('commission_party_id') ?: null,
            'commission_amount' => $this->input('commission_party_id') ? $this->decimal('commission_amount') : null,
            'waybill_no' => $text('waybill_no'),
            'scale_ticket_no' => $text('scale_ticket_no'),
            'quarry_invoice_no' => $text('quarry_invoice_no'),
            'description' => $text('description'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $current = $this->route('shipment');
        $current = $current instanceof Shipment ? $current : null;
        $activeOr = fn (string $model, ?string $id) => Rule::exists($model, 'id')->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $id));

        return [
            'kind' => ['required', Rule::enum(ShipmentKind::class)],
            'shipped_on' => ['required', 'date', 'before_or_equal:today'],
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin. Tanımsız plakayı araç kartından Geçici Cari\'ye ekleyebilirsiniz.');
                }
            }],
            'quarry_id' => ['required', 'uuid', $activeOr(Quarry::class, $current?->quarry_id)],
            'material_id' => ['required', 'uuid', $activeOr(Material::class, $current?->material_id)],
            'delivery_address_id' => ['required', 'uuid', $activeOr(DeliveryAddress::class, $current?->delivery_address_id)],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999'],
            'carrier_price' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999'],
            'quarry_price' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999'],
            'vat_rate' => ['required', Rule::in(array_keys(Shipment::VAT_RATES))],
            'withholding_rate' => ['required', Rule::enum(Withholding::class)],
            'commission_party_id' => ['nullable', 'uuid'],
            'commission_amount' => ['nullable', 'required_with:commission_party_id', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999'],
            'waybill_no' => ['nullable', 'string', 'max:30'],
            'scale_ticket_no' => ['nullable', 'string', 'max:30'],
            'quarry_invoice_no' => ['nullable', 'string', 'max:30'],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $date = Carbon::parse($this->input('shipped_on'));
                $vehicle = Vehicle::findByPlate($this->string('plate')->value());

                if ($vehicle && ! $vehicle->ownershipOn($date)) {
                    $validator->errors()->add('plate', sprintf('Bu aracın %s tarihinde kayıtlı sahibi yok.', $date->format('d.m.Y')));
                }

                if ($this->input('kind') === ShipmentKind::MaterialSale->value && ! Quarry::query()->whereKey($this->input('quarry_id'))->whereNotNull('party_id')->exists()) {
                    $validator->errors()->add('quarry_id', 'Malzeme satışlı seferde ocağın carisi olmalı; ocak kartından cari seçin.');
                }

                $commission = $this->input('commission_party_id');

                if ($commission && ! Party::query()->find($commission)?->hasRole(PartyRole::Commission)) {
                    $validator->errors()->add('commission_party_id', 'Komisyoncu, "Komisyoncu" rolüne sahip bir cari olmalı.');
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
            'kind' => 'sefer türü',
            'shipped_on' => 'tarih',
            'plate' => 'plaka',
            'quarry_id' => 'ocak / yükleme yeri',
            'material_id' => 'malzeme',
            'delivery_address_id' => 'sevk yeri',
            'quantity' => 'miktar',
            'sale_price' => 'müşteri birim fiyatı',
            'carrier_price' => 'nakliyeci birim fiyatı',
            'quarry_price' => 'ocak alış fiyatı',
            'vat_rate' => 'KDV oranı',
            'withholding_rate' => 'tevkifat',
            'commission_party_id' => 'komisyoncu',
            'commission_amount' => 'komisyon tutarı',
            'waybill_no' => 'irsaliye no',
            'scale_ticket_no' => 'kantar fiş no',
            'quarry_invoice_no' => 'ocak fatura no',
            'description' => 'açıklama',
        ];
    }

    private function decimal(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }
}
