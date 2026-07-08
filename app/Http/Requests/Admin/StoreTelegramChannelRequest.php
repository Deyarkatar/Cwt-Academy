<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTelegramChannelRequest extends FormRequest
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
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'telegram_url' => [
                'nullable',
                'string',
                'max:255',
                'url:https',
                'starts_with:https://t.me/',
            ],
            'private_channel_name' => ['nullable', 'string', 'max:255'],
            'internal_channel_reference' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
