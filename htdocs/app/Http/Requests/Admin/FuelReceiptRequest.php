<?php

namespace App\Http\Requests\Admin;

use App\Enums\Shift;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Site;
use App\Models\Vehicle;
use App\Services\FuelReceiptService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FuelReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate' => mb_strtoupper((string) $this->input('plate'), 'UTF-8'),
            'ticket_no' => trim((string) $this->input('ticket_no')) ?: null,
            'quantity' => $this->decimalInput('quantity'),
            'unit_price' => $this->decimalInput('unit_price'),
            'purchase_price' => $this->decimalInput('purchase_price'),
            'site_id' => $this->input('site_id') ?: null,
            'shift' => $this->input('shift') ?: null,
            'km' => $this->input('km') === '' || $this->input('km') === null ? null : $this->input('km'),
            'haul_ticket_count' => $this->input('haul_ticket_count') === '' || $this->input('haul_ticket_count') === null ? null : $this->input('haul_ticket_count'),
            'received_by' => trim((string) $this->input('received_by')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_no' => ['nullable', 'string', 'max:20', $this->uniqueTicket()],
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin.');
                }
            }],
            'issued_on' => ['required', 'date'],
            'fuel_station_id' => ['required', 'uuid', Rule::in(array_keys(app(FuelReceiptService::class)->formOptions($this->receipt())['stations']))],
            'site_id' => [
                'nullable',
                'uuid',
                Rule::exists(Site::class, 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->orWhere('id', $this->receipt()?->site_id)),
            ],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'unit_price' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:9999'],
            'purchase_price' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:9999'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'received_by' => ['nullable', 'string', 'max:80'],
            'shift' => ['nullable', Rule::enum(Shift::class)],
            'haul_ticket_count' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['plate', 'issued_on', 'fuel_station_id'])) {
                    return;
                }

                $vehicle = Vehicle::findByPlate($this->string('plate')->value());
                $date = Carbon::parse($this->string('issued_on')->value());
                $station = FuelStation::query()->with('sites')->find($this->input('fuel_station_id'));

                if ($vehicle && ! app(FuelReceiptService::class)->ownerOn($vehicle, $date)) {
                    $validator->errors()->add('plate', sprintf(
                        'Bu aracın %s tarihinde kayıtlı sahibi yok. Araç kartından sahiplik geçmişini kontrol edin.',
                        $date->format('d.m.Y'),
                    ));
                }

                if ($station && $this->input('unit_price') === null && (float) $station->unit_price <= 0) {
                    $validator->errors()->add('unit_price', 'Bu yakıt yerinin satış fiyatı yok; fiyatı girin veya yakıt kaynağı kartından tanımlayın.');
                }

                if ($station && ! $station->isTank() && $this->input('purchase_price') === null && (float) $station->purchase_price <= 0) {
                    $validator->errors()->add('purchase_price', 'Bu istasyonun alış fiyatı yok; fiyatı girin veya yakıt kaynağı kartından tanımlayın.');
                }

                $siteId = $this->input('site_id');
                $linked = $station?->sites->pluck('id')->all() ?? [];

                if ($siteId && $linked !== [] && ! in_array($siteId, $linked, true) && $siteId !== $this->receipt()?->site_id) {
                    $validator->errors()->add('site_id', 'Şantiye, seçilen yakıt yerine bağlı olanlardan biri olmalı.');
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
            'ticket_no' => 'fiş no',
            'plate' => 'plaka',
            'issued_on' => 'tarih',
            'fuel_station_id' => 'yakıt yeri',
            'site_id' => 'şantiye',
            'quantity' => 'litre',
            'unit_price' => 'satış fiyatı',
            'purchase_price' => 'alış fiyatı',
            'km' => 'kilometre',
            'received_by' => 'alan kişi',
            'shift' => 'vardiya',
            'haul_ticket_count' => 'taşıma fiş adedi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fuel_station_id.in' => 'Yakıt yeri, aktif istasyonlardan seçilmeli.',
        ];
    }

    private function decimalInput(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }

    private function receipt(): ?FuelReceipt
    {
        $receipt = $this->route('fuel_receipt');

        return $receipt instanceof FuelReceipt ? $receipt : null;
    }

    private function uniqueTicket(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || $this->receipt()?->ticket_no === $value) {
                return;
            }

            if (app(FuelReceiptService::class)->ticketTaken((string) $value)) {
                $fail('Bu fiş numarası daha önce kullanılmış.');
            }
        };
    }
}
