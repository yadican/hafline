<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwner;
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
            'owner_id' => ['nullable', 'uuid', Rule::exists(VehicleOwner::class, 'id')->withoutTrashed()],
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
            'owner_id' => 'araç sahibi',
            'vehicle_class_id' => 'araç sınıfı',
            'driver_name' => 'şoför',
            'active' => 'aktif',
        ];
    }
}
