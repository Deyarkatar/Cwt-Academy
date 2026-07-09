@extends('layouts.app')

@section('title', __('request.success_submitted_title'))

@section('content')
<section class="pt-32 pb-16 px-6 max-w-2xl mx-auto">
    <div class="mb-8">
        <a href="/courses/{{ $course?->slug ?? '' }}" class="text-sm text-text-muted hover:text-gold-400 transition-colors">&larr; {{ __('request.back_to_course') }}</a>
    </div>

    <div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8 mb-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-full bg-green-500/10 text-green-400 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">check_circle</span>
            </div>
            <div>
                <h1 class="font-(--font-headline) text-xl md:text-2xl font-bold text-text-primary">{{ __('request.success_submitted_title') }}</h1>
                <p class="text-text-secondary text-sm mt-1">{{ __('request.success_submitted_body') }}</p>
            </div>
        </div>

        <div class="bg-bg-section border border-border-default rounded-xl p-5 mb-6">
            <p class="text-sm text-text-muted mb-2">{{ __('request.your_tracking_code') }}</p>
            <div class="flex items-center gap-3 flex-wrap">
                <code id="tracking-code" data-testid="tracking-code" class="text-2xl md:text-3xl font-mono font-bold text-gold-400 tracking-wider">{{ $courseRequest->public_tracking_code }}</code>
                <button type="button" data-copy-target="#tracking-code" data-copy-success="{{ __('request.code_copied') }}" class="shrink-0 flex items-center gap-1.5 text-sm text-gold-400 hover:text-gold-300 transition-colors px-3 py-1.5 rounded-lg bg-gold-400/10">
                    <span class="material-symbols-outlined text-base">content_copy</span>
                    <span class="copy-label">{{ __('request.copy_code') }}</span>
                </button>
            </div>
            <p class="text-xs text-text-muted mt-2">{{ __('request.tracking_explanation') }}</p>
        </div>

        <div class="space-y-3 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-text-muted">{{ __('request.course_label') }}</span>
                <span class="text-text-primary font-medium">{{ $course?->title ?? '-' }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-text-muted">{{ __('request.course_price') }}</span>
                <span class="text-text-primary font-medium">{{ number_format($course?->price_iqd ?? 0) }} IQD</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-text-muted">{{ __('request.request_status') }}</span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-400">{{ __('request.status_waiting_admin_review') }}</span>
            </div>
            @if($courseRequest->latestPaymentProof)
            <div class="flex justify-between text-sm">
                <span class="text-text-muted">{{ __('request.payment_proof') }}</span>
                <span class="text-green-400 font-medium inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    {{ __('request.proof_received') }}
                </span>
            </div>
            @endif
        </div>

        <div class="bg-gold-400/5 border border-gold-400/20 rounded-xl p-4 mb-6">
            <p class="text-sm text-text-secondary">{{ __('messages.manual_review_notice') }}</p>
        </div>

        <a href="{{ route('track', ['code' => $courseRequest->public_tracking_code]) }}" class="inline-flex items-center gap-2 text-sm text-gold-400 hover:text-gold-300 transition-colors">
            <span class="material-symbols-outlined text-base">visibility</span>
            {{ __('request.view_tracking') }}
        </a>
    </div>
</section>
@endsection
