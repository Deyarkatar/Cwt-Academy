<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user) {
            if (! $user) {
                return false;
            }

            return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true)
                && $user->isActive()
                && $user->hasVerifiedEmail();
        });
    }
}
