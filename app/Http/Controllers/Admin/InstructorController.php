<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\InstructorStatus;
use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstructorController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Instructor::class);

        $instructors = Instructor::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $instructors,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Instructor::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'avatar' => ['nullable', 'url', 'max:1000'],
        ]);

        $instructor = new Instructor($validated);
        $instructor->status = InstructorStatus::PENDING->value;
        $instructor->save();

        AuditLogger::logModelChange(AuditAction::INSTRUCTOR_CREATED, $instructor);

        return response()->json([
            'ok' => true,
            'data' => $instructor,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $instructor = Instructor::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $this->authorize('update', $instructor);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'],
                'bio' => ['nullable', 'string', 'max:5000'],
                'avatar' => ['nullable', 'url', 'max:1000'],
                'admin_notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $old = $instructor->toArray();
            $instructor->fill($validated);
            $instructor->save();

            AuditLogger::log(
                AuditAction::INSTRUCTOR_UPDATED,
                'Instructor',
                $instructor->id,
                $old,
                $instructor->toArray(),
                auth()->user()?->id,
            );

            return response()->json([
                'ok' => true,
                'data' => $instructor->fresh(),
            ]);
        });
    }

    public function approve(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $instructor = Instructor::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $this->authorize('approve', $instructor);

            if ($instructor->status === InstructorStatus::APPROVED->value) {
                throw ValidationException::withMessages([
                    'instructor' => 'Instructor is already approved.',
                ]);
            }

            $oldStatus = $instructor->status;
            $instructor->status = InstructorStatus::APPROVED->value;
            $instructor->save();

            AuditLogger::log(
                AuditAction::INSTRUCTOR_APPROVED,
                'Instructor',
                $instructor->id,
                ['status' => $oldStatus->value],
                ['status' => InstructorStatus::APPROVED->value],
                auth()->user()?->id,
            );

            return response()->json([
                'ok' => true,
                'message' => 'Instructor approved.',
                'data' => $instructor->fresh(),
            ]);
        });
    }

    public function reject(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $instructor = Instructor::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $this->authorize('reject', $instructor);

            if ($instructor->status === InstructorStatus::REJECTED->value) {
                throw ValidationException::withMessages([
                    'instructor' => 'Instructor is already rejected.',
                ]);
            }

            $oldStatus = $instructor->status;
            $instructor->status = InstructorStatus::REJECTED->value;
            $instructor->save();

            AuditLogger::log(
                AuditAction::INSTRUCTOR_REJECTED,
                'Instructor',
                $instructor->id,
                ['status' => $oldStatus->value],
                ['status' => InstructorStatus::REJECTED->value],
                auth()->user()?->id,
            );

            return response()->json([
                'ok' => true,
                'message' => 'Instructor rejected.',
                'data' => $instructor->fresh(),
            ]);
        });
    }
}
