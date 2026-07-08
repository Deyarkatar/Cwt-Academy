<?php

namespace App\Policies;

use App\Models\Instructor;
use App\Models\User;

class InstructorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageInstructors();
    }

    public function view(User $user, Instructor $instructor): bool
    {
        return $user->canManageInstructors();
    }

    public function create(User $user): bool
    {
        return $user->canManageInstructors();
    }

    public function update(User $user, Instructor $instructor): bool
    {
        return $user->canManageInstructors();
    }

    public function approve(User $user, Instructor $instructor): bool
    {
        return $user->canManageInstructors();
    }

    public function reject(User $user, Instructor $instructor): bool
    {
        return $user->canManageInstructors();
    }
}
