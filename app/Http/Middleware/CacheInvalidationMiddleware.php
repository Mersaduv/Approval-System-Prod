<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CacheService;

class CacheInvalidationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Clear relevant caches after certain operations
        if ($this->shouldClearCache($request)) {
            CacheService::clearWorkflowCaches();
        }

        return $response;
    }

    /**
     * Determine if cache should be cleared based on the request
     */
    private function shouldClearCache(Request $request): bool
    {
        $method = $request->method();
        $path = $request->path();

        // Clear cache for workflow-related operations
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            return str_contains($path, 'requests') ||
                   str_contains($path, 'workflow') ||
                   str_contains($path, 'delegations') ||
                   str_contains($path, 'users') ||
                   str_contains($path, 'roles') ||
                   str_contains($path, 'departments');
        }

        return false;
    }
}
