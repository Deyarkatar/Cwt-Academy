<?php

namespace App\Policies;

use App\Models\PaymentProof;
use App\Models\User;

class PaymentProofPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageRequests();
    }

    public function view(User $user, PaymentProof $paymentProof): bool
    {
        return $user->canManageRequests();
    }

    public function download(User $user, PaymentProof $paymentProof): bool
    {
        return $user->canManageRequests();
    }

    public function approve(User $user, PaymentProof $paymentProof): bool
    {
        if (! $user->canApprovePayments()) {
            return false;
        }

        $courseRequest = $paymentProof->courseRequest;
        if (! $courseRequest) {
            return false;
        }

        return $user->email !== $courseRequest->student_email;
    }

    public function reject(User $user, PaymentProof $paymentProof): bool
    {
        return $user->canApprovePayments();
    }
}
