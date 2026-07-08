<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Single command to run all production optimizations.
 * Use in CI/CD pipeline and post-deployment hooks.
 */
class OptimizeProductionCommand extends Command
{
    protected $signature = 'app:optimize-prod';

    protected $description = 'Run all production optimization commands';

    public function handle(): int
    {
        $steps = [
            ['config:cache', 'Caching configuration...'],
            ['route:cache', 'Caching routes...'],
            ['view:cache', 'Caching views...'],
            ['event:cache', 'Caching events...'],
            ['cache:warm', 'Warming caches...'],
        ];

        foreach ($steps as [$command, $message]) {
            $this->info($message);
            Artisan::call($command);
            $output = Artisan::output();
            if ($output) {
                $this->line(trim($output));
            }
        }

        $this->info('All production optimizations complete.');

        return self::SUCCESS;
    }
}
