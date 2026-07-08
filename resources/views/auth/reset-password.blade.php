@extends('layouts.app')
@section('title', __('auth.reset_password'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-md mx-auto">
    <div class="text-center mb-8">
        <h1 class="font-(--font-headline) text-2xl font-bold text-text-primary mb-2">{{ __('auth.reset_password') }}</h1>
        <p class="text-text-secondary text-sm">{{ __('auth.reset_password_instructions') }}</p>
    </div>
    <div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8">
        <form method="POST" action="/reset-password" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('auth.email') }}</label>
                <input type="email" name="email" required value="{{ old('email', $email) }}" readonly
                    class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary opacity-60 cursor-not-allowed">
                @error('email')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('auth.new_password') }}</label>
                <div class="relative">
                    <input type="password" id="reset-password" name="password" required
                        class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 pr-12 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors"
                        placeholder="{{ __('auth.password_placeholder') }}">
                    <button type="button" data-toggle-password="reset-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-primary transition-colors" tabindex="-1">
                        <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a9.969 9.969 0 01-3.59 3.59"/></svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('auth.confirm_password') }}</label>
                <input type="password" name="password_confirmation" required
                    class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors"
                    placeholder="{{ __('auth.confirm_password_placeholder') }}">
            </div>

            <button type="submit" class="w-full btn-primary py-3">
                {{ __('auth.reset_password_button') }}
            </button>
        </form>
    </div>
</section>
@endsection
