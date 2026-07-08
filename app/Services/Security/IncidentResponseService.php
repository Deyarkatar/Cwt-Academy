<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IncidentResponseService
{
    private const int BLOCK_DURATION = 3600; // 1 hour

    public static function triggerTemporaryIpBan(string $ip, string $reason): void
    {
        $key = "intrusion:blocked:{$ip}";
        Cache::put($key, true, self::BLOCK_DURATION);

        Log::critical('Temporary IP ban triggered', [
            'ip' => $ip,
            'reason' => $reason,
            'duration_seconds' => self::BLOCK_DURATION,
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function logIncident(string $type, array $details): void
    {
        Log::critical('Security incident detected', [
            'type' => $type,
            'details' => $details,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
