@extends('layouts.app')

@section('title', __('profile.title') . ' - Cwt Academy')

@section('content')
@include('components.recaptcha-v3')
<section class="pt-32 pb-16 px-6 max-w-(--spacing-container) mx-auto">
    <header class="mb-10">
        <p class="text-gold-400 text-sm font-medium tracking-wide uppercase mb-2">{{ __('profile.title') }}</p>
        <h1 class="font-(--font-headline) text-3xl md:text-4xl font-bold text-text-primary mb-3">
            {{ $user->name }}
        </h1>
        <p class="text-text-secondary max-w-2xl">{{ __('profile.subtitle') }}</p>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Account information --}}
        <div class="lg:col-span-2">
            <div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-gold-400/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

                <div class="flex items-center gap-4 mb-6 relative z-10">
                    <div class="w-14 h-14 rounded-full bg-gold-400/10 flex items-center justify-center text-gold-400 font-bold text-xl">
                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="font-semibold text-text-primary text-lg">{{ __('profile.account_information') }}</h2>
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5 relative z-10">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-text-muted mb-1">{{ __('profile.name') }}</dt>
                        <dd class="text-text-primary font-medium">{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-text-muted mb-1">{{ __('profile.email') }}</dt>
                        <dd class="text-text-primary font-medium break-all">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-text-muted mb-1">{{ __('profile.role') }}</dt>
                        <dd>
                            @php
                                $roleValue = $user->role->value ?? '';
                                $roleLabel = __('enum.user_role.' . strtolower($roleValue));
                                $roleLabelFallback = $roleLabel === 'enum.user_role.' . strtolower($roleValue) ? $roleValue : $roleLabel;
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gold-400/10 text-gold-400 border border-gold-400/20">
                                {{ $roleLabelFallback ?: '-' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-text-muted mb-1">{{ __('profile.status') }}</dt>
                        <dd>
                            @php
                                $userStatusValue = $user->status->value ?? '';
                                $userStatusLabel = __('enum.user_status.' . strtolower($userStatusValue));
                                $userStatusLabelFallback = $userStatusLabel === 'enum.user_status.' . strtolower($userStatusValue) ? $userStatusValue : $userStatusLabel;
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $userStatusValue === 'ACTIVE' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' }}">
                                {{ $userStatusLabelFallback ?: '-' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-text-muted mb-1">{{ __('profile.member_since') }}</dt>
                        <dd class="text-text-primary font-medium">{{ optional($user->created_at)->format('M Y') ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            @include('components.webauthn-manager')
        </div>

        {{-- Quick actions sidebar --}}
        <aside class="space-y-4">
            <div class="bg-bg-card border border-border-default rounded-2xl p-6">
                <h3 class="font-semibold text-text-primary mb-4">{{ __('profile.quick_actions') }}</h3>
                <div class="space-y-3">
                    <a href="/dashboard" class="block p-4 bg-bg-section rounded-xl border border-border-default hover:border-gold-400/40 transition-colors group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-text-primary group-hover:text-gold-400 transition-colors">{{ __('profile.my_courses') }}</p>
                                <p class="text-xs text-text-muted mt-1">{{ __('profile.my_courses_desc') }}</p>
                            </div>
                            <span class="material-symbols-outlined text-text-muted group-hover:text-gold-400 transition-colors">arrow_forward</span>
                        </div>
                    </a>
                    <a href="/courses" class="block p-4 bg-bg-section rounded-xl border border-border-default hover:border-gold-400/40 transition-colors group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-text-primary group-hover:text-gold-400 transition-colors">{{ __('profile.browse_courses') }}</p>
                                <p class="text-xs text-text-muted mt-1">{{ __('profile.browse_courses_desc') }}</p>
                            </div>
                            <span class="material-symbols-outlined text-text-muted group-hover:text-gold-400 transition-colors">arrow_forward</span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="bg-bg-card border border-border-default rounded-2xl p-6">
                <h3 class="font-semibold text-text-primary mb-2">{{ __('profile.support') }}</h3>
                <p class="text-sm text-text-secondary mb-4">{{ __('profile.support_desc') }}</p>
                <a href="/contact" class="inline-flex items-center gap-2 text-sm font-medium text-gold-400 hover:underline">
                    {{ __('profile.contact_support') }}
                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>
        </aside>
    </div>
</section>
@endsection
