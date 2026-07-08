<?php

namespace App\Http\Controllers\Admin\Web;

use App\Actions\CourseRequests\ApproveCourseRequestAction;
use App\Actions\CourseRequests\RejectCourseRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveCourseRequestRequest;
use App\Http\Requests\Admin\RejectCourseRequestRequest;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CourseRequestActionController extends Controller
{
    public function approve(
        ApproveCourseRequestRequest $request,
        int $id,
        ApproveCourseRequestAction $action,
    ): RedirectResponse {
        $courseRequest = CourseRequest::query()->findOrFail($id);
        $paymentProofId = $request->validated('payment_proof_id');
        if (! is_int($paymentProofId)) {
            abort(422, 'Invalid payment proof ID.');
        }
        $paymentProof = PaymentProof::query()->findOrFail($paymentProofId);

        try {
            $action->execute($courseRequest, $paymentProof, auth()->user()?->id);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', __('errors.validation_failed'))->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', __('errors.generic'))->withInput();
        }

        return redirect()->back()->with('success', __('admin.request_approved'));
    }

    public function reject(
        RejectCourseRequestRequest $request,
        int $id,
        RejectCourseRequestAction $action,
    ): RedirectResponse {
        $courseRequest = CourseRequest::query()->findOrFail($id);

        $rejectionReason = $request->validated('rejection_reason');
        if (! is_string($rejectionReason)) {
            abort(422, 'Invalid rejection reason.');
        }

        try {
            $action->execute(
                $courseRequest,
                auth()->user()?->id,
                $rejectionReason,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', __('errors.validation_failed'))->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', __('errors.generic'))->withInput();
        }

        return redirect()->back()->with('success', __('admin.request_rejected'));
    }
}
