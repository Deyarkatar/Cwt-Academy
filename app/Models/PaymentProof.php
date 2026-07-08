<?php

namespace App\Models;

use App\Enums\PaymentProofStatus;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'course_request_id',
    'amount_iqd',
    'sender_name',
    'transaction_reference',
    'transaction_reference_hash',
    'proof_file_path',
    'proof_mime',
    'proof_size_bytes',
    'virus_scan_status',
])]
class PaymentProof extends Model
{
    /** @use HasFactory<PaymentProofFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_iqd' => 'integer',
            'proof_size_bytes' => 'integer',
            'status' => PaymentProofStatus::class,
            'reviewed_at' => 'datetime',
            'sender_name' => 'encrypted',
            'transaction_reference' => 'encrypted',
        ];
    }

    public static function hashTransactionReference(?string $reference): ?string
    {
        if ($reference === null || trim($reference) === '') {
            return null;
        }

        return hash('sha256', trim($reference));
    }

    /**
     * @return BelongsTo<CourseRequest, $this>
     */
    public function courseRequest(): BelongsTo
    {
        return $this->belongsTo(CourseRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<PaymentProof>  $query
     * @return Builder<PaymentProof>
     */
    public function scopePending($query)
    {
        return $query->where('status', PaymentProofStatus::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentProofStatus::PENDING;
    }
}
