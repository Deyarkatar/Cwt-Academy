@extends('layouts.app')
@section('title', __('auth.forgot_password'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-md mx-auto">
    <div class="text-center mb-8">
        <h1 class="font-(--font-headline) text-2xl font-bold text-text-primary mb-2">{{ __('auth.forgot_password') }}</h1>
        <p class="text-text-secondary text-sm">{{ __('auth.forgot_password_instructions') }}</p>
    </div>
    <div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8">
        @if (session('status'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="/forgot-password" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('auth.email') }}</label>
                <input type="email" name="email" required value="{{ old('email') }}"
                    class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors"
                    placeholder="{{ __('auth.email_placeholder') }}">
                @error('email')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            @include('components.math-captcha')

            @include('components.recaptcha-v3')

            <button type="submit" class="w-full btn-primary py-3">
                {{ __('auth.send_reset_link') }}
            </button>
        </form>
        <div class="mt-6 pt-6 border-t border-border-default text-center text-sm text-text-secondary">
            <a href="/login" class="text-gold-400 hover:underline">{{ __('auth.back_to_login') }}</a>
        </div>
    </div>
</section>
@endsection
