<?php

namespace App\Policies;

use App\Models\TelegramChannel;
use App\Models\User;

class TelegramChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageTelegramChannels();
    }

    public function view(User $user, TelegramChannel $channel): bool
    {
        return $user->canManageTelegramChannels();
    }

    public function create(User $user): bool
    {
        return $user->canManageTelegramChannels();
    }

    public function update(User $user, TelegramChannel $channel): bool
    {
        return $user->canManageTelegramChannels();
    }

    public function deactivate(User $user, TelegramChannel $channel): bool
    {
        return $user->canManageTelegramChannels();
    }
}
