<?php

namespace App\Http\Requests\Admin;

use App\Enums\CariEntryGroup;
use App\Enums\CariEntryKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CariEntrySearchRequest extends FormRequest
{
    protected $redirectRoute = 'admin.cari-entries.index';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $viewable = array_map(
            fn (CariEntryKind $kind) => $kind->value,
            array_values(array_filter(
                CariEntryKind::cases(),
                fn (CariEntryKind $kind) => Gate::allows('view-'.$kind->group()->permissionResource()),
            )),
        );

        $viewableGroups = array_values(array_unique(array_map(
            fn (CariEntryKind $kind) => $kind->group()->value,
            array_values(array_filter(
                CariEntryKind::cases(),
                fn (CariEntryKind $kind) => Gate::allows('view-'.$kind->group()->permissionResource()),
            )),
        )));

        return [
            'group' => ['nullable', Rule::in($viewableGroups)],
            'kind' => ['nullable', Rule::in($viewable)],
            'party' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        $filters = array_filter(
            $this->safe()->only(['group', 'kind', 'party', 'from', 'to']),
            fn ($value) => $value !== null && $value !== '',
        );

        $group = CariEntryGroup::tryFrom($filters['group'] ?? '');
        $kind = CariEntryKind::tryFrom($filters['kind'] ?? '');

        if ($group !== null && $kind !== null && $kind->group() !== $group) {
            unset($filters['kind']);
            $kind = null;
        }

        // Kasa hareketlerinin ve hesaplar arası virmanın party_id'si yok; seçim gönderilse bile yok sayılır.
        if ($kind?->namesAccount()) {
            unset($filters['party']);
        }

        return $filters;
    }
}
