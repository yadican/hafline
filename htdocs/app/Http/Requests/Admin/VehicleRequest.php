<?php

namespace App\Http\Requests\Admin;

use App\Models\Party;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                $key = Vehicle::normalizePlate((string) $value);
                $current = $this->route('vehicle');

                $taken = Vehicle::query()
                    ->where('plate_key', $key)
                    ->when($current instanceof Vehicle, fn ($query) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($taken) {
                    $fail('Bu plaka başka bir araçta kayıtlı.');
                }
            }],
            'owner_party_id' => [Rule::excludeIf($this->isUpdate()), 'required', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()],
            'owned_from' => [Rule::excludeIf($this->isUpdate()), 'required', 'date', 'before_or_equal:today'],
            'vehicle_class_id' => ['required', 'uuid', Rule::exists(VehicleClass::class, 'id')->withoutTrashed()],
            'driver_name' => ['nullable', 'string', 'max:80'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plate' => 'plaka',
            'owner_party_id' => 'araç sahibi',
            'owned_from' => 'sahiplik başlangıcı',
            'vehicle_class_id' => 'araç sınıfı',
            'driver_name' => 'şoför',
            'active' => 'aktif',
        ];
    }

    /**
     * Ownership changes after creation go through transfers so the history is kept.
     */
    private function isUpdate(): bool
    {
        return $this->route('vehicle') instanceof Vehicle;
    }
}
