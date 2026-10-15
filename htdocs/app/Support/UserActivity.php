<?php

namespace App\Support;

use App\Http\Middleware\PreventDuplicateSubmissions;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserActivity
{
    /**
     * Credential fields stay out of the log. Every other posted field is kept.
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'password_confirmation',
        'current_password',
    ];

    /**
     * @var list<string>
     */
    private const FRAMEWORK = [
        '_token',
        '_method',
        PreventDuplicateSubmissions::FIELD,
    ];

    /**
     * @param  array<string, array<string, mixed>>  $before
     */
    public function record(Request $request, Response $response, ?string $actorId, array $before): void
    {
        $models = $this->models($request);
        $subject = Arr::last($models);
        $after = [];

        foreach ($models as $name => $model) {
            $after[$name] = $this->storedAttributes($model);
        }

        $route = $request->route()?->getName();

        AuditLog::query()->create([
            'user_id' => $actorId ?? $request->user()?->id,
            'action' => $this->action($route, $request->method()),
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'old_values' => $before === [] ? null : $before,
            'new_values' => $after === [] ? null : $after,
            'method' => $request->method(),
            'route' => $route,
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'status' => $response->getStatusCode(),
            'payload' => $this->payload($request),
            'context' => $this->context($request),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function snapshot(Request $request): array
    {
        $snapshot = [];

        foreach ($this->models($request) as $name => $model) {
            $snapshot[$name] = $model->attributesToArray();
        }

        return $snapshot;
    }

    private function action(?string $route, string $method): string
    {
        return match (true) {
            $route === 'admin.login.store' => 'login',
            $route === 'admin.logout' => 'logout',
            str_ends_with((string) $route, '.store') => 'created',
            str_ends_with((string) $route, '.update') => 'updated',
            str_ends_with((string) $route, '.destroy'), $method === 'DELETE' => 'deleted',
            $method === 'PUT', $method === 'PATCH' => 'updated',
            default => 'submitted',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return $this->normalize($request->except([...self::REDACTED, ...self::FRAMEWORK]));
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        $parameters = [];

        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            if ($value instanceof Model) {
                $parameters[$name] = $value->getKey();

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $parameters[$name] = $value;
            }
        }

        $messages = array_filter([
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'info' => $request->session()->get('info'),
        ], fn (mixed $message): bool => is_string($message) && $message !== '');

        $errors = $request->session()->get('errors');

        return array_filter([
            'parameters' => $parameters,
            'query' => $request->query(),
            'messages' => $messages,
            'errors' => $errors ? $errors->getMessages() : [],
        ], fn (mixed $value): bool => $value !== [] && $value !== null);
    }

    /**
     * @return array<string, Model>
     */
    private function models(Request $request): array
    {
        $models = [];

        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            if ($value instanceof Model) {
                $models[$name] = $value;
            }
        }

        return $models;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedAttributes(Model $model): ?array
    {
        $fresh = $model->newQueryWithoutScopes()->find($model->getKey());

        return $fresh?->attributesToArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize() ?: null,
                'mime' => $value->getClientMimeType(),
            ];
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, self::REDACTED, true)) {
                continue;
            }

            try {
                $normalized[$key] = $this->normalize($item);
            } catch (Throwable) {
                $normalized[$key] = null;
            }
        }

        return $normalized;
    }
}
