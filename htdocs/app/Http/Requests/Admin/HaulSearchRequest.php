<?php

namespace App\Http\Requests\Admin;

use App\Enums\Shift;
use App\Http\Requests\Concerns\SortsResults;
use App\Repositories\HaulRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HaulSearchRequest extends FormRequest
{
    use SortsResults;

    /**
     * An invalid query string would otherwise redirect back to itself.
     */
    protected $redirectRoute = 'admin.hauls.index';

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return HaulRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'f' => ['nullable', 'array'],
            'f.site' => ['nullable', 'uuid'],
            'f.dump_site' => ['nullable', 'uuid'],
            'f.hauled_from' => ['nullable', 'date'],
            'f.hauled_to' => ['nullable', 'date'],
            'f.entered_from' => ['nullable', 'date'],
            'f.entered_to' => ['nullable', 'date'],
            'f.owner' => ['nullable', 'uuid'],
            'f.plate' => ['nullable', 'string', 'max:20'],
            'f.shift' => ['nullable', Rule::enum(Shift::class)],
            'f.class' => ['nullable', 'uuid'],
            'f.ticket' => ['nullable', 'string', 'max:20'],
            'f.creator' => ['nullable', 'uuid'],
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
