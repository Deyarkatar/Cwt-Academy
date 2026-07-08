<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravel\Sanctum\HasApiTokens;

/*
 * Mass-assignment policy (2026): `role` and `status` are deliberately
 * EXCLUDED from $fillable. Privilege fields must be set explicitly:
 *
 *     $user = new User(['name' => ..., 'email' => ..., 'password' => ...]);
 *     $user->role = UserRole::STUDENT;     // explicit
 *     $user->status = UserStatus::ACTIVE;  // explicit
 *     $user->save();
 *
 * This prevents privilege escalation via $request->all() / mass-assign bugs
 * in any present or future code path.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, WebAuthnAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, WebAuthnAuthentication;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'totp_verified_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [
            UserRole::ADMIN,
            UserRole::SUPER_ADMIN,
            UserRole::FINANCE_MANAGER,
        ], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isFinanceManager(): bool
    {
        return $this->role === UserRole::FINANCE_MANAGER;
    }

    public function canManageCourses(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::SUPER_ADMIN], true);
    }

    public function canManageRequests(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::SUPER_ADMIN, UserRole::FINANCE_MANAGER], true);
    }

    public function canApprovePayments(): bool
    {
        return in_array($this->role, [UserRole::FINANCE_MANAGER, UserRole::SUPER_ADMIN], true);
    }

    public function canManageAdmins(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function canManageCategories(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::SUPER_ADMIN], true);
    }

    public function canManageInstructors(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::SUPER_ADMIN], true);
    }

    public function canManageTelegramChannels(): bool
    {
        return in_array($this->role, [UserRole::ADMIN, UserRole::SUPER_ADMIN], true);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
