<?php

namespace App\Support;

use App\Http\Middleware\PreventDuplicateSubmissions;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserActivity
{
    /**
     * Credential and token fields stay out of the log. Every other posted field is kept.
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
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
            'user_id' => $actorId ?? $this->resolvedUserId($request),
            'channel' => $this->channel($request),
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
            'response' => $this->responseBody($response),
            'context' => $this->context($request, $response),
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

    private function channel(Request $request): string
    {
        return $request->is('api/*') ? 'api' : 'web';
    }

    private function resolvedUserId(Request $request): ?string
    {
        return $request->user()?->id
            ?? $request->user('api')?->id;
    }

    private function action(?string $route, string $method): string
    {
        return match (true) {
            $route === 'admin.login.store', $route === 'api.auth.login' => 'login',
            $route === 'admin.logout', $route === 'api.auth.logout' => 'logout',
            $method === 'GET', $method === 'HEAD' => 'viewed',
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
     * JSON bodies are stored (secrets masked). Redirects keep the Location.
     * HTML/binary responses only keep type and size — full page HTML would bloat the log.
     *
     * @return array<string, mixed>|null
     */
    private function responseBody(Response $response): ?array
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return [
                'type' => 'stream',
                'content_type' => $response->headers->get('Content-Type'),
            ];
        }

        $location = $response->headers->get('Location');

        if ($response->isRedirection()) {
            return array_filter([
                'type' => 'redirect',
                'location' => $location,
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return null;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        $bytes = strlen($content);
        $trimmed = ltrim($content);

        if (str_contains($contentType, 'json')
            || str_starts_with($trimmed, '{')
            || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'type' => 'json',
                    'body' => $this->normalize($decoded, maskSecrets: true),
                ];
            }
        }

        if (str_contains($contentType, 'html') || str_starts_with($trimmed, '<!')) {
            return [
                'type' => 'html',
                'bytes' => $bytes,
                'content_type' => $contentType !== '' ? $contentType : 'text/html',
            ];
        }

        if (str_contains($contentType, 'text') || str_contains($contentType, 'xml')) {
            return [
                'type' => 'text',
                'bytes' => $bytes,
                'content_type' => $contentType,
            ];
        }

        return [
            'type' => 'binary',
            'bytes' => $bytes,
            'content_type' => $contentType !== '' ? $contentType : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request, Response $response): array
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

        $messages = [];
        $errors = [];

        if ($request->hasSession()) {
            $messages = array_filter([
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
            ], fn (mixed $message): bool => is_string($message) && $message !== '');

            $bag = $request->session()->get('errors');
            $errors = $bag ? $bag->getMessages() : [];
        } elseif ($response->getStatusCode() === 422) {
            $decoded = json_decode($response->getContent(), true);
            $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];
        }

        return array_filter([
            'parameters' => $parameters,
            'query' => $request->query(),
            'messages' => $messages,
            'errors' => $errors,
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
    private function normalize(mixed $value, bool $maskSecrets = false): mixed
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
                if ($maskSecrets) {
                    $normalized[$key] = '[redacted]';
                }

                continue;
            }

            try {
                $normalized[$key] = $this->normalize($item, $maskSecrets);
            } catch (Throwable) {
                $normalized[$key] = null;
            }
        }

        return $normalized;
    }
}
