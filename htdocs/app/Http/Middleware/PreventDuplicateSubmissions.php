<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser keeps only the last response of a double submit, so the repeated
 * request must replay the first request's redirect and flash instead of failing.
 */
class PreventDuplicateSubmissions
{
    public const FIELD = '_submit_token';

    private const PENDING = 'pending';

    private const TTL_SECONDS = 600;

    private const WAIT_MILLISECONDS = 10_000;

    private const POLL_MILLISECONDS = 100;

    public function __construct(private Repository $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input(self::FIELD);

        if ($request->isMethodSafe() || ! is_string($token) || ! preg_match('/^[A-Za-z0-9-]{16,64}$/', $token)) {
            return $next($request);
        }

        $request->request->remove(self::FIELD);
        $key = 'submit-token:'.$request->session()->getId().':'.$token;

        if (! $this->cache->add($key, self::PENDING, self::TTL_SECONDS)) {
            $result = $this->await($key);

            if (is_array($result)) {
                return $this->replay($request, $result);
            }

            if ($result === self::PENDING || ! $this->cache->add($key, self::PENDING, self::TTL_SECONDS)) {
                return back()->with('info', 'Bu form zaten gönderildi, işlem sürüyor.');
            }
        }

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->cache->forget($key);

            throw $exception;
        }

        if ($this->succeeded($request, $response)) {
            $this->cache->put($key, [
                'url' => $response->getTargetUrl(),
                'flash' => $request->session()->only($request->session()->get('_flash.new', [])),
            ], self::TTL_SECONDS);
        } else {
            $this->cache->forget($key);
        }

        return $response;
    }

    private function await(string $key): mixed
    {
        $waited = 0;

        while (($result = $this->cache->get($key)) === self::PENDING && $waited < self::WAIT_MILLISECONDS) {
            usleep(self::POLL_MILLISECONDS * 1000);
            $waited += self::POLL_MILLISECONDS;
        }

        return $result;
    }

    /**
     * @param  array{url: string, flash: array<string, mixed>}  $result
     */
    private function replay(Request $request, array $result): RedirectResponse
    {
        foreach ($result['flash'] as $name => $value) {
            $request->session()->flash($name, $value);
        }

        return redirect()->to($result['url']);
    }

    private function succeeded(Request $request, Response $response): bool
    {
        return $response instanceof RedirectResponse
            && ! $request->session()->has('errors')
            && ! $request->session()->has('error');
    }
}
