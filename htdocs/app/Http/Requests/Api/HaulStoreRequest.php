<?php

namespace App\Http\Requests\Api;

use App\Models\FieldHaulSession;
use App\Models\Vehicle;
use App\Support\ApiPermissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HaulStoreRequest extends FormRequest
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
            'scan_id' => ['required', 'uuid'],
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
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plate' => 'plaka',
            'scan_id' => 'tarama',
            'field_session_id' => 'oturum',
        ];
    }
}
