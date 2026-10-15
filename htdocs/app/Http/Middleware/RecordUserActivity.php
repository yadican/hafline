<?php

namespace App\Http\Middleware;

use App\Support\UserActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Every form submission is stored with the posted fields, the record before
 * and after, and the request that carried them.
 */
class RecordUserActivity
{
    public function __construct(private UserActivity $activity) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
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
}
