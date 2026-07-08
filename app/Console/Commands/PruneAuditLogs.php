<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=90 : Retention period in days}';

    protected $description = 'Prune audit logs older than the retention period.';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $retention = config('security.audit_retention_days', 90);
        $days = is_numeric($daysOption) ? (int) $daysOption : (is_numeric($retention) ? (int) $retention : 90);
        $cutoff = now()->subDays(max(7, $days));

        $count = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $pruned = is_int($count) ? $count : (is_numeric($count) ? (int) $count : 0);
        $this->info('Pruned '.$pruned.' audit log(s) older than '.$days.' days.');

        return self::SUCCESS;
    }
}
