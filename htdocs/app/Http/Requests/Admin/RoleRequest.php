<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\ApiPermissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
        /** @var Role|null $role */
        $role = $this->route('role');
        $guard = $role?->guard_name ?? $this->input('guard_name', 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guard))
                    ->ignore($role?->id),
            ],
            'guard_name' => [
                $role ? 'prohibited' : 'required',
                Rule::in(['web', ApiPermissions::GUARD]),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'rol adı',
            'guard_name' => 'guard',
            'permissions' => 'izinler',
            'permissions.*' => 'izin',
        ];
    }
}
