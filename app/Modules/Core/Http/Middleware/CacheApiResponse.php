<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache GET API responses for read-heavy endpoints.
 *
 * Usage in routes: ->middleware('cache.api:300') for 5min TTL.
 * The cache key is derived from the full URL + authenticated user.
 * Any non-GET request to the same prefix will bust related cache entries.
 *
 * Uses Redis cache tags for reliable, atomic invalidation.
 */
class CacheApiResponse
{
    public function handle(Request $request, Closure $next, int $ttl = 300): Response
    {
        // Only cache GET requests
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $cacheKey = $this->buildCacheKey($request);
        $prefix = $this->getRoutePrefix($request);
        $tags = array_filter(['api_cache', $prefix ? "api_cache:{$prefix}" : null]);

        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use ($request, $next): Response {
            return $next($request);
        });
    }

    /**
     * After a write operation, bust all cache entries for the same prefix.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('GET')) {
            return;
        }

        $prefix = $this->getRoutePrefix($request);

        if ($prefix) {
            Cache::tags(["api_cache:{$prefix}"])->flush();
        }

        // Also bust the dashboard cache since it aggregates across scopes
        if ($prefix !== 'dashboard') {
            Cache::tags(['api_cache:dashboard'])->flush();
        }
    }

    private function buildCacheKey(Request $request): string
    {
        $userId = $request->user()?->id ?? 'guest';
        $url = $request->fullUrl();

        return 'api_cache:'.md5("{$userId}:{$url}");
    }

    private function getRoutePrefix(Request $request): ?string
    {
        $path = $request->path();

        // Extract the first two segments: api/experiments → experiments
        $segments = explode('/', $path);

        return $segments[1] ?? null;
    }
}
