<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageCourses() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'instructor_id' => ['nullable', 'integer', 'exists:instructors,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('courses')->ignore($this->route('id')),
            ],
            'short_description' => ['required', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:50000'],
            'price_iqd' => ['required', 'integer', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:1000', 'url:https'],
            'level' => ['required', 'string', Rule::enum(CourseLevel::class)],
            'language' => ['required', 'string', Rule::enum(CourseLanguage::class)],
            // SECURITY: status, is_featured, published_at are NOT mass-assignable.
            // They must be managed through dedicated actions to prevent privilege
            // escalation or unauthorized publishing. These fields are accepted
            // only in the update method via explicit action logic.
        ];
    }
}
