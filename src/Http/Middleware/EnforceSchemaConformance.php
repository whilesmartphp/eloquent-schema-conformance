<?php

namespace Whilesmart\SchemaConformance\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\SchemaConformance\SchemaConformanceService;

/**
 * Gates the app behind a schema conformance check. When `schema.enforce` is
 * true (default), any drift between config/schema.php and the live DB returns a
 * 503 with the list of problems. The result is cached for `schema.cache_ttl`
 * seconds so it does not hit the DB on every request.
 *
 * Bypass in local dev by setting SCHEMA_CONFORMANCE_ENFORCE=false.
 */
class EnforceSchemaConformance
{
    private const CACHE_KEY = 'schema.conformance.problems';

    public function __construct(
        protected SchemaConformanceService $service,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('schema.enforce', true)) {
            return $next($request);
        }

        // Never gate the schema tooling itself, or the fix is unreachable while
        // the app is refusing to serve.
        foreach (config('schema.bypass_paths', ['schema/*']) as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $ttl = (int) config('schema.cache_ttl', 60);
        $problems = Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->service->verify());

        if (empty($problems)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Database schema is not conformant. Run `php artisan schema:conform`.',
            'problems' => $problems,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
