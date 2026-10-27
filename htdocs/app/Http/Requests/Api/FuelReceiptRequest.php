<?php

namespace App\Http\Requests\Api;

use App\Models\FieldHaulSession;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Vehicle;
use App\Services\Api\FieldFuelReceiptService;
use App\Services\FuelReceiptService;
use App\Support\ApiPermissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Store (POST /fuel-receipts) and update (PUT /fuel-receipts/{fuelReceipt}). The plate may be typed
 * by hand or confirmed from a scan; either way it is checked like the admin fuel receipt form.
 */
class FuelReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->checkPermissionTo(
            ApiPermissions::FIELD_HAUL_OPERATE,
            ApiPermissions::GUARD,
        ) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate' => mb_strtoupper((string) $this->input('plate'), 'UTF-8'),
            'quantity' => $this->decimalInput('quantity'),
            'unit_price' => $this->decimalInput('unit_price'),
            'km' => $this->blankToNull('km'),
            'haul_ticket_count' => $this->blankToNull('haul_ticket_count'),
            'received_by' => trim((string) $this->input('received_by')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin.');
                }
            }],
            'fuel_station_id' => ['required', 'uuid', Rule::in(array_keys(app(FuelReceiptService::class)->formOptions($this->receipt())['stations']))],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'unit_price' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:9999'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'received_by' => ['nullable', 'string', 'max:80'],
            'haul_ticket_count' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];

        if ($this->receipt()) {
            return $rules;
        }

        return [
            ...$rules,
            'scan_id' => ['nullable', 'uuid'],
            'field_session_id' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->fieldSession()) {
                    $fail('Geçerli bir saha oturumu seçin veya yeniden sabitleyin.');
                }
            }],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('plate')) {
                    return;
                }

                $vehicle = Vehicle::findByPlate($this->string('plate')->value());

                if (! $vehicle) {
                    return;
                }

                $fieldReceipts = app(FieldFuelReceiptService::class);
                $problem = $fieldReceipts->plateProblem($vehicle, $fieldReceipts->issueDate($this->receipt()));

                if ($problem) {
                    $validator->errors()->add('plate', $problem);
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['fuel_station_id', 'field_session_id'])) {
                    return;
                }

                $station = FuelStation::query()->with('sites')->find($this->input('fuel_station_id'));

                if (! $station) {
                    return;
                }

                if ($this->input('unit_price') === null && ! $validator->errors()->has('unit_price') && (float) $station->unit_price <= 0) {
                    $validator->errors()->add('unit_price', 'Bu yakıt yerinin birim fiyatı yok; fiyatı girin veya istasyon kartından tanımlayın.');
                }

                $siteId = $this->receipt()?->site_id ?? $this->fieldSession()?->site_id;
                $stationChanged = $station->id !== $this->receipt()?->fuel_station_id;

                if ($stationChanged && ! app(FieldFuelReceiptService::class)->servesSite($station, $siteId)) {
                    $validator->errors()->add('fuel_station_id', 'Bu yakıt yeri oturumdaki şantiyeye bağlı değil.');
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
            'plate' => 'plaka',
            'scan_id' => 'tarama',
            'field_session_id' => 'oturum',
            'fuel_station_id' => 'yakıt yeri',
            'quantity' => 'litre',
            'unit_price' => 'birim fiyat',
            'km' => 'kilometre',
            'received_by' => 'alan kişi',
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

    /**
     * Accepts "45,5" as well as "45.5".
     */
    private function decimalInput(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }

    private function fieldSession(): ?FieldHaulSession
    {
        $id = $this->input('field_session_id');

        if (! is_string($id) || ! str($id)->isUuid()) {
            return null;
        }

        return FieldHaulSession::query()
            ->whereKey($id)
            ->where('user_id', $this->user()->id)
            ->open()
            ->first();
    }

    private function receipt(): ?FuelReceipt
    {
        $receipt = $this->route('fuelReceipt');

        return $receipt instanceof FuelReceipt ? $receipt : null;
    }
}
