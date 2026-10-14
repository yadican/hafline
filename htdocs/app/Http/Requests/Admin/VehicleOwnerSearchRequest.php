<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\VehicleOwnerRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VehicleOwnerSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return VehicleOwnerRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
