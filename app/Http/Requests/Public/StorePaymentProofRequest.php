<?php

namespace App\Http\Requests\Public;

use App\Models\PaymentProof;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentProofRequest extends FormRequest
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
        $maxMb = $this->maxMb();

        return [
            'amount_iqd' => ['required', 'integer', 'min:1', 'max:10000000'],
            'sender_name' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\-]+$/u'],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $hash = PaymentProof::hashTransactionReference(is_string($value) ? $value : null);
                    if ($hash !== null && PaymentProof::query()->where('transaction_reference_hash', $hash)->exists()) {
                        $fail(__('errors.transaction_reference_used'));
                    }
                },
            ],
            'proof_file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:'.($maxMb * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'proof_file.max' => 'The proof file must not exceed '.$this->maxMb().'MB.',
        ];
    }

    private function maxMb(): int
    {
        $value = config('services.payment_proof_max_mb', 5);

        return is_numeric($value) ? (int) $value : 5;
    }
}
