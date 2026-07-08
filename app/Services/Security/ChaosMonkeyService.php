<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChaosMonkeyService
{
    private const array SIMULATIONS = [
        'db_failure',
        'timeout',
        'restricted_file_access',
    ];

    public static function isEnabled(): bool
    {
        return app()->environment('staging') && config('security.chaos_monkey.enabled', false);
    }

    public static function triggerRandomSimulation(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $simulation = self::SIMULATIONS[array_rand(self::SIMULATIONS)];

        Log::warning('Chaos Monkey: Triggering simulation', ['simulation' => $simulation]);

        match ($simulation) {
            'db_failure' => self::simulateDbFailure(),
            'timeout' => self::simulateTimeout(),
            'restricted_file_access' => self::simulateRestrictedFileAccess(),
        };
    }

    private static function simulateDbFailure(): void
    {
        Cache::put('chaos:db_failure', true, 30);
    }

    private static function simulateTimeout(): void
    {
        sleep(5);
    }

    private static function simulateRestrictedFileAccess(): void
    {
        Cache::put('chaos:file_restriction', true, 30);
    }
}
