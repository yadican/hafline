<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class VehicleRateRequest extends FormRequest
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
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'rate_started_on' => ['required', 'date'],
        ];
    }

    public function hourlyRate(): string
    {
        return number_format((float) $this->validated('hourly_rate'), 2, '.', '');
    }

    public function startedOn(): Carbon
    {
        return Carbon::parse($this->validated('rate_started_on'))->startOfDay();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hourly_rate' => 'saat ücreti',
            'rate_started_on' => 'geçerlilik başlangıcı',
        ];
    }
}
