<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CourseRequests\ApproveCourseRequestAction;
use App\Enums\AuditAction;
use App\Enums\PaymentProofStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\ManualPaymentService;
use App\Support\Security\UrlHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentProof::class);

        $query = PaymentProof::query()->with(['courseRequest.course', 'reviewer']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $proofs = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $proofs,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $proof = PaymentProof::query()
            ->with(['courseRequest.course', 'reviewer'])
            ->findOrFail($id);

        $this->authorize('view', $proof);

        return response()->json([
            'ok' => true,
            'data' => $proof,
        ]);
    }

    public function download(int $id): JsonResponse|StreamedResponse
    {
        $proof = PaymentProof::query()->findOrFail($id);

        $this->authorize('download', $proof);

        AuditLogger::log(
            AuditAction::PAYMENT_PROOF_DOWNLOADED,
            'PaymentProof',
            $proof->id,
            null,
            ['file' => basename($proof->proof_file_path ?? '')],
            auth()->user()?->id,
        );

        $path = $proof->proof_file_path;
        $disk = ManualPaymentService::storageDisk();

        if (! $path || ! UrlHelper::safePaymentProofPath($path) || ! Storage::disk($disk)->exists($path)) {
            AuditLogger::log(
                AuditAction::PAYMENT_PROOF_DOWNLOADED,
                'PaymentProof',
                $proof->id,
                null,
                ['error' => 'file_not_found', 'path' => $path],
                auth()->user()?->id,
            );

            return response()->json([
                'ok' => false,
                'message' => 'File not found.',
            ], 404);
        }

        $response = Storage::disk($disk)->download($path);

        // Override Content-Type with the stored MIME to prevent MIME-sniffing attacks.
        if ($proof->proof_mime) {
            $response->headers->set('Content-Type', $proof->proof_mime);
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.basename($path).'"');

        return $response;
    }

    public function approve(Request $request, int $id, ApproveCourseRequestAction $action): JsonResponse
    {
        $proof = PaymentProof::query()->with('courseRequest.course')->findOrFail($id);

        $this->authorize('approve', $proof);

        $courseRequest = $proof->courseRequest;
        if (! $courseRequest) {
            return response()->json([
                'ok' => false,
                'message' => 'Course request not found.',
            ], 404);
        }

        try {
            $action->execute($courseRequest, $proof, auth()->user()?->id);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'PAYMENT_PROOF_APPROVED',
            'data' => $proof->fresh(),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $proof = PaymentProof::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $this->authorize('reject', $proof);

            $lockedRequest = CourseRequest::query()
                ->whereKey($proof->course_request_id)
                ->lockForUpdate()
                ->first();

            $validated = $request->validate([
                'rejection_reason' => ['required', 'string', 'max:500'],
            ]);

            if ($proof->status !== PaymentProofStatus::PENDING) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Payment proof is not pending.',
                ], 422);
            }

            if (! $lockedRequest || ! $lockedRequest->isPending()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Course request is not in a reviewable state.',
                ], 422);
            }

            $proof->status = PaymentProofStatus::REJECTED->value;
            $proof->reviewed_by = auth()->user()?->id;
            $proof->reviewed_at = now();
            $proof->rejection_reason = $validated['rejection_reason'];
            $proof->save();

            AuditLogger::log(
                AuditAction::PAYMENT_PROOF_REJECTED,
                'PaymentProof',
                $proof->id,
                ['status' => PaymentProofStatus::PENDING->value],
                ['status' => PaymentProofStatus::REJECTED->value],
                auth()->user()?->id,
            );

            return response()->json([
                'ok' => true,
                'message' => 'PAYMENT_PROOF_REJECTED',
                'data' => $proof->fresh(),
            ]);
        });
    }
}
