<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\AuditAction;
use App\Enums\TelegramAccessGrantStatus;
use App\Http\Controllers\Controller;
use App\Models\TelegramAccessGrant;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TelegramAccessActionController extends Controller
{
    public function markAdded(Request $request, int $id): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $lockedGrant = TelegramAccessGrant::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorize('markAdded', $lockedGrant);

                if ($lockedGrant->status !== TelegramAccessGrantStatus::PENDING_MANUAL_ADD) {
                    throw new \RuntimeException(__('errors.invalid_status'));
                }

                $validated = $request->validate([
                    'manual_access_reference' => ['nullable', 'string', 'max:255'],
                    'admin_note' => ['nullable', 'string', 'max:1000'],
                ]);

                $lockedGrant->status = TelegramAccessGrantStatus::MANUALLY_ADDED->value;
                $lockedGrant->granted_by = auth()->user()?->id;
                $lockedGrant->granted_at = now();
                $lockedGrant->manual_access_reference = $validated['manual_access_reference'] ?? $lockedGrant->manual_access_reference;
                $lockedGrant->admin_note = $validated['admin_note'] ?? $lockedGrant->admin_note;
                $lockedGrant->save();

                AuditLogger::log(
                    AuditAction::MANUAL_TELEGRAM_ACCESS_GRANTED,
                    'TelegramAccessGrant',
                    $lockedGrant->id,
                    ['status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD->value],
                    ['status' => TelegramAccessGrantStatus::MANUALLY_ADDED->value],
                    auth()->user()?->id,
                );
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', __('errors.generic'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', __('errors.generic'));
        }

        return redirect()->back()->with('success', __('admin.access_marked_added'));
    }

    public function revoke(Request $request, int $id): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $lockedGrant = TelegramAccessGrant::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorize('markRevoked', $lockedGrant);

                if ($lockedGrant->status === TelegramAccessGrantStatus::REVOKED->value) {
                    throw new \RuntimeException(__('errors.already_rejected'));
                }

                $validated = $request->validate([
                    'revoked_reason' => ['required', 'string', 'max:1000'],
                ]);

                $oldStatus = $lockedGrant->status->value;

                $lockedGrant->status = TelegramAccessGrantStatus::REVOKED->value;
                $lockedGrant->revoked_by = auth()->user()?->id;
                $lockedGrant->revoked_at = now();
                $lockedGrant->revoked_reason = $validated['revoked_reason'];
                $lockedGrant->save();

                AuditLogger::log(
                    AuditAction::TELEGRAM_ACCESS_REVOKED,
                    'TelegramAccessGrant',
                    $lockedGrant->id,
                    ['status' => $oldStatus],
                    ['status' => TelegramAccessGrantStatus::REVOKED->value, 'reason' => $validated['revoked_reason']],
                    auth()->user()?->id,
                );
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', __('errors.generic'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', __('errors.generic'));
        }

        return redirect()->back()->with('success', __('admin.access_revoked'));
    }
}
