<?php

namespace App\Http\Requests\Admin;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemporaryOwnershipRequest extends FormRequest
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
            'party_id' => ['required', 'uuid', Rule::exists(Party::class, 'id')->whereNull('system_key')->withoutTrashed()],
        ];
    }

    public function party(): Party
    {
        return Party::query()->findOrFail($this->validated('party_id'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'party_id' => 'asıl sahip',
        ];
    }
}
