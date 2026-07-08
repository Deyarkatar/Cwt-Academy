<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCourses();
    }

    public function view(User $user, Course $course): bool
    {
        return $user->canManageCourses();
    }

    public function create(User $user): bool
    {
        return $user->canManageCourses();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->canManageCourses();
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    public function archive(User $user, Course $course): bool
    {
        return $user->canManageCourses();
    }
}
