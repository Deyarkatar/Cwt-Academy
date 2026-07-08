@extends('layouts.app')

@section('title', 'Cwt Academy - Kurdistan Course Marketplace')

@section('content')
@php($isKurdish = app()->getLocale() === 'ku')
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
        data-dir="{{ $isKurdish ? 'rtl' : 'ltr' }}"
        data-is-kurdish="{{ $isKurdish ? 'true' : 'false' }}"
    >
        {{-- SSR fallback: rendered immediately so the hero is never blank.
             The React Spline app will replace this markup once it hydrates.
             Kurdish/RTL: robot on the left, text on the right.
             English/LTR: text on the left, robot on the right. --}}
        <div class="hero-card group w-full bg-[#0e0e0e] border border-white/10 rounded-[28px] shadow-2xl overflow-hidden relative min-h-[560px] lg:min-h-[680px]">
            <div class="pointer-events-none absolute top-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-[#FFD700]/[0.08] blur-3xl z-0 {{ $isKurdish ? 'left-[-10%]' : 'right-[-10%]' }}"></div>

            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-[52%_48%] items-center gap-8 lg:gap-12 px-5 py-8 sm:px-8 sm:py-10 lg:p-[clamp(24px,4vw,64px)]">
                {{-- Robot visual --}}
                <div class="hero-robot relative w-full h-[380px] sm:h-[440px] lg:h-[620px] xl:h-[680px] {{ $isKurdish ? 'lg:order-1' : 'lg:order-2' }}">
                    <div class="hero-robot-stage absolute inset-0 lg:-inset-x-4 lg:-bottom-4"></div>
                </div>

                {{-- Text content --}}
                <div dir="{{ $isKurdish ? 'rtl' : 'ltr' }}" class="flex flex-col justify-center text-center items-center {{ $isKurdish ? 'lg:order-2 lg:text-right lg:items-end' : 'lg:order-1 lg:text-left lg:items-start' }}">
                    <h1 class="font-extrabold text-white tracking-tight hero-title-display" data-rtl="{{ $isKurdish ? 'true' : 'false' }}">
                        {{ __('home.hero_title') }}
                        <span class="text-[#FFD700]">{{ __('home.hero_highlight') }}</span>
                    </h1>

                    <p class="mt-5 text-base md:text-lg text-[#b7b5b4] max-w-lg leading-relaxed hero-subtitle-display" data-rtl="{{ $isKurdish ? 'true' : 'false' }}">
                        {{ __('home.hero_subtitle') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-4 justify-center {{ $isKurdish ? 'lg:justify-end' : 'lg:justify-start' }}">
                        <a href="/courses" class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-2xl font-semibold text-sm bg-gradient-to-br from-[#FFD700] to-[#FFB800] text-[#3a3000] shadow-[0_0_20px_rgba(255,215,0,0.2)] hover:shadow-[0_0_30px_rgba(255,215,0,0.4)] hover:opacity-95 transition-all">
                            {{ __('home.cta_browse') }}
                        </a>
                        <a href="/contact" class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-2xl font-semibold text-sm border border-[#FFD700] text-[#FFD700] hover:bg-[rgba(255,215,0,0.1)] transition-all">
                            {{ __('home.cta_contact') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- How it works -->
<section class="py-20 px-6 max-w-[var(--spacing-container)] mx-auto">
    <h2 class="font-[var(--font-headline)] text-2xl md:text-3xl font-bold text-text-primary mb-12 text-center">{{ __('home.how_it_works') }}</h2>
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
<section class="py-20 px-6 max-w-[var(--spacing-container)] mx-auto">
    <div class="flex justify-between items-end mb-10">
        <h2 class="font-[var(--font-headline)] text-2xl md:text-3xl font-bold text-text-primary">{{ __('home.featured_courses') }}</h2>
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
<section class="py-20 px-6 max-w-[var(--spacing-container)] mx-auto">
    <div class="bg-bg-card border border-border-default rounded-2xl p-8 md:p-12 text-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-gold-400/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-gold-400/10 flex items-center justify-center text-gold-400">
            <span class="material-symbols-outlined text-3xl">chat</span>
        </div>
        <h2 class="font-[var(--font-headline)] text-2xl font-bold text-text-primary mb-4 relative z-10">{{ __('home.telegram_title') }}</h2>
        <p class="text-text-secondary max-w-2xl mx-auto relative z-10">{{ __('home.telegram_desc') }}</p>
    </div>
</section>

@vite('resources/js/spline-app.tsx')
@endsection
