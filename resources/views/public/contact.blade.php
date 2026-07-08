@extends('layouts.app')

@section('title', __('contact.page_title') . ' - Cwt Academy')

@section('content')
@php
    $cards = [
        [
            'key' => 'telegram',
            'initials' => 'TG',
            'url' => 'https://t.me/cyberwithtm',
        ],
        [
            'key' => 'facebook',
            'initials' => 'FB',
            'url' => 'https://www.facebook.com/share/17fSmDWPJV/?mibextid=wwXIfr',
        ],
        [
            'key' => 'instagram',
            'initials' => 'IG',
            'url' => 'https://www.instagram.com/cyber.with.tm?igsh=MXIxdjlxNzBrMG9meg%3D%3D&utm_source=qr',
        ],
        [
            'key' => 'tiktok',
            'initials' => 'TT',
            'url' => 'https://www.tiktok.com/@cyber.with.tm?_r=1&_t=ZS-96TjdE8cBjb',
        ],
        [
            'key' => 'youtube',
            'initials' => 'YT',
            'url' => 'https://youtube.com/@cyber_with_tm?si=q99yQvjwfaeRKVrr',
        ],
    ];
@endphp

{{-- Hero --}}
<section class="relative pt-32 pb-12 px-6 max-w-(--spacing-container) mx-auto overflow-hidden">
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-gold-400/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>
    <div class="max-w-3xl">
        <p class="text-gold-400 text-sm font-medium tracking-wide uppercase mb-3">{{ __('contact.hero_eyebrow') }}</p>
        <h1 class="font-(--font-headline) hero-title text-3xl md:text-5xl font-bold text-text-primary mb-5 leading-tight">
            {{ __('contact.hero_title') }}
        </h1>
        <p class="text-text-secondary text-base md:text-lg mb-8 max-w-2xl">
            {{ __('contact.hero_body') }}
        </p>
        <div class="flex flex-col sm:flex-row gap-3">
            <a
                href="https://t.me/cyberwithtm"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-primary inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold"
            >
                <span class="material-symbols-outlined">send</span>
                {{ __('contact.cta_telegram') }}
            </a>
            <a href="/courses" class="btn-secondary inline-flex items-center justify-center px-6 py-3 text-sm font-semibold">
                {{ __('contact.cta_browse') }}
            </a>
        </div>
    </div>
</section>

{{-- Official accounts grid --}}
<section class="px-6 max-w-(--spacing-container) mx-auto pb-12">
    <h2 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary mb-8">
        {{ __('contact.section_title') }}
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($cards as $card)
            <article class="bg-bg-card border border-border-default rounded-2xl p-6 flex flex-col h-full hover:border-gold-400/40 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-gold-400/10 border border-gold-400/20 flex items-center justify-center text-gold-400 font-bold text-sm">
                        {{ $card['initials'] }}
                    </div>
                    <h3 class="font-semibold text-text-primary">
                        {{ __('contact.' . $card['key'] . '_title') }}
                    </h3>
                </div>
                <p class="text-sm text-text-secondary mb-6 flex-grow">
                    {{ __('contact.' . $card['key'] . '_body') }}
                </p>
                <a
                    href="{{ $card['url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-secondary inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium"
                >
                    {{ __('contact.' . $card['key'] . '_button') }}
                    <span class="material-symbols-outlined text-base">open_in_new</span>
                </a>
            </article>
        @endforeach

        {{-- Direct contact card (phone) --}}
        <article class="bg-bg-card border border-gold-400/30 rounded-2xl p-6 flex flex-col h-full sm:col-span-2 lg:col-span-1 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-40 h-40 bg-gold-400/10 rounded-full blur-2xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
            <div class="flex items-center gap-4 mb-4 relative z-10">
                <div class="w-12 h-12 rounded-xl bg-gold-400/15 border border-gold-400/30 flex items-center justify-center text-gold-400">
                    <span class="material-symbols-outlined">call</span>
                </div>
                <h3 class="font-semibold text-text-primary">{{ __('contact.direct_title') }}</h3>
            </div>
            <p class="text-sm text-text-secondary mb-4 relative z-10">{{ __('contact.direct_body') }}</p>
            <p
                id="contact-phone"
                dir="ltr"
                class="font-mono text-2xl font-bold text-gold-400 tracking-wide mb-4 relative z-10 select-all"
            >07518717793</p>
            <button
                type="button"
                data-copy-target="#contact-phone"
                data-copy-success="{{ __('contact.direct_button_success') }}"
                class="btn-primary inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold relative z-10"
            >
                <span class="material-symbols-outlined text-base">content_copy</span>
                {{ __('contact.direct_button') }}
            </button>
        </article>
    </div>

    <p class="text-text-muted text-xs text-center mt-10 max-w-2xl mx-auto">
        {{ __('contact.trust_note') }}
    </p>
</section>
@endsection
