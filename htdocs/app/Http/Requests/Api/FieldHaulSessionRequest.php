<?php

namespace App\Http\Requests\Api;

use App\Enums\Shift;
use App\Services\HaulService;
use App\Support\ApiPermissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FieldHaulSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->checkPermissionTo(
            ApiPermissions::FIELD_HAUL_OPERATE,
            ApiPermissions::GUARD,
        ) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'uuid'],
            'dump_site_id' => ['required', 'uuid'],
            'shift' => ['required', Rule::enum(Shift::class)],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['site_id', 'dump_site_id'])) {
                    return;
                }

                $route = app(HaulService::class)->findRoute(
                    $this->string('site_id')->value(),
                    $this->string('dump_site_id')->value(),
                );

                if (! $route) {
                    $validator->errors()->add('dump_site_id', 'Bu şantiye ile döküm yeri arasında aktif güzergah yok.');
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
            'site_id' => 'şantiye',
            'dump_site_id' => 'döküm yeri',
            'shift' => 'vardiya',
        ];
    }
}
