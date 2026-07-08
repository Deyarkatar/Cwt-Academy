<?php

namespace App\Policies;

use App\Models\CourseRequest;
use App\Models\User;

class CourseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageRequests();
    }

    public function view(User $user, CourseRequest $courseRequest): bool
    {
        return $user->canManageRequests();
    }

    public function approve(User $user, CourseRequest $courseRequest): bool
    {
        return $user->canApprovePayments() && $user->email !== $courseRequest->student_email;
    }

    public function reject(User $user, CourseRequest $courseRequest): bool
    {
        return $user->canManageRequests();
    }
}
