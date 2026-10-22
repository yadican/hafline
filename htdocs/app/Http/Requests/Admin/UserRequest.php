<?php

namespace App\Http\Requests\Admin;

use App\Enums\Shift;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'roles' => $this->input('roles', []),
            'site_id' => $this->filled('site_id') ? $this->input('site_id') : null,
            'shift' => $this->filled('shift') ? $this->input('shift') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $creating = $user === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'active' => ['boolean'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'shift' => ['nullable', Rule::enum(Shift::class)],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        $data = $this->safe()->only(['name', 'username', 'email', 'active', 'site_id', 'shift']);

        if ($this->filled('password')) {
            $data['password'] = $this->validated('password');
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return array_values(array_unique($this->validated('roles') ?? []));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ad soyad',
            'username' => 'kullanıcı adı',
            'email' => 'e-posta',
            'password' => 'şifre',
            'active' => 'aktif',
            'site_id' => 'şantiye',
            'shift' => 'vardiya',
            'roles' => 'roller',
            'roles.*' => 'rol',
        ];
    }
}
