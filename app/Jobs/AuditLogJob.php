<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronous audit log insertion to prevent DB write pressure
 * on the hot path of every user-facing request.
 */
class AuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        private readonly AuditAction $action,
        private readonly string $entityType,
        private readonly ?int $entityId,
        private readonly ?array $oldValues,
        private readonly ?array $newValues,
        private readonly ?int $actorId,
        private readonly ?string $actorType,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly ?string $requestId,
    ) {}

    public function handle(): void
    {
        try {
            AuditLog::create([
                'actor_id' => $this->actorId,
                'actor_type' => $this->actorType,
                'action' => $this->action,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'old_values' => $this->oldValues,
                'new_values' => $this->newValues,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'request_id' => $this->requestId,
            ]);
        } catch (\Throwable $e) {
            Log::critical('AuditLogJob failed after retries', [
                'error' => $e->getMessage(),
                'action' => $this->action->value,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
            ]);

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'audit-log-'.$this->requestId.'-'.$this->action->value.'-'.($this->entityId ?? 'null');
    }

    public function uniqueFor(): int
    {
        return 60;
    }
}
