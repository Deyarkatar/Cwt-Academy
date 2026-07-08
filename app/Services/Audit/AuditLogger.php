<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Jobs\AuditLogJob;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** @var list<string> */
    private static array $redactedKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'credit_card',
        'cvv',
        'ssn',
        'api_key',
        'private_key',
        'proof_file_path',
        'proof_mime',
        'proof_size_bytes',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'cf-turnstile-response',
        'g-recaptcha-response',
        'h-captcha-response',
        'student_name',
        'student_email',
        'student_phone',
        'student_city',
        'sender_name',
        'transaction_reference',
        'transaction_reference_hash',
        'rejection_reason',
        'admin_note',
        'public_rejection_note',
        'revoked_reason',
    ];

    /**
     * @param  array<mixed, mixed>  $values
     * @return array<mixed, mixed>
     */
    private static function redact(array $values): array
    {
        foreach ($values as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), self::$redactedKeys, true)) {
                $values[$k] = '[REDACTED]';

                continue;
            }
            if (is_array($v)) {
                $values[$k] = self::redact($v);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function log(
        AuditAction $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $actorId = null,
        ?string $actorType = null,
    ): ?AuditLog {
        $redactedOld = $oldValues !== null ? self::redact($oldValues) : null;
        $redactedNew = $newValues !== null ? self::redact($newValues) : null;
        $resolvedActorId = $actorId ?? (auth()->id() !== null ? (int) auth()->id() : null);
        $resolvedActorType = $actorType ?? (auth()->user() ? get_class(auth()->user()) : null);
        $ip = Request::ip();
        $ua = htmlspecialchars((string) Request::userAgent(), ENT_QUOTES, 'UTF-8');
        $requestId = (string) request()->header('X-Request-ID');

        if (config('queue.default') === 'sync') {
            return AuditLog::create([
                'actor_id' => $resolvedActorId,
                'actor_type' => $resolvedActorType,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $redactedOld,
                'new_values' => $redactedNew,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'request_id' => $requestId,
            ]);
        }

        AuditLogJob::dispatch(
            $action,
            $entityType,
            $entityId,
            $redactedOld,
            $redactedNew,
            $resolvedActorId,
            $resolvedActorType,
            $ip,
            $ua,
            $requestId,
        )->afterResponse();

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function logModelChange(
        AuditAction $action,
        object $model,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ?AuditLog {
        return self::log(
            $action,
            class_basename($model),
            $model->id ?? null,
            $oldValues,
            $newValues,
        );
    }

    public static function logLogin(?int $userId = null, bool $failed = false): ?AuditLog
    {
        $id = $userId ?? (auth()->id() !== null ? (int) auth()->id() : null);

        return self::log(
            $failed ? AuditAction::LOGIN_FAILED : AuditAction::LOGIN,
            'User',
            $id,
            null,
            null,
            $id,
        );
    }
}
