<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Only create test credentials in the testing environment.
        if (! app()->environment('testing')) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Test Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::SUPER_ADMIN,
                'status' => UserStatus::ACTIVE,
            ]
        );
    }
}
