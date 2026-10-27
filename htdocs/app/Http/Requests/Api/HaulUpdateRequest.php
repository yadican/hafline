<?php

namespace App\Http\Requests\Api;

use App\Models\Haul;
use App\Models\Vehicle;
use App\Services\Api\FieldHaulService;
use App\Support\ApiPermissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /hauls/{haul}. Only the plate and tonnage can change; the vehicle must have an owner on the haul date.
 */
class HaulUpdateRequest extends FormRequest
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
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin.');
                }
            }],
            'tonnage' => ['required', 'integer', 'between:0,1000'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $haul = $this->route('haul');

                if ($validator->errors()->has('plate') || ! $haul instanceof Haul) {
                    return;
                }

                $vehicle = Vehicle::findByPlate($this->string('plate')->value());
                $fieldHauls = app(FieldHaulService::class);
                $problem = $vehicle ? $fieldHauls->plateProblem($vehicle, $fieldHauls->haulDate($haul), $haul) : null;

                if ($problem) {
                    $validator->errors()->add('plate', $problem);
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
            'tonnage' => 'tonaj',
        ];
    }
}
