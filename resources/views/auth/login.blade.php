@extends('layouts.app')
@section('title', __('auth.sign_in'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-md mx-auto">
    <div class="text-center mb-8">
        <h1 class="font-(--font-headline) text-2xl font-bold text-text-primary mb-2">{{ __('auth.welcome_back') }}</h1>
        <p class="text-text-secondary text-sm">{{ __('auth.sign_in_to_continue') }}</p>
    </div>
    <div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8">
        @include('components.social-login')

        @include('components.webauthn-login')

        <form method="POST" action="/login" class="space-y-5 mt-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('auth.email') }}</label>
                <input type="email" name="email" required class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('auth.email_placeholder') }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('auth.password') }}</label>
                <div class="relative">
                    <input type="password" id="login-password" name="password" required class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 pr-12 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('auth.password_placeholder') }}">
                    <button type="button" data-toggle-password="login-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-primary transition-colors" tabindex="-1">
                        <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a9.969 9.969 0 01-3.59 3.59"/></svg>
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-text-secondary">
                    <input type="checkbox" name="remember" class="rounded bg-bg-input border-border-default text-gold-400 focus:ring-gold-400">
                    {{ __('auth.remember_me') }}
                </label>
                <a href="/forgot-password" class="text-gold-400 hover:underline">{{ __('auth.forgot_password') }}</a>
            </div>

            @include('components.math-captcha')

            @include('components.recaptcha-v3')

            <button type="submit" class="w-full btn-primary py-3">
                {{ __('auth.sign_in') }}
            </button>
        </form>
        <div class="mt-6 pt-6 border-t border-border-default text-center text-sm text-text-secondary">
            {{ __('auth.no_account') }} <a href="/register" class="text-gold-400 hover:underline">{{ __('auth.get_started') }}</a>
        </div>
    </div>
</section>

@endsection
