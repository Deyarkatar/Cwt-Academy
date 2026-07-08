<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCourseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApprovePayments() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_proof_id' => ['required', 'integer', 'exists:payment_proofs,id'],
        ];
    }
}
