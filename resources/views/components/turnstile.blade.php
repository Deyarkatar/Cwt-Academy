@props([
    'action' => null,
    'theme' => 'light',
    'size' => 'normal',
    'language' => app()->getLocale(),
])

@php
$siteKey = config('security.captcha.turnstile.site_key');
$enabled = config('security.captcha.driver') === 'turnstile' && ! empty($siteKey);
$nonce = request()->attributes->get('csp_nonce') ?? '';
@endphp

@if($enabled)
<div class="cf-turnstile"
     data-sitekey="{{ $siteKey }}"
     data-action="{{ $action ?? 'default' }}"
     data-theme="{{ $theme }}"
     data-size="{{ $size }}"
     data-language="{{ $language }}"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer
        @if($nonce) nonce="{{ $nonce }}" @endif></script>
@endif
