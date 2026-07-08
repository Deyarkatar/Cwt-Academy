@php
use App\Services\Captcha\MathCaptchaService;

$service = app(MathCaptchaService::class);
$captcha = $service->generate();
@endphp

@if($service->enabled())
<div class="space-y-2">
    <label class="block text-sm font-medium text-text-primary">
        {{ __('auth.captcha_question', ['question' => $captcha['question']]) }}
    </label>
    <input type="text"
           name="{{ $captcha['input_name'] }}"
           required
           autocomplete="off"
           inputmode="numeric"
           pattern="[0-9]+"
           class="w-full bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors"
           placeholder="{{ __('auth.captcha_placeholder') }}">
</div>
@endif
