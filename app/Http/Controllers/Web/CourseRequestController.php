<?php

namespace App\Http\Controllers\Web;

use App\Actions\CourseRequests\CreateCourseRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCourseRequestWithProofRequest;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Services\Captcha\CaptchaGuard;
use App\Services\Payments\ManualPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseRequestController extends Controller
{
    /**
     * Safely extract a string value from validated form data.
     *
     * @param  array<string, mixed>  $validated
     */
    private function stringFromValidated(array $validated, string $key, string $default = ''): string
    {
        $value = $validated[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function store(
        StoreCourseRequestWithProofRequest $request,
        CreateCourseRequestAction $action,
        ManualPaymentService $paymentService,
    ): RedirectResponse {
        /** @var Course $course */
        $course = Course::query()->active()->findOrFail($request->validated('course_id'));
        $file = $request->file('payment_proof');

        $turnstileToken = $request->input('cf-turnstile-response');
        $turnstileToken = is_string($turnstileToken) ? $turnstileToken : null;

        app(CaptchaGuard::class)->verify(
            $turnstileToken,
            $request->string('captcha_answer')->toString(),
        );

        try {
            $validated = $request->validated();
            $courseRequest = DB::transaction(function () use (
                $action,
                $paymentService,
                $validated,
                $course,
                $file,
            ) {
                $courseRequest = $action->execute(
                    course: $course,
                    studentName: $this->stringFromValidated($validated, 'student_name'),
                    studentEmail: $this->stringFromValidated($validated, 'student_email'),
                    studentPhone: $this->stringFromValidated($validated, 'student_phone'),
                    studentCity: $this->stringFromValidated($validated, 'student_city'),
                    studentNote: $this->stringFromValidated($validated, 'student_note'),
                    paymentMethod: $this->stringFromValidated($validated, 'payment_method', 'MANUAL'),
                    userId: auth()->user()?->id,
                );

                $amountIqd = is_int($course->price_iqd) ? $course->price_iqd : (int) $course->price_iqd;

                $paymentService->storeProof(
                    courseRequest: $courseRequest,
                    amountIqd: $amountIqd,
                    senderName: $this->stringFromValidated($validated, 'student_name'),
                    transactionReference: null,
                    file: $file,
                );

                return $courseRequest->refresh();
            });
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        session([
            'latest_course_request.'.$course->id => $courseRequest->public_tracking_code,
        ]);

        return redirect()->route('request.success', [
            'code' => $courseRequest->public_tracking_code,
        ]);
    }

    public function success(string $code): View|RedirectResponse
    {
        $courseRequest = CourseRequest::query()
            ->with(['course', 'latestPaymentProof'])
            ->where('public_tracking_code', $code)
            ->firstOrFail();

        // Only the browser session that created the request may view the
        // success page. Anyone else holding the URL is sent to the public
        // tracking page, which reveals nothing sensitive without the
        // matching email hash.
        $sessionCode = session('latest_course_request.'.$courseRequest->course_id);

        if ($sessionCode !== $code) {
            return redirect()->route('track', ['code' => $code]);
        }

        return view('public.request-success', [
            'courseRequest' => $courseRequest,
            'course' => $courseRequest->course,
        ]);
    }
}
