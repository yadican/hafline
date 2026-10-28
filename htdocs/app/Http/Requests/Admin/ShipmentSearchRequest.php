<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\ShipmentRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShipmentSearchRequest extends FormRequest
{
    use SortsResults;

    protected $redirectRoute = 'admin.shipments.index';

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return ShipmentRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'f' => ['nullable', 'array'],
            'f.plate' => ['nullable', 'string', 'max:20'],
            'f.waybill' => ['nullable', 'string', 'max:30'],
            'f.loading' => ['nullable', 'string', 'max:120'],
            'f.customer' => ['nullable', 'uuid'],
            'f.carrier' => ['nullable', 'uuid'],
            'f.from' => ['nullable', 'date'],
            'f.to' => ['nullable', 'date'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter(
            (array) $this->validated('f', []),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
