<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequestRequest extends FormRequest
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
        return [
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('status', 'ACTIVE')],
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['required', 'string', 'max:40'],
            'student_city' => ['required', 'string', 'max:80'],
            'student_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', Rule::in(['FIB', 'FASTPAY', 'CARD', 'MANUAL'])],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.exists' => __('request.validation.course_unavailable'),
            'student_phone.required' => __('request.validation.phone_required'),
            'student_city.required' => __('request.validation.city_required'),
        ];
    }
}
