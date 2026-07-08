<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn (array $c, string $key) => (bool) ($c['healthy'] ?? false));

        $healthToken = config('app.health_check_token');
        $bearerToken = request()->bearerToken();
        $isAdmin = (is_string($healthToken) && is_string($bearerToken) && hash_equals($healthToken, $bearerToken))
            || (auth()->check() && auth()->user()?->isAdmin());

        if (! $isAdmin) {
            return response()->json([
                'ok' => $healthy,
                'timestamp' => now()->toIso8601String(),
            ], $healthy ? 200 : 503);
        }

        return response()->json([
            'ok' => $healthy,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['healthy' => true, 'latency_ms' => $this->measure(fn () => DB::select('SELECT 1'))];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCache(): array
    {
        try {
            $key = 'health:'.now()->timestamp;
            Cache::put($key, true, 5);
            $ok = Cache::pull($key) === true;

            return ['healthy' => $ok, 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkQueue(): array
    {
        try {
            $driver = config('queue.default');
            if ($driver === 'sync') {
                return ['healthy' => true, 'driver' => $driver, 'note' => 'sync driver is acceptable for small scale'];
            }
            Queue::connection()->size() >= 0;

            return ['healthy' => true, 'driver' => $driver];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkStorage(): array
    {
        try {
            // Lightweight check: verify storage path is writable without I/O
            $path = storage_path('app');

            return ['healthy' => is_writable($path), 'disk' => 'local'];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function measure(callable $fn): int
    {
        $start = hrtime(true);
        $fn();

        return (int) round((hrtime(true) - $start) / 1e6);
    }
}
