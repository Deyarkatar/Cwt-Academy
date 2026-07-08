<?php

namespace App\Policies;

use App\Models\TelegramAccessGrant;
use App\Models\User;

class TelegramAccessGrantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageRequests();
    }

    public function view(User $user, TelegramAccessGrant $grant): bool
    {
        return $user->canManageRequests();
    }

    public function markAdded(User $user, TelegramAccessGrant $grant): bool
    {
        return $user->canManageTelegramChannels();
    }

    public function markRevoked(User $user, TelegramAccessGrant $grant): bool
    {
        return $user->canManageTelegramChannels();
    }
}
