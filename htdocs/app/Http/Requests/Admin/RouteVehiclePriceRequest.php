<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RouteVehiclePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Bu plakada kayıtlı araç yok.');
                }
            }],
            'vehicle_price' => ['required', ...array_slice(HaulRouteRequest::PRICE_RULES, 1)],
        ];
    }

    public function vehicle(): Vehicle
    {
        /** @var Vehicle */
        return Vehicle::findByPlate($this->validated('vehicle_plate'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_plate' => 'plaka',
            'vehicle_price' => 'fiyat',
        ];
    }
}
