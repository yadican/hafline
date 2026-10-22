<?php

namespace App\Http\Middleware;

use App\Support\UserActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Every request is stored with the posted fields, the record before
 * and after, and the request that carried them.
 */
class RecordUserActivity
{
    public function __construct(private UserActivity $activity) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $actorId = $request->user()?->id;
        $before = $this->activity->snapshot($request);

        $response = $next($request);

        try {
            $this->activity->record($request, $response, $actorId, $before);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        $route = $request->route()?->getName();

        if (! is_string($route)) {
            return false;
        }

        return str_starts_with($route, 'admin.web-audit-logs.')
            || str_starts_with($route, 'admin.api-audit-logs.');
    }
}
