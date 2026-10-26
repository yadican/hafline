<?php

namespace App\Http\Requests\Api;

use App\Models\FieldHaulSession;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Vehicle;
use App\Services\Api\FieldMachineWorkService;
use App\Services\MachineWorkService;
use App\Support\ApiPermissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Store (POST /machine-works) and update (PUT /machine-works/{machineWork}). The plate may be typed
 * by hand or confirmed from a scan; either way it is checked like the admin machine work form.
 */
class MachineWorkRequest extends FormRequest
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
            'hours' => $this->decimalInput('hours'),
            'hourly_rate' => $this->decimalInput('hourly_rate'),
            'operator_id' => $this->input('operator_id') ?: null,
            'operator_rate' => $this->decimalInput('operator_rate'),
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
            'operator_id' => ['nullable', 'uuid', Rule::in(array_keys(app(MachineWorkService::class)->operatorOptions($this->work())))],
            'operator_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'hours' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:24'],
            'hourly_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ];

        if ($this->work()) {
            return $rules;
        }

        return [
            ...$rules,
            'scan_id' => ['nullable', 'uuid'],
            'field_session_id' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail): void {
                $session = FieldHaulSession::query()
                    ->whereKey($value)
                    ->where('user_id', $this->user()->id)
                    ->open()
                    ->first();

                if (! $session) {
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
                if ($validator->errors()->hasAny(['operator_id', 'operator_rate']) || ! $this->input('operator_id')) {
                    return;
                }

                $operator = Party::query()->find($this->input('operator_id'));

                if ($operator && ! app(MachineWorkService::class)->isSalaried($operator) && $this->input('operator_rate') === null) {
                    $validator->errors()->add('operator_rate', 'Operatör personel değil; carisine yazılacak saat ücretini girin. Ücret yazılmasın istiyorsanız operatörü boş bırakın.');
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->has('plate')) {
                    return;
                }

                $vehicle = Vehicle::findByPlate($this->string('plate')->value());

                if (! $vehicle) {
                    return;
                }

                $fieldWorks = app(FieldMachineWorkService::class);
                $date = $fieldWorks->workDate($this->work());
                $problem = $fieldWorks->plateProblem($vehicle, $date, $this->work());

                if ($problem) {
                    $validator->errors()->add('plate', $problem);

                    return;
                }

                if ($this->input('hourly_rate') === null && ! $validator->errors()->has('hourly_rate') && app(MachineWorkService::class)->rateOn($vehicle, $date) === null) {
                    $validator->errors()->add('hourly_rate', sprintf(
                        'Bu makine için %s tarihinde saat ücreti tanımlı değil; ücreti girin.',
                        $date->format('d.m.Y'),
                    ));
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
            'operator_id' => 'operatör',
            'operator_rate' => 'operatör saat ücreti',
            'hours' => 'çalışılan saat',
            'hourly_rate' => 'saat ücreti',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operator_id.in' => 'Operatör, cari kartında "Makine operatörü" rolü olan aktif kişilerden seçilmeli.',
        ];
    }

    /**
     * Accepts "4,5" as well as "4.5".
     */
    private function decimalInput(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str_replace(',', '.', $value);
    }

    private function work(): ?MachineWork
    {
        $work = $this->route('machineWork');

        return $work instanceof MachineWork ? $work : null;
    }
}
