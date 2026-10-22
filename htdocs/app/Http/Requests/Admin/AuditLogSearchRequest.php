<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SortsResults;
use App\Models\AuditLog;
use App\Repositories\AuditLogRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogSearchRequest extends FormRequest
{
    use SortsResults;

    public function authorize(): bool
    {
        return true;
    }

    protected function sortRepository(): string
    {
        return AuditLogRepository::class;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', 'string', Rule::in(array_keys(AuditLog::ACTION_LABELS))],
            'method' => ['nullable', 'string', Rule::in(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'ip' => ['nullable', 'string', 'max:45'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            ...$this->sortRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => 'arama',
            'action' => 'işlem',
            'method' => 'method',
            'ip' => 'IP',
            'user_id' => 'kullanıcı',
            'from' => 'başlangıç',
            'to' => 'bitiş',
        ];
    }

    /**
     * @return array{
     *     q?: string,
     *     action?: string,
     *     method?: string,
     *     ip?: string,
     *     user_id?: string,
     *     from?: string,
     *     to?: string
     * }
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['q', 'action', 'method', 'ip', 'user_id', 'from', 'to']),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
