<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogLocalPerformance
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        DB::enableQueryLog();
        $startedAt = microtime(true);

        $response = $next($request);

        Log::debug('Local route performance', [
            'route' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
            'queries' => count(DB::getQueryLog()),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        DB::disableQueryLog();

        return $response;
    }
}
