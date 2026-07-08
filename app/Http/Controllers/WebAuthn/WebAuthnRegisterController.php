<?php

namespace App\Http\Controllers\WebAuthn;

use App\Services\Captcha\RecaptchaService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

use function response;

class WebAuthnRegisterController
{
    /**
     * Maximum number of passkeys a single user may register.
     */
    private const MAX_PASSKEYS = 10;

    /**
     * Returns a challenge to be verified by the user device.
     */
    public function options(AttestationRequest $request): Responsable
    {
        app(RecaptchaService::class)->verify($request);

        if ($request->user()->webAuthnCredentials()->count() >= self::MAX_PASSKEYS) {
            throw ValidationException::withMessages([
                'alias' => __('profile.passkey_limit_reached'),
            ]);
        }

        return $request
            ->fastRegistration()
            ->toCreate();
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response
    {
        $alias = $request->input('alias');

        if (is_string($alias) && $alias !== '') {
            $validator = Validator::make(['alias' => $alias], [
                'alias' => ['string', 'max:255'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }

        if ($request->user()->webAuthnCredentials()->count() >= self::MAX_PASSKEYS) {
            throw ValidationException::withMessages([
                'alias' => __('profile.passkey_limit_reached'),
            ]);
        }

        $request->save(['alias' => is_string($alias) && $alias !== '' ? $alias : null]);

        return response()->noContent();
    }
}
