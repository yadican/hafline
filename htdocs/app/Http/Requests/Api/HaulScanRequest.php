<?php

namespace App\Http\Requests\Api;

use App\Models\FieldHaulSession;
use App\Services\Api\SecureImageIngest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HaulScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-haul') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) (SecureImageIngest::MAX_BYTES / 1024)],
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
            'file' => 'fotoğraf',
            'field_session_id' => 'oturum',
        ];
    }
}
