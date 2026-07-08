<?php

namespace App\Services\Payments;

use App\Enums\CourseRequestStatus;
use App\Enums\PaymentProofStatus;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualPaymentService
{
    /**
     * Store a payment proof with full race-condition protection.
     *
     * Uses `lockForUpdate()` on the CourseRequest so that concurrent
     * approval/rejection cannot change status between the eligibility
     * check and the status update.
     */
    public function storeProof(
        CourseRequest $courseRequest,
        int $amountIqd,
        ?string $senderName,
        ?string $transactionReference,
        UploadedFile $file,
    ): PaymentProof {
        return DB::transaction(function () use ($courseRequest, $amountIqd, $senderName, $transactionReference, $file) {
            $lockedRequest = CourseRequest::query()
                ->whereKey($courseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check eligibility on the locked row.
            if (! $lockedRequest->canSubmitPaymentProof()) {
                throw ValidationException::withMessages([
                    'course_request' => __('errors.cannot_submit_proof'),
                ]);
            }

            if ($amountIqd < 1 || $amountIqd > 10_000_000) {
                throw ValidationException::withMessages([
                    'amount_iqd' => __('errors.invalid_amount'),
                ]);
            }

            $maxMb = config('services.payment_proof_max_mb', 5);
            if (! is_int($maxMb)) {
                $maxMb = is_numeric($maxMb) ? (int) $maxMb : 5;
            }
            $maxBytes = $maxMb * 1024 * 1024;

            if ($file->getSize() > $maxBytes) {
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.file_too_large', ['max' => $maxMb]),
                ]);
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            $mime = $file->getMimeType();

            if (! in_array($mime, $allowedMimes, true)) {
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.invalid_file_type'),
                ]);
            }

            $this->validateMagicBytes($file, $mime);

            $this->validateFileContent($file, $mime);

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
            ];

            // PHPStan can prove the mime is in the allowed list, but keep the
            // defensive guard so the code stays safe if the lists drift.
            $extension = $extensionMap[$mime] ?? null; // @phpstan-ignore nullCoalesce.offset

            if ($extension === null) { // @phpstan-ignore identical.alwaysFalse
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.invalid_file_type'),
                ]);
            }

            $filename = 'proof_'.Str::uuid()->toString().'.'.$extension;
            $disk = self::storageDisk();
            $path = $file->storeAs('payment_proofs', $filename, $disk);

            $referenceHash = PaymentProof::hashTransactionReference($transactionReference);

            if ($referenceHash !== null) {
                $existing = PaymentProof::query()
                    ->where('transaction_reference_hash', $referenceHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw ValidationException::withMessages([
                        'transaction_reference' => __('errors.transaction_reference_used'),
                    ]);
                }
            }

            $proof = new PaymentProof([
                'course_request_id' => $lockedRequest->id,
                'amount_iqd' => $amountIqd,
                'sender_name' => $senderName,
                'transaction_reference' => $transactionReference,
                'transaction_reference_hash' => $referenceHash,
                'proof_file_path' => $path,
                'proof_mime' => $mime,
                'proof_size_bytes' => $file->getSize(),
            ]);
            $proof->status = PaymentProofStatus::PENDING->value;

            try {
                $proof->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'idx_payment_proofs_ref_hash_unique')) {
                    throw ValidationException::withMessages([
                        'transaction_reference' => __('errors.transaction_reference_used'),
                    ]);
                }

                throw $e;
            }

            $lockedRequest->status = CourseRequestStatus::PENDING_REVIEW->value;
            $lockedRequest->save();

            return $proof;
        });
    }

    public function deleteProofFile(PaymentProof $paymentProof): void
    {
        $disk = self::storageDisk();
        if ($paymentProof->proof_file_path && Storage::disk($disk)->exists($paymentProof->proof_file_path)) {
            Storage::disk($disk)->delete($paymentProof->proof_file_path);
        }
    }

    /**
     * Return the storage disk used for payment proof files.
     *
     * R2 is preferred when configured; otherwise local (private) storage.
     */
    public static function storageDisk(): string
    {
        return config('filesystems.disks.r2.bucket') ? 'r2' : 'local';
    }

    private function validateMagicBytes(UploadedFile $file, string $mime): void
    {
        $realPath = $file->getRealPath();

        if ($realPath === false || filesize($realPath) === 0) {
            return;
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'proof_file' => __('errors.invalid_file_type'),
            ]);
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        if ($bytes === false || strlen($bytes) === 0) {
            return;
        }

        $signatures = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47"],
            'image/webp' => ["\x52\x49\x46\x46"],
            'application/pdf' => ['%PDF'],
        ];

        $expected = $signatures[$mime] ?? [];

        foreach ($expected as $sig) {
            if (str_starts_with($bytes, $sig)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'proof_file' => __('errors.invalid_file_type'),
        ]);
    }

    private function validateFileContent(UploadedFile $file, string $mime): void
    {
        $realPath = $file->getRealPath();

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $imageSize = getimagesize($realPath);
            if ($imageSize === false) {
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.invalid_file_type'),
                ]);
            }
        } elseif ($mime === 'application/pdf') {
            $handle = fopen($realPath, 'rb');
            if ($handle === false) {
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.invalid_file_type'),
                ]);
            }

            $fileSize = filesize($realPath);
            if ($fileSize === false || $fileSize === 0) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.invalid_file_type'),
                ]);
            }

            $tailSize = min(1024, $fileSize);
            fseek($handle, -$tailSize, SEEK_END);
            $tail = fread($handle, $tailSize);
            fclose($handle);

            if ($tail === false || ! str_contains($tail, '%%EOF')) {
                throw ValidationException::withMessages([
                    'proof_file' => __('errors.invalid_file_type'),
                ]);
            }
        }
    }
}
