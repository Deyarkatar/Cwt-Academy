@php
$statuses = [
    ['key' => 'submitted', 'label' => __('tracking.status_submitted'), 'active' => true],
    ['key' => 'payment_received', 'label' => __('tracking.status_payment_received'), 'active' => in_array($data['status'] ?? '', ['PENDING_REVIEW', 'APPROVED', 'REJECTED'])],
    ['key' => 'pending_review', 'label' => __('tracking.status_pending_review'), 'active' => in_array($data['status'] ?? '', ['PENDING_REVIEW', 'APPROVED', 'REJECTED'])],
    ['key' => 'approved', 'label' => __('tracking.status_approved'), 'active' => ($data['status'] ?? '') === 'APPROVED'],
    ['key' => 'telegram_pending', 'label' => __('tracking.status_telegram_pending'), 'active' => ($data['status'] ?? '') === 'APPROVED'],
    ['key' => 'telegram_granted', 'label' => __('tracking.status_telegram_granted'), 'active' => ($data['telegram_access']['status'] ?? '') === 'MANUALLY_ADDED'],
];
@endphp
<div class="bg-bg-card border border-border-default rounded-2xl p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="font-semibold text-text-primary">{{ $data['course_title'] ?? '' }}</h3>
            <p class="text-sm text-text-muted">{{ __('tracking.code') }}: {{ $data['tracking_code'] ?? '' }}</p>
        </div>
        @php
            $timelineStatus = $data['status'] ?? '';
            $timelineStatusLabel = __('enum.course_request_status.' . strtolower($timelineStatus));
            $timelineStatusLabelFallback = $timelineStatusLabel === 'enum.course_request_status.' . strtolower($timelineStatus) ? $timelineStatus : $timelineStatusLabel;
        @endphp
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $timelineStatus === 'APPROVED' ? 'bg-green-500/10 text-green-400' : ($timelineStatus === 'REJECTED' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">
            {{ $timelineStatusLabelFallback }}
        </span>
    </div>
    <div class="space-y-0">
        @foreach($statuses as $i => $status)
        <div class="flex gap-4">
            <div class="flex flex-col items-center">
                <div class="w-3 h-3 rounded-full {{ $status['active'] ? 'bg-gold-400' : 'bg-bg-elevated border border-border-default' }}"></div>
                @if($i < count($statuses) - 1)
                <div class="w-px h-full {{ $status['active'] ? 'bg-gold-400/30' : 'bg-border-default' }}"></div>
                @endif
            </div>
            <div class="pb-6">
                <p class="text-sm font-medium {{ $status['active'] ? 'text-text-primary' : 'text-text-muted' }}">{{ $status['label'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
    @if(isset($data['telegram_access']['message']))
    <div class="mt-4 bg-gold-400/5 border border-gold-400/20 rounded-lg p-4">
        <p class="text-sm text-text-secondary">{{ $data['telegram_access']['message'] }}</p>
    </div>
    @endif

    @if(! empty($data['telegram_channel_url']))
    <div class="mt-4 bg-gold-400/10 border border-gold-400/30 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="material-symbols-outlined text-gold-400">verified</span>
            <p class="text-sm font-semibold text-gold-400">{{ __('tracking.telegram_channel_ready') }}</p>
        </div>
        <a href="{{ \App\Support\Security\UrlHelper::safeTelegramUrl($data['telegram_channel_url']) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm text-gold-400 hover:text-gold-300 transition-colors break-all">
            <span class="material-symbols-outlined text-base">open_in_new</span>
            {{ \App\Support\Security\UrlHelper::safeTelegramUrl($data['telegram_channel_url']) }}
        </a>
    </div>
    @elseif(! empty($data['telegram_channel_fallback']))
    <div class="mt-4 bg-amber-500/5 border border-amber-500/20 rounded-lg p-4">
        <p class="text-sm text-amber-400">{{ __('tracking.telegram_channel_not_configured') }}</p>
    </div>
    @endif

    @if(isset($data['public_rejection_note']))
    <div class="mt-4 bg-red-500/5 border border-red-500/20 rounded-lg p-4">
        <p class="text-sm text-red-400">{{ __('tracking.rejection_reason') }}: {{ $data['public_rejection_note'] }}</p>
    </div>
    @endif
</div>
