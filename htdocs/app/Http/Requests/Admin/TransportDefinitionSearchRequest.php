<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\DeliveryAddressRepository;
use App\Repositories\MaterialRepository;
use App\Repositories\QuarryRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Search on the transport definition lists (materials, quarries, delivery addresses).
 */
class TransportDefinitionSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return match (true) {
            $this->routeIs('admin.materials.*') => MaterialRepository::class,
            $this->routeIs('admin.quarries.*') => QuarryRepository::class,
            default => DeliveryAddressRepository::class,
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'party' => ['nullable', 'uuid'],
            'active' => ['nullable', Rule::in(['1', '0'])],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array{q?: string, party?: string, active?: string}
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'party', 'active']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
