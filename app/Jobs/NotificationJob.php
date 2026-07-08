<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue database notification inserts to prevent blocking
 * on the notification table during high-traffic bursts.
 */
class NotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly int $recipientUserId,
        private readonly string $type,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $actionUrl = null,
    ) {}

    public function handle(): void
    {
        try {
            Notification::create([
                'recipient_user_id' => $this->recipientUserId,
                'type' => $this->type,
                'title' => $this->title,
                'body' => $this->body,
                'action_url' => $this->actionUrl,
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            Log::critical('NotificationJob failed', [
                'recipient_user_id' => $this->recipientUserId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
