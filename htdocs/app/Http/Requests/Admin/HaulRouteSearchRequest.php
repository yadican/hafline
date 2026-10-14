<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\HaulRouteRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HaulRouteSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return HaulRouteRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site' => ['nullable', 'uuid'],
            'dump_site' => ['nullable', 'uuid'],
            'active' => ['nullable', Rule::in(['1', '0'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{site?: string, dump_site?: string, active?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['site', 'dump_site', 'active']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
