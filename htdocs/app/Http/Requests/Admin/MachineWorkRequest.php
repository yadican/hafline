<?php

namespace App\Http\Requests\Admin;

use App\Enums\Shift;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Site;
use App\Models\Vehicle;
use App\Services\MachineWorkService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MachineWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        return [
            'ticket_no' => ['required', 'string', 'max:20', $this->uniqueTicket()],
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin.');
                }
            }],
            'worked_on' => ['required', 'date'],
            'shift' => ['required', Rule::enum(Shift::class)],
            'site_id' => [
                'required',
                'uuid',
                Rule::exists(Site::class, 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->orWhere('id', $this->work()?->site_id)),
            ],
            'operator_id' => ['nullable', 'uuid', Rule::in(array_keys(app(MachineWorkService::class)->operatorOptions($this->work())))],
            'operator_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'hours' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:24'],
            'hourly_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * The machine must be billed by the hour and have an owner on the work date; a blank rate
     * is only accepted when a rate period covers that date.
     *
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
                if ($validator->errors()->hasAny(['plate', 'worked_on'])) {
                    return;
                }

                $vehicle = Vehicle::findByPlate($this->string('plate')->value());
                $date = Carbon::parse($this->string('worked_on')->value());
                $service = app(MachineWorkService::class);

                if (! $vehicle) {
                    return;
                }

                if (! $vehicle->isHourly() && $vehicle->id !== $this->work()?->vehicle_id) {
                    $validator->errors()->add('plate', 'Bu araç saatlik çalışan bir tipte değil. Araç tipinde "Saatlik çalışır" işaretli olmalı.');

                    return;
                }

                if (! $service->ownerOn($vehicle, $date)) {
                    $validator->errors()->add('plate', sprintf(
                        'Bu aracın %s tarihinde kayıtlı sahibi yok. Araç kartından sahiplik geçmişini kontrol edin.',
                        $date->format('d.m.Y'),
                    ));
                }

                if ($this->input('hourly_rate') === null && ! $validator->errors()->has('hourly_rate') && $service->rateOn($vehicle, $date) === null) {
                    $validator->errors()->add('hourly_rate', sprintf(
                        'Bu makine için %s tarihinde saat ücreti tanımlı değil; ücreti girin veya araç kartından ekleyin.',
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
            'ticket_no' => 'fiş no',
            'plate' => 'plaka',
            'worked_on' => 'tarih',
            'shift' => 'vardiya',
            'site_id' => 'şantiye',
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
        $work = $this->route('machine_work');

        return $work instanceof MachineWork ? $work : null;
    }

    private function uniqueTicket(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->work()?->ticket_no === $value) {
                return;
            }

            if (app(MachineWorkService::class)->ticketTaken((string) $value)) {
                $fail('Bu fiş numarası daha önce kullanılmış.');
            }
        };
    }
}
