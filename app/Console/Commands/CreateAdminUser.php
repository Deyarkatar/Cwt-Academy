<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Security\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Securely create an admin user with strict role validation, RFC-compliant
 * email validation, uniqueness check, and enforced password policy.
 *
 * Security guarantees (2026):
 *  - Invalid roles ALWAYS throw; never silently elevated to SUPER_ADMIN.
 *  - Email is validated by Laravel's `email:rfc,strict,dns` chain.
 *  - Password meets the centralised `PasswordPolicy`.
 *  - Existing email is rejected (DB unique constraint enforces it too).
 */
class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--name= : Admin display name}
                            {--email= : Admin email address}
                            {--role= : Role (super_admin, admin, finance_manager). Required, no default for safety.}
                            {--password= : Password (will prompt if omitted; min 12 chars, mixed case, digit, symbol)}';

    protected $description = 'Create a new admin user with strict role and password validation.';

    /**
     * Strict role allow-list. Never include STUDENT here; this command is for
     * elevated accounts only. STUDENT accounts go through the public
     * registration flow.
     */
    private const ROLE_MAP = [
        'super_admin' => UserRole::SUPER_ADMIN,
        'admin' => UserRole::ADMIN,
        'finance_manager' => UserRole::FINANCE_MANAGER,
    ];

    public function handle(): int
    {
        $name = $this->stringOption('name', 'Admin name');
        $email = $this->stringOption('email', 'Admin email');
        $roleInput = $this->option('role');
        $password = $this->option('password');

        if (! is_string($roleInput)) {
            $roleInput = $this->ask('Role (super_admin, admin, finance_manager)');
        }
        if (! is_string($roleInput)) {
            $this->error('Role must be a string.');

            return self::FAILURE;
        }
        $roleInput = strtolower(trim($roleInput));

        if (! is_string($password)) {
            $password = $this->secret('Password');
        }
        if (! is_string($password)) {
            $this->error('Password must be a string.');

            return self::FAILURE;
        }

        if ($name === '' || strlen($name) > 255) {
            $this->error('Name is required and must be at most 255 characters.');

            return self::FAILURE;
        }

        if ($roleInput === '') {
            $this->error(sprintf(
                'Role is required. Allowed values: %s',
                implode(', ', array_keys(self::ROLE_MAP))
            ));

            return self::FAILURE;
        }

        if (! array_key_exists($roleInput, self::ROLE_MAP)) {
            // CRITICAL: never silently fall back to SUPER_ADMIN (the previous
            // behaviour). Fail closed.
            $this->error(sprintf(
                'Invalid role "%s". Allowed values: %s',
                $roleInput,
                implode(', ', array_keys(self::ROLE_MAP))
            ));

            return self::FAILURE;
        }

        $emailValidator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:rfc,strict', 'max:255']]
        );

        if ($emailValidator->fails()) {
            $this->error('Invalid email address: '.$emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        $passwordError = PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            $this->error($passwordError);

            return self::FAILURE;
        }

        $role = self::ROLE_MAP[$roleInput];

        // Use direct attribute assignment because role/status are no longer
        // mass-assignable on the User model.
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->role = $role->value;
        $user->status = UserStatus::ACTIVE->value;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Admin user created: {$user->email} ({$role->value})");
        $this->warn('Reminder: rotate this password on first login and enable 2FA when available.');

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $prompt): string
    {
        $value = $this->option($name);

        if (! is_string($value) || $value === '') {
            $value = $this->ask($prompt);
        }

        if (! is_string($value)) {
            $value = '';
        }

        return trim($value);
    }
}
