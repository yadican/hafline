<?php

namespace App\Http\Requests\Admin;

use App\Enums\HaulShift;
use App\Repositories\HaulRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HaulSearchRequest extends FormRequest
{
    /**
     * An invalid query string would otherwise redirect back to itself.
     */
    protected $redirectRoute = 'admin.hauls.index';

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
            'f' => ['nullable', 'array'],
            'f.site' => ['nullable', 'integer'],
            'f.dump_site' => ['nullable', 'integer'],
            'f.hauled_from' => ['nullable', 'date'],
            'f.hauled_to' => ['nullable', 'date'],
            'f.entered_from' => ['nullable', 'date'],
            'f.entered_to' => ['nullable', 'date'],
            'f.owner' => ['nullable', 'integer'],
            'f.plate' => ['nullable', 'string', 'max:20'],
            'f.shift' => ['nullable', Rule::enum(HaulShift::class)],
            'f.class' => ['nullable', 'integer'],
            'f.ticket' => ['nullable', 'string', 'max:20'],
            'f.creator' => ['nullable', 'uuid'],
            'sort' => ['nullable', Rule::in(HaulRepository::SORTS)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
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

    public function sort(): ?string
    {
        return $this->validated('sort');
    }

    public function direction(): string
    {
        return $this->validated('dir') ?? 'desc';
    }
}
