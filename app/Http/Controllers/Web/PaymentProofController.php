<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use App\Services\Payments\ManualPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentProofController extends Controller
{
    public function store(Request $request, string $trackingCode, ManualPaymentService $paymentService): RedirectResponse
    {
        $courseRequest = CourseRequest::query()
            ->where('public_tracking_code', $trackingCode)
            ->first();

        if (! $courseRequest) {
            return redirect()->back()->with('error', __('errors.request_not_found'));
        }

        if (! $courseRequest->canSubmitPaymentProof()) {
            return redirect()->back()->with('error', __('errors.cannot_submit_proof'));
        }

        $validated = $request->validate([
            'amount_iqd' => ['required', 'integer', 'min:1'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'proof_file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:'.($this->maxProofSizeKb()),
            ],
        ]);

        try {
            $paymentService->storeProof(
                courseRequest: $courseRequest,
                amountIqd: $validated['amount_iqd'],
                senderName: $validated['sender_name'] ?? null,
                transactionReference: $validated['transaction_reference'] ?? null,
                file: $request->file('proof_file'),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->validator->errors())->withInput();
        }

        return redirect()->route('track', ['code' => $trackingCode])
            ->with('success', __('messages.payment_proof_uploaded'));
    }

    private function maxProofSizeKb(): int
    {
        $value = config('services.payment_proof_max_mb', 5);

        return (is_numeric($value) ? (int) $value : 5) * 1024;
    }
}
