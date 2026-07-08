<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically route safe GET requests from unauthenticated users
 * to a read-replica database when configured.
 */
class ReadReplicaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethodSafe()
            && ! auth()->check()
            && config('database.connections.mysql_read') !== null
            && config('database.connections.mysql_read.host') !== config('database.connections.mysql.host')
        ) {
            DB::setDefaultConnection('mysql_read');
        }

        return $next($request);
    }
}
