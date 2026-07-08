<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTelegramChannelRequest;
use App\Models\TelegramChannel;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramChannelController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TelegramChannel::class);

        $channels = TelegramChannel::query()
            ->with(['course'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $channels,
        ]);
    }

    public function store(StoreTelegramChannelRequest $request): JsonResponse
    {
        $this->authorize('create', TelegramChannel::class);

        $channel = TelegramChannel::create($request->validated());

        AuditLogger::logModelChange(AuditAction::TELEGRAM_CHANNEL_CREATED, $channel);

        return response()->json([
            'ok' => true,
            'data' => $channel,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $channel = TelegramChannel::query()
            ->with(['course'])
            ->findOrFail($id);

        $this->authorize('view', $channel);

        return response()->json([
            'ok' => true,
            'data' => $channel,
        ]);
    }

    public function update(StoreTelegramChannelRequest $request, int $id): JsonResponse
    {
        $channel = TelegramChannel::query()->findOrFail($id);

        $this->authorize('update', $channel);
        $old = $channel->toArray();
        $channel->update($request->validated());

        AuditLogger::log(
            AuditAction::TELEGRAM_CHANNEL_UPDATED,
            'TelegramChannel',
            $channel->id,
            $old,
            $channel->toArray(),
            Auth::user()?->id,
        );

        return response()->json([
            'ok' => true,
            'data' => $channel->fresh(),
        ]);
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        $channel = TelegramChannel::query()->findOrFail($id);

        $this->authorize('deactivate', $channel);

        $channel->update([
            'is_active' => false,
        ]);

        AuditLogger::log(
            AuditAction::TELEGRAM_CHANNEL_DEACTIVATED,
            'TelegramChannel',
            $channel->id,
            ['is_active' => true],
            ['is_active' => false],
            Auth::user()?->id,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Channel deactivated.',
            'data' => $channel->fresh(),
        ]);
    }
}
