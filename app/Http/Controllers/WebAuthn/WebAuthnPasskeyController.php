<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnPasskeyController
{
    /**
     * List the WebAuthn credentials owned by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        $credentials = $user
            ->webAuthnCredentials()
            ->orderByDesc('created_at')
            ->get()
            ->makeVisible(['created_at']);

        return response()->json(['credentials' => $credentials]);
    }

    /**
     * Revoke a WebAuthn credential belonging to the authenticated user.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        $deleted = $user
            ->webAuthnCredentials()
            ->whereKey($id)
            ->delete();

        return $deleted
            ? response()->json(null, 204)
            : response()->json(['message' => __('profile.passkey_not_found')], 404);
    }
}
