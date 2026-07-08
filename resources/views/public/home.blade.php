@extends('layouts.app')

@section('title', 'Cwt Academy - Kurdistan Course Marketplace')

@section('content')
<!-- Hero — 3D Card (full-screen) -->
<section class="relative min-h-[calc(100svh-80px)] lg:min-h-[calc(100vh-80px)] flex items-center justify-center px-4 sm:px-6 lg:px-8 pt-24 lg:pt-28 pb-12">
    <div
        id="spline-mount"
        class="w-full max-w-[1280px]"
        data-title="{{ __('home.hero_title') }}"
        data-highlight="{{ __('home.hero_highlight') }}"
        data-subtitle="{{ __('home.hero_subtitle') }}"
        data-cta-browse="{{ __('home.cta_browse') }}"
        data-cta-contact="{{ __('home.cta_contact') }}"
        data-cta-browse-url="/courses"
        data-cta-contact-url="/contact"
        data-dir="{{ app()->getLocale() === 'ku' ? 'rtl' : 'ltr' }}"
    ></div>
</section>
<!-- How it works -->
<section class="py-20 px-6 max-w-(--spacing-container) mx-auto">
    <h2 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary mb-12 text-center">{{ __('home.how_it_works') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        @foreach([
            ['icon' => 'search', 'title' => __('home.step_browse_title'), 'desc' => __('home.step_browse_desc')],
            ['icon' => 'edit_note', 'title' => __('home.step_request_title'), 'desc' => __('home.step_request_desc')],
            ['icon' => 'verified', 'title' => __('home.step_approve_title'), 'desc' => __('home.step_approve_desc')],
            ['icon' => 'chat', 'title' => __('home.step_access_title'), 'desc' => __('home.step_access_desc')],
        ] as $step)
        <div class="stitch-card p-6 text-center">
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-gold-400/10 flex items-center justify-center text-gold-400">
                <span class="material-symbols-outlined">{{ $step['icon'] }}</span>
            </div>
            <h3 class="font-semibold text-text-primary mb-2">{{ $step['title'] }}</h3>
            <p class="text-sm text-text-secondary">{{ $step['desc'] }}</p>
        </div>
        @endforeach
    </div>
</section>

<!-- Featured Courses -->
<section class="py-20 px-6 max-w-(--spacing-container) mx-auto">
    <div class="flex justify-between items-end mb-10">
        <h2 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary">{{ __('home.featured_courses') }}</h2>
        <a href="/courses" class="text-gold-400 text-sm font-medium hover:underline decoration-gold-400 underline-offset-4 hidden sm:block">{{ __('home.view_all') }}</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($courses ?? [] as $course)
            @include('components.course-card', ['course' => $course])
        @endforeach
        @if(empty($courses ?? []))
            @for($i = 0; $i < 3; $i++)
                @include('components.course-card', ['course' => null])
            @endfor
        @endif
    </div>
    <div class="mt-6 text-center sm:hidden">
        <a href="/courses" class="text-gold-400 text-sm font-medium hover:underline decoration-gold-400 underline-offset-4">{{ __('home.view_all') }}</a>
    </div>
</section>

<!-- Telegram Delivery Notice -->
<section class="py-20 px-6 max-w-(--spacing-container) mx-auto">
    <div class="bg-bg-card border border-border-default rounded-2xl p-8 md:p-12 text-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-gold-400/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-gold-400/10 flex items-center justify-center text-gold-400">
            <span class="material-symbols-outlined text-3xl">chat</span>
        </div>
        <h2 class="font-(--font-headline) text-2xl font-bold text-text-primary mb-4 relative z-10">{{ __('home.telegram_title') }}</h2>
        <p class="text-text-secondary max-w-2xl mx-auto relative z-10">{{ __('home.telegram_desc') }}</p>
    </div>
</section>

@vite('resources/js/spline-app.tsx')
@endsection
