@extends('layouts.app')
@section('title', __('dashboard.page_title'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-(--spacing-container) mx-auto">
    <h1 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary mb-8">{{ __('dashboard.title') }}</h1>

    <!-- Summary stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-bg-card border border-border-default rounded-2xl p-5">
            <p class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('dashboard.total_requests') }}</p>
            <p class="text-2xl font-bold text-text-primary">{{ $totalRequests ?? 0 }}</p>
        </div>
        <div class="bg-bg-card border border-border-default rounded-2xl p-5">
            <p class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('dashboard.active_courses') }}</p>
            <p class="text-2xl font-bold text-green-400">{{ $activeCount ?? 0 }}</p>
        </div>
        <div class="bg-bg-card border border-border-default rounded-2xl p-5">
            <p class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('dashboard.pending_requests') }}</p>
            <p class="text-2xl font-bold text-amber-400">{{ $pendingCount ?? 0 }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <!-- Active courses -->
            @if(!empty($approvedRequests ?? []) && $approvedRequests->isNotEmpty())
            <div class="bg-bg-card border border-border-default rounded-2xl p-6">
                <h2 class="font-semibold text-text-primary mb-4">{{ __('dashboard.my_active_courses') }}</h2>
                <div class="space-y-4">
                    @foreach($approvedRequests as $req)
                    <div class="p-4 bg-bg-section rounded-xl border border-border-default">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <p class="font-semibold text-text-primary">{{ $req->course->title ?? '-' }}</p>
                                <p class="text-sm text-text-muted mt-0.5">{{ number_format($req->course->price_iqd ?? 0) }} IQD</p>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-500/10 text-green-400">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                {{ __('dashboard.this_course_is_active') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3 text-xs">
                            <div class="flex items-center gap-1.5 text-text-secondary">
                                <span class="material-symbols-outlined text-sm text-text-muted">receipt_long</span>
                                <span>{{ __('dashboard.status') }}:</span>
                                <span class="text-green-400 font-medium">{{ __('dashboard.approved') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-text-secondary">
                                <span class="material-symbols-outlined text-sm text-text-muted">payments</span>
                                <span>{{ __('dashboard.payment_proof') }}:</span>
                                <span class="font-medium">
                                    @if($req->latestPaymentProof)
                                        {{ $req->latestPaymentProof->status->value === 'APPROVED' ? __('dashboard.approved') : ($req->latestPaymentProof->status->value === 'REJECTED' ? __('dashboard.rejected') : __('dashboard.pending_review')) }}
                                    @else
                                        {{ __('dashboard.pending_review') }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 text-text-secondary">
                                <span class="material-symbols-outlined text-sm text-text-muted">tag</span>
                                <span>{{ __('dashboard.tracking_code') }}:</span>
                                <code class="text-text-primary font-mono bg-bg-elevated px-1.5 rounded">{{ $req->public_tracking_code }}</code>
                            </div>
                            <div class="flex items-center gap-1.5 text-text-secondary">
                                <span class="material-symbols-outlined text-sm text-text-muted">chat</span>
                                <span>Telegram:</span>
                                @php
                                    $grant = $req->telegramAccessGrant;
                                    $telegramReady = $grant && in_array($grant->status->value, ['MANUALLY_ADDED', 'ACCESS_SENT'], true);
                                @endphp
                                @if($telegramReady)
                                    <span class="text-green-400 font-medium">{{ __('dashboard.telegram_ready') }}</span>
                                @else
                                    <span class="text-amber-400 font-medium">{{ __('dashboard.telegram_waiting') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <a href="{{ route('track', ['code' => $req->public_tracking_code]) }}" class="inline-flex items-center justify-center gap-1.5 btn-secondary py-2 px-4 text-xs">
                                <span class="material-symbols-outlined text-sm">location_searching</span>
                                {{ __('dashboard.track_request') }}
                            </a>
                            @if($telegramReady && $req->course?->telegramChannel?->telegram_url)
                                <a href="{{ \App\Support\Security\UrlHelper::safeTelegramUrl($req->course->telegramChannel->telegram_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-1.5 bg-[#0088cc]/10 text-[#0088cc] hover:bg-[#0088cc]/20 border border-[#0088cc]/20 rounded-lg py-2 px-4 text-xs font-medium transition-colors">
                                    <span class="material-symbols-outlined text-sm">chat</span>
                                    {{ __('dashboard.open_telegram') }}
                                </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Pending requests -->
            @if(!empty($pendingRequests ?? []) && $pendingRequests->isNotEmpty())
            <div class="bg-bg-card border border-border-default rounded-2xl p-6">
                <h2 class="font-semibold text-text-primary mb-4">{{ __('dashboard.pending_requests_title') }}</h2>
                <div class="space-y-3">
                    @foreach($pendingRequests as $req)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-bg-section rounded-xl border border-border-default">
                        <div>
                            <p class="font-medium text-text-primary">{{ $req->course->title ?? '-' }}</p>
                            <p class="text-sm text-text-muted">{{ $req->public_tracking_code }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $req->status->value === 'REJECTED' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400' }}">
                                @php
                                    $pendingStatusValue = $req->status->value ?? '';
                                    $pendingStatusLabel = __('enum.course_request_status.' . strtolower($pendingStatusValue));
                                    $pendingStatusLabelFallback = $pendingStatusLabel === 'enum.course_request_status.' . strtolower($pendingStatusValue) ? $pendingStatusValue : $pendingStatusLabel;
                                @endphp
                                {{ $pendingStatusLabelFallback }}
                            </span>
                            <a href="{{ route('track', ['code' => $req->public_tracking_code]) }}" class="inline-flex items-center gap-1 text-xs text-gold-400 hover:underline">
                                <span class="material-symbols-outlined text-sm">location_searching</span>
                                {{ __('dashboard.track_request') }}
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Empty state -->
            @if(empty($requests) || $requests->isEmpty())
            <div class="bg-bg-card border border-border-default rounded-2xl p-10 text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-gold-400/10 text-gold-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">school</span>
                </div>
                <p class="text-text-muted mb-4">{{ __('dashboard.no_requests') }}</p>
                <a href="/courses" class="inline-flex items-center gap-1 text-gold-400 font-medium hover:underline">
                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    {{ __('dashboard.browse_courses') }}
                </a>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <div class="bg-bg-card border border-border-default rounded-2xl p-6">
                <h2 class="font-semibold text-text-primary mb-4">{{ __('dashboard.support') }}</h2>
                <p class="text-sm text-text-secondary mb-4">{{ __('dashboard.support_desc') }}</p>
                <a href="/contact" class="inline-flex text-sm font-medium text-gold-400 hover:underline">{{ __('dashboard.contact_support') }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
