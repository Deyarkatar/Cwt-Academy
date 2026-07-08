<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DigitalEntropyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $randomDelay = random_int(0, 100000);
        usleep($randomDelay);

        $response = $next($request);

        $executionTime = (microtime(true) - $startTime) * 1000;

        if (app()->environment('production')) {
            $response->headers->set('X-Entropy', bin2hex(random_bytes(8)));
        }

        return $response;
    }
}
