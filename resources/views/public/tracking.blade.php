@extends('layouts.app')
@section('title', __('tracking.page_title'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-2xl mx-auto">
    <h1 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary mb-2 text-center">{{ __('tracking.title') }}</h1>
    <p class="text-text-secondary text-center mb-8">{{ __('tracking.subtitle') }}</p>
    <div class="bg-bg-card border border-border-default rounded-2xl p-6 mb-8">
        <form method="GET" action="/track" class="flex gap-3">
            <input type="text" name="code" value="{{ $code ?? '' }}" placeholder="{{ __('tracking.code_placeholder') }}" class="flex-1 bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors uppercase">
            <button type="submit" class="btn-primary px-6 py-3">{{ __('tracking.button') }}</button>
        </form>
    </div>
    @if(isset($requestData))
    <div class="space-y-4">
        @include('components.status-timeline', ['data' => $requestData])
    </div>
    @endif
</section>
@endsection
