<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\Cache\QueryCacheService;
use Illuminate\Console\Command;

class CacheWarmCommand extends Command
{
    protected $signature = 'cache:warm
                            {--tags= : Comma-separated list of cache tags to warm}';

    protected $description = 'Warm critical caches for production deployment';

    public function handle(): int
    {
        $tags = $this->option('tags');

        $this->info('['.now()->toDateTimeString().'] Starting cache warm...');

        if (! $tags || str_contains($tags, 'courses')) {
            $this->warmCourses();
        }

        if (! $tags || str_contains($tags, 'stats')) {
            $this->warmStats();
        }

        $this->info('Cache warm complete.');

        return self::SUCCESS;
    }

    private function warmCourses(): void
    {
        $this->info('Warming featured courses...');
        Course::active()
            ->with(['category', 'instructor'])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        $this->info('Warming catalog courses...');
        Course::active()
            ->with(['category', 'instructor'])
            ->orderByDesc('created_at')
            ->paginate(12);
    }

    private function warmStats(): void
    {
        $this->info('Warming dashboard statistics...');
        QueryCacheService::warmDashboardStats();
    }
}
