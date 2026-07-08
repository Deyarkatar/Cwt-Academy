<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unified web form request: creates the course request and uploads the
 * payment proof in a single submit. Used by the public web controller
 * at POST /course-requests/store. The legacy 2-step backend
 * (StoreCourseRequestRequest + payment-proof.store) is preserved for the API.
 */
class StoreCourseRequestWithProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = $this->maxMb() * 1024;

        return [
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('status', 'ACTIVE')],
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['required', 'string', 'max:40'],
            'student_city' => ['required', 'string', 'max:80'],
            'student_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', Rule::in(['FIB', 'FASTPAY', 'CARD', 'MANUAL'])],
            'amount_iqd' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                "max:{$maxKb}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.exists' => __('request.validation.course_unavailable'),
            'student_phone.required' => __('request.validation.phone_required'),
            'student_city.required' => __('request.validation.city_required'),
            'payment_proof.required' => __('request.validation.proof_required'),
            'payment_proof.file' => __('request.validation.proof_file'),
            'payment_proof.mimes' => __('request.validation.proof_mimes'),
            'payment_proof.mimetypes' => __('request.validation.proof_mimes'),
            'payment_proof.max' => __('request.validation.proof_max', ['mb' => $this->maxMb()]),
        ];
    }

    private function maxMb(): int
    {
        $value = config('services.payment_proof_max_mb', 5);

        return is_numeric($value) ? (int) $value : 5;
    }
}
