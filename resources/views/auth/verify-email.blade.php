@extends('layouts.app')

@section('title', __('auth.verify_email'))

@section('content')
<section class="min-h-[70vh] flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md bg-bg-card border border-border-default rounded-2xl p-8 shadow-lg">
        <h2 class="text-2xl font-bold text-text-primary mb-4 text-center">{{ __('auth.verify_email') }}</h2>
        <p class="text-text-secondary text-center mb-6">
            {{ __('auth.verify_email_instructions') }}
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="mb-4 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm text-center">
                {{ __('auth.verification_link_sent') }}
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="text-center">
            @csrf
            <button type="submit" class="btn-primary py-3 px-6">
                {{ __('auth.resend_verification_email') }}
            </button>
        </form>
    </div>
</section>
@endsection
