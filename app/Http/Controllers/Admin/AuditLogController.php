<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'entity_type' => ['nullable', 'string', 'max:50'],
            'actor_id' => ['nullable', 'integer'],
        ]);

        $query = AuditLog::query();

        if ($validated['action'] ?? null) {
            $query->where('action', $validated['action']);
        }

        if ($validated['entity_type'] ?? null) {
            $query->where('entity_type', $validated['entity_type']);
        }

        if ($validated['actor_id'] ?? null) {
            $query->where('actor_id', $validated['actor_id']);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'ok' => true,
            'data' => $logs,
        ]);
    }
}
