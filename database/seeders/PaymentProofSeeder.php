<?php

namespace Database\Seeders;

use App\Enums\PaymentProofStatus;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentProofSeeder extends Seeder
{
    public function run(): void
    {
        $reviewRequest = CourseRequest::where('status', 'PENDING_REVIEW')->first();
        $approvedRequest = CourseRequest::where('status', 'APPROVED')->first();

        if (! $reviewRequest) {
            return;
        }

        // Payment proof pending review
        PaymentProof::create([
            'course_request_id' => $reviewRequest->id,
            'amount_iqd' => $reviewRequest->course->price_iqd ?? 150000,
            'sender_name' => $reviewRequest->student_name,
            'transaction_reference' => 'REF-'.strtoupper(Str::random(8)),
            'proof_file_path' => 'payment_proofs/sample_proof_1.jpg',
            'proof_mime' => 'image/jpeg',
            'proof_size_bytes' => 245760,
            'status' => PaymentProofStatus::PENDING,
        ]);

        // Approved payment proof
        if ($approvedRequest) {
            PaymentProof::create([
                'course_request_id' => $approvedRequest->id,
                'amount_iqd' => $approvedRequest->course->price_iqd ?? 150000,
                'sender_name' => $approvedRequest->student_name,
                'transaction_reference' => 'REF-'.strtoupper(Str::random(8)),
                'proof_file_path' => 'payment_proofs/sample_proof_2.jpg',
                'proof_mime' => 'image/jpeg',
                'proof_size_bytes' => 189440,
                'status' => PaymentProofStatus::APPROVED,
            ]);
        }
    }
}
