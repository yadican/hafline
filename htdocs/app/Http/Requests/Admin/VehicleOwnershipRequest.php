<?php

namespace App\Http\Requests\Admin;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class VehicleOwnershipRequest extends FormRequest
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
            'party_id' => ['required', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()],
            'started_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function party(): Party
    {
        return Party::query()->findOrFail($this->validated('party_id'));
    }

    public function startedOn(): Carbon
    {
        return Carbon::parse($this->validated('started_on'))->startOfDay();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'party_id' => 'yeni sahip',
            'started_on' => 'devir tarihi',
        ];
    }
}
