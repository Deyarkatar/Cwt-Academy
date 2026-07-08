<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'actor_id',
    'actor_type',
    'action',
    'entity_type',
    'entity_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
    'request_id',
])]
class AuditLog extends Model
{
    public $timestamps = true;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'entity_id' => 'integer',
            'request_id' => 'string',
        ];
    }

    /**
     * Prevent any update to audit log rows after creation.
     */
    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('AuditLog rows are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('AuditLog rows are immutable and cannot be deleted.');
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
