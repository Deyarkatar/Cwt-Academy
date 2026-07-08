@extends('layouts.app')

@section('title', __('request.page_title'))

@section('content')
<section class="pt-32 pb-16 px-6 max-w-2xl mx-auto">
    <div class="mb-8">
        <a href="/courses/{{ $course?->slug ?? '' }}" class="text-sm text-text-muted hover:text-gold-400 transition-colors">&larr; {{ __('request.back_to_course') }}</a>
    </div>

    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-gold-400 text-text-on-gold flex items-center justify-center">
            <span class="material-symbols-outlined">workspace_premium</span>
        </div>
        <div>
            <h1 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary">{{ __('request.title') }}</h1>
            <p class="text-text-secondary text-sm">{{ $course?->title ?? '' }} — {{ ($course?->price_iqd ?? 0) === 0 ? __('request.free_course') : number_format($course?->price_iqd).' IQD' }}</p>
        </div>
    </div>

    <div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8">
        <form method="POST" action="{{ route('course-requests.store') }}" enctype="multipart/form-data" class="space-y-6" data-submit-loading>
            @csrf
            <input type="hidden" name="course_id" value="{{ $course?->id }}">

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.full_name') }} <span class="text-red-400">*</span></label>
                <input type="text" name="student_name" required maxlength="255" value="{{ old('student_name') }}" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('request.full_name_placeholder') }}">
                @error('student_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.email') }} <span class="text-red-400">*</span></label>
                <input type="email" name="student_email" required maxlength="255" value="{{ old('student_email', auth()->user()?->email) }}" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('request.email_placeholder') }}">
                @error('student_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.phone') }} <span class="text-red-400">*</span></label>
                <input type="tel" name="student_phone" required maxlength="40" value="{{ old('student_phone') }}" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('request.phone_placeholder') }}">
                @error('student_phone')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.city') }} <span class="text-red-400">*</span></label>
                <input type="text" name="student_city" required maxlength="80" value="{{ old('student_city') }}" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('request.city_placeholder') }}">
                @error('student_city')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.note') }} <span class="text-text-muted">({{ __('request.optional') }})</span></label>
                <textarea name="student_note" rows="3" maxlength="1000" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors resize-none" placeholder="{{ __('request.note_placeholder') }}">{{ old('student_note') }}</textarea>
            </div>

            {{-- Payment proof section --}}
            <div class="pt-2 border-t border-border-default">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-gold-400">receipt_long</span>
                    <h2 class="font-(--font-headline) text-lg font-bold text-text-primary">{{ __('request.payment_proof_title') }}</h2>
                </div>

                <div class="bg-gold-400/5 border border-gold-400/20 rounded-xl p-4 mb-4">
                    <p class="text-sm text-text-secondary leading-relaxed">{{ __('request.payment_proof_body') }}</p>
                    <div class="grid grid-cols-3 gap-2 mt-4">
                        <div class="bg-bg-elevated border border-gold-400/20 rounded-lg px-3 py-2 text-center">
                            <div class="text-gold-400 text-xs font-bold tracking-wide">{{ __('request.method_fib') }}</div>
                        </div>
                        <div class="bg-bg-elevated border border-gold-400/20 rounded-lg px-3 py-2 text-center">
                            <div class="text-gold-400 text-xs font-bold tracking-wide">{{ __('request.method_fastpay') }}</div>
                        </div>
                        <div class="bg-bg-elevated border border-gold-400/20 rounded-lg px-3 py-2 text-center">
                            <div class="text-gold-400 text-xs font-bold tracking-wide">{{ __('request.method_card') }}</div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.payment_method_label') }} <span class="text-text-muted">({{ __('request.optional') }})</span></label>
                    <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="{{ __('request.payment_method_label') }}">
                        @foreach (['FIB' => 'method_fib', 'FASTPAY' => 'method_fastpay', 'CARD' => 'method_card'] as $value => $key)
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="{{ $value }}" {{ old('payment_method') === $value ? 'checked' : '' }} class="peer sr-only">
                                <div class="bg-bg-input border border-border-default rounded-lg px-3 py-2.5 text-center text-sm text-text-secondary transition-colors peer-checked:bg-gold-400/10 peer-checked:border-gold-400 peer-checked:text-gold-400 hover:border-gold-400/40">
                                    {{ __("request.{$key}") }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.proof_amount') }} <span class="text-text-muted">({{ __('request.optional') }})</span></label>
                        <input type="number" name="amount_iqd" min="0" max="10000000" value="{{ old('amount_iqd', $course?->price_iqd) }}" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('request.proof_amount_placeholder') }}">
                        @error('amount_iqd')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-primary mb-2">{{ __('request.proof_transaction_reference') }} <span class="text-text-muted">({{ __('request.optional') }})</span></label>
                        <input type="text" name="transaction_reference" maxlength="255" value="{{ old('transaction_reference') }}" class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors" placeholder="{{ __('request.proof_transaction_reference_placeholder') }}">
                        @error('transaction_reference')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="payment_proof" class="block text-sm font-medium text-text-primary mb-2">{{ __('request.upload_proof_label') }} @if (($course?->price_iqd ?? 0) > 0)<span class="text-red-400">*</span>@endif</label>

                    <label
                        for="payment_proof"
                        class="block border-2 border-dashed border-border-default rounded-xl p-6 text-center cursor-pointer hover:border-gold-400/60 focus-within:border-gold-400 transition-colors bg-bg-input/40"
                        data-proof-dropzone
                    >
                        <input
                            type="file"
                            id="payment_proof"
                            name="payment_proof"
                            accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                            @if (($course?->price_iqd ?? 0) > 0) required @endif
                            class="sr-only"
                            data-proof-input
                        >
                        <div class="flex flex-col items-center gap-2 pointer-events-none">
                            <span class="material-symbols-outlined text-3xl text-gold-400">cloud_upload</span>
                            <div class="text-sm text-text-primary font-medium" data-proof-placeholder>{{ __('request.upload_proof_placeholder') }}</div>
                            <div class="text-xs text-text-muted" data-proof-helper>{{ __('request.upload_proof_helper') }}</div>
                        </div>
                    </label>
                    @error('payment_proof')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Bot protection --}}
            <div class="pt-2 border-t border-border-default">
                <x-turnstile action="course_request" />
                @error('cf-turnstile-response')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror

                <div class="mt-4">
                    <x-math-captcha />
                    @error('captcha_answer')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <button type="submit" class="w-full btn-primary py-3 flex items-center justify-center gap-2" data-loading-text="{{ __('request.submitting') }}">
                <span class="btn-spinner hidden material-symbols-outlined animate-spin">progress_activity</span>
                <span class="btn-text">{{ __('request.submit_request_button') }}</span>
            </button>
        </form>
    </div>
</section>
@endsection
