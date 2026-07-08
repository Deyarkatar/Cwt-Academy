@extends('layouts.app')

@section('title', ($course?->title ?? '') . ' - Cwt Academy')

@section('content')
<section class="pt-32 pb-16 px-6 max-w-(--spacing-container) mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main content -->
        <div class="lg:col-span-2">
            <div class="bg-bg-card border border-border-default rounded-2xl overflow-hidden mb-8">
                <div class="h-64 bg-bg-elevated flex items-center justify-center text-text-muted text-6xl font-bold opacity-20">
                    {{ $course ? strtoupper(substr($course->title, 0, 2)) : 'CW' }}
                </div>
            </div>

            <h1 class="font-(--font-headline) hero-title text-3xl font-bold text-text-primary mb-4">{{ $course?->title ?? '-' }}</h1>

            <div class="flex flex-wrap gap-3 mb-6">
                <span class="text-xs text-text-muted bg-bg-elevated px-3 py-1 rounded-full">{{ $course?->category?->name ?? '-' }}</span>
                <span class="text-xs text-text-muted bg-bg-elevated px-3 py-1 rounded-full">{{ $course?->language?->value ?? '-' }}</span>
                <span class="text-xs text-text-muted bg-bg-elevated px-3 py-1 rounded-full">{{ $course?->level?->value ?? '-' }}</span>
            </div>

            <div class="prose prose-invert max-w-none mb-8">
                <p class="text-text-secondary">{{ $course?->description ?? '' }}</p>
            </div>

            <div class="bg-bg-card border border-border-default rounded-2xl p-6 mb-8">
                <h3 class="font-semibold text-text-primary mb-4">{{ __('course.what_you_learn') }}</h3>
                <ul class="space-y-2 text-text-secondary">
                    @if(!empty($course?->learning_points))
                        @foreach($course->learning_points as $point)
                        <li class="flex items-start gap-2">
                            <span class="text-gold-400 mt-0.5">&check;</span>
                            <span>{{ $point }}</span>
                        </li>
                        @endforeach
                    @else
                        <li class="text-text-muted">{{ __('course.what_you_learn') }}</li>
                    @endif
                </ul>
            </div>

            <div class="bg-gold-400/5 border border-gold-400/20 rounded-2xl p-6">
                <h3 class="font-semibold text-gold-400 mb-3">{{ __('course.telegram_delivery_title') }}</h3>
                <p class="text-text-secondary text-sm">{{ __('course.telegram_delivery_desc') }}</p>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="bg-bg-card border border-border-default rounded-2xl p-6 sticky top-28">
                <div class="text-3xl font-bold text-text-primary mb-2">{{ number_format($course?->price_iqd ?? 0) }} <span class="text-sm font-normal text-text-muted">IQD</span></div>
                <p class="text-text-muted text-sm mb-6">{{ __('course.price_note') }}</p>

                <button
                    type="button"
                    data-modal-open="buy-modal"
                    class="flex w-full items-center justify-center gap-2 btn-primary text-center py-3 mb-3 text-base font-semibold"
                    data-buy-button
                >
                    <span class="material-symbols-outlined text-xl">shopping_bag</span>
                    {{ __('course.buy') }}
                </button>
                <p class="text-xs text-text-muted text-center mb-4">{{ __('course.buy_button_hint') }}</p>

                @if($trackingCode)
                <div class="bg-gold-400/5 border border-gold-400/20 rounded-xl p-4 mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-gold-400 text-sm">check_circle</span>
                        <span class="text-sm font-semibold text-gold-400">{{ __('course.request_submitted') }}</span>
                    </div>
                    <p class="text-xs text-text-secondary mb-3">{{ __('course.request_submitted_for_course') }}</p>

                    <div class="mb-3">
                        <span class="text-xs text-text-muted block mb-1">{{ __('course.tracking_code') }}</span>
                        <div class="flex items-center gap-2">
                            <code id="course-tracking-code" class="text-sm font-mono text-text-primary bg-bg-elevated px-2.5 py-1 rounded border border-border-default flex-1 truncate">{{ $trackingCode }}</code>
                            <button
                                type="button"
                                data-copy-target="#course-tracking-code"
                                data-copy-success="{{ __('course.copied') }}"
                                class="shrink-0 inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded bg-gold-400/10 text-gold-400 hover:bg-gold-400/20 transition-colors"
                            >
                                <span class="material-symbols-outlined text-sm">content_copy</span>
                                <span class="copy-label">{{ __('course.copy') }}</span>
                            </button>
                        </div>
                    </div>

                    <a
                        href="{{ route('track', ['code' => $trackingCode]) }}"
                        class="flex w-full items-center justify-center gap-1.5 btn-secondary py-2 text-xs"
                    >
                        <span class="material-symbols-outlined text-sm">location_searching</span>
                        {{ __('course.track_request') }}
                    </a>
                </div>
                @endif

                <p class="text-xs text-text-muted mb-2">{{ __('course.or_request_internally') }}</p>
                <a href="/courses/{{ $course?->slug ?? '#' }}/request" class="block w-full btn-secondary text-center py-2.5 mb-4 text-sm">
                    {{ __('course.cta_request') }}
                </a>

                <div class="space-y-4 pt-6 border-t border-border-default">
                    <div class="flex justify-between text-sm">
                        <span class="text-text-muted">{{ __('course.duration') }}</span>
                        <span class="text-text-primary">{{ $course?->duration ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-text-muted">{{ __('course.level') }}</span>
                        <span class="text-text-primary">{{ $course?->level?->value ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-text-muted">{{ __('course.language') }}</span>
                        <span class="text-text-primary">{{ $course?->language?->value ?? '-' }}</span>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-border-default">
                    <p class="text-xs text-text-muted">{{ __('course.manual_approval_notice') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Buy / Request Access Modal --}}
    <div
        id="buy-modal"
        data-modal
        class="fixed inset-0 z-50 hidden items-center justify-center px-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="buy-modal-title"
        aria-hidden="true"
    >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" data-modal-close></div>

        <div class="relative w-full max-w-md bg-bg-card border border-gold-400/30 rounded-2xl p-6 md:p-8 shadow-2xl shadow-gold-400/10">
            <button
                type="button"
                data-modal-close
                class="absolute top-4 right-4 text-text-muted hover:text-gold-400 transition-colors"
                aria-label="{{ __('course.modal_close') }}"
            >
                <span class="material-symbols-outlined">close</span>
            </button>

            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-gold-400/10 text-gold-400 flex items-center justify-center">
                    <span class="material-symbols-outlined">workspace_premium</span>
                </div>
                <h2 id="buy-modal-title" class="font-(--font-headline) text-xl md:text-2xl font-bold text-text-primary">
                    {{ __('course.modal_title') }}
                </h2>
            </div>

            <p class="text-text-secondary text-sm md:text-base mb-6 leading-relaxed">
                {{ __('course.modal_body') }}
            </p>

            <div class="bg-gold-400/5 border border-gold-400/20 rounded-xl p-4 mb-6">
                <p class="text-xs text-text-secondary">
                    <span class="text-gold-400 font-semibold">{{ __('course.modal_course_label') }}:</span>
                    {{ $course?->title ?? '' }}
                </p>
                <p class="text-xs text-text-secondary mt-1">
                    <span class="text-gold-400 font-semibold">{{ __('course.modal_price_label') }}:</span>
                    {{ number_format($course?->price_iqd ?? 0) }} IQD
                </p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3">
                <button
                    type="button"
                    data-modal-close
                    class="flex-1 btn-secondary py-2.5 text-sm"
                >
                    {{ __('course.modal_cancel') }}
                </button>
                <a
                    href="/courses/{{ $course?->slug ?? '#' }}/request"
                    class="flex-1 btn-primary py-2.5 text-sm flex items-center justify-center gap-2"
                    data-modal-request-button
                >
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                    {{ __('course.modal_request_button') }}
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
