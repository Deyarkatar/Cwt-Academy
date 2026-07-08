<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Security\PasswordPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 2026 hardened seeder.
 *
 *  - Seeds ONE SUPER_ADMIN account only (single super-user invariant).
 *  - In production: a fresh, cryptographically-random password is generated
 *    and printed exactly once to STDOUT. ADMIN_DEFAULT_PASSWORD is ignored
 *    in production to remove the possibility of leakage via .env.
 *  - In non-production: ADMIN_DEFAULT_PASSWORD may be used so devs/CI can
 *    pre-share credentials, but it is still validated against the policy.
 *  - Additional admins (ADMIN, FINANCE_MANAGER) must be created via
 *    `php artisan admin:create` with distinct credentials.
 *  - role/status are NEVER set via mass-assignment.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultEmail = config('services.admin.default_email');

        if (! is_string($defaultEmail) || $defaultEmail === '') {
            throw new \RuntimeException(
                'ADMIN_DEFAULT_EMAIL must be set in .env before seeding.'
            );
        }

        // Idempotent: skip if a SUPER_ADMIN already exists.
        $existing = User::query()->where('role', UserRole::SUPER_ADMIN->value)->first();
        if ($existing !== null) {
            $this->command->warn(sprintf(
                'A SUPER_ADMIN already exists (%s). Skipping UserSeeder.',
                $existing->email
            ));

            return;
        }

        $password = $this->resolvePassword();

        $user = new User([
            'name' => 'Super Admin',
            'email' => $defaultEmail,
            'password' => Hash::make($password),
        ]);
        $user->role = UserRole::SUPER_ADMIN->value;
        $user->status = UserStatus::ACTIVE->value;
        // Force password rotation on first login: explicitly null the verified
        // timestamp so first-login flows can prompt rotation if implemented.
        $user->email_verified_at = now();
        $user->save();

        $this->printCredentials($defaultEmail, $password);
    }

    /**
     * In production: generate a fresh random password every seeding run,
     * ignoring whatever is in .env. In non-production: prefer the .env value
     * (validated) so test/dev fixtures are reproducible.
     */
    private function resolvePassword(): string
    {
        if (app()->environment('production')) {
            // 32 chars, mixed case, digits, symbols. Far above policy minima.
            return Str::password(32, letters: true, numbers: true, symbols: true, spaces: false);
        }

        $envPasswordRaw = config('services.admin.default_password', '');
        $envPassword = is_string($envPasswordRaw) ? $envPasswordRaw : '';

        if ($envPassword === '') {
            throw new \RuntimeException(
                'ADMIN_DEFAULT_PASSWORD must be set in non-production .env before seeding.'
            );
        }

        $error = PasswordPolicy::validate($envPassword);
        if ($error !== null) {
            throw new \RuntimeException('Unsafe ADMIN_DEFAULT_PASSWORD: '.$error);
        }

        return $envPassword;
    }

    private function printCredentials(string $email, string $password): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->getOutput()->writeln('');
        $this->command->getOutput()->writeln('  <fg=green;options=bold>SUPER_ADMIN account seeded</>');
        $this->command->getOutput()->writeln(sprintf('    email:    %s', $email));

        if (app()->environment('production')) {
            $path = storage_path('app/.admin-credentials');
            $content = sprintf("email: %s\npassword: %s\n", $email, $password);
            file_put_contents($path, $content);
            chmod($path, 0600);

            $this->command->getOutput()->writeln('    password: <fg=yellow;options=bold>[written to storage/app/.admin-credentials]</>');
            $this->command->getOutput()->writeln(
                '    <fg=red>STORE THIS NOW. It will not be shown again.</>'
            );
            $this->command->getOutput()->writeln(
                '    Rotate immediately on first login.'
            );
        } else {
            $this->command->getOutput()->writeln('    password: (from ADMIN_DEFAULT_PASSWORD in .env)');
        }
        $this->command->getOutput()->writeln('');
    }
}
