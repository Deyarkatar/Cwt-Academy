@php
    $googleEnabled = is_string(config('services.google.client_id')) && config('services.google.client_id') !== ''
        && is_string(config('services.google.client_secret')) && config('services.google.client_secret') !== '';
    $githubEnabled = is_string(config('services.github.client_id')) && config('services.github.client_id') !== ''
        && is_string(config('services.github.client_secret')) && config('services.github.client_secret') !== '';
@endphp
    <div class="space-y-3">
        <form method="POST" action="{{ route('social.redirect', 'google') }}" class="w-full">
            @csrf
            <input type="hidden" name="g-recaptcha-response" value="">
            <button type="submit" @disabled(! $googleEnabled)
               class="w-full flex items-center justify-center gap-3 bg-white hover:bg-gray-100 text-gray-800 font-medium border border-border-default rounded-xl px-4 py-3 transition-colors {{ $googleEnabled ? '' : 'opacity-50 cursor-not-allowed' }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.766 12.2764c0-.9175-.0745-1.5884-.2356-2.284H12.24v4.1443h6.6178c-.1334 1.1076-.854 2.7756-2.4552 3.8968l-.0224.1492 3.5648 2.7616.247.0247c2.2682-2.0947 3.5762-5.177 3.5762-8.8326z"/>
                    <path fill="#34A853" d="M12.24 24c3.2417 0 5.9634-1.0673 7.9515-2.9083l-3.7893-2.9355c-1.0139.7071-2.3746 1.2007-4.1622 1.2007-3.1753 0-5.8701-2.0947-6.8306-4.9896l-.1409.012-3.7067 2.8686-.0485.1347C3.4874 21.3097 7.5493 24 12.24 24z"/>
                    <path fill="#FBBC05" d="M5.4094 14.3673c-.2535-.7476-.4002-1.5484-.4002-2.3673 0-.819.1467-1.6197.3869-2.3673l-.0067.1594-3.7529-2.9146-.1228.0584C.7307 8.1284.24 10.0089.24 12s.4907 3.8716 1.3537 5.383l3.8157-3.0157z"/>
                    <path fill="#EB4335" d="M12.24 4.6434c2.2528 0 3.7724.9731 4.6389 1.7863l3.3862-3.3067C18.1902 1.1893 15.4817 0 12.24 0 7.5493 0 3.4874 2.6903 1.5937 6.6169l3.8024 2.9524c.9738-2.8949 3.6686-4.9259 6.8439-4.9259z"/>
                </svg>
                {{ __('auth.continue_with_google') }}
            </button>
        </form>
        <form method="POST" action="{{ route('social.redirect', 'github') }}" class="w-full">
            @csrf
            <input type="hidden" name="g-recaptcha-response" value="">
            <button type="submit" @disabled(! $githubEnabled)
               class="w-full flex items-center justify-center gap-3 bg-[#24292f] hover:bg-[#1b1f23] text-white font-medium border border-border-default rounded-xl px-4 py-3 transition-colors {{ $githubEnabled ? '' : 'opacity-50 cursor-not-allowed' }}">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                </svg>
                {{ __('auth.continue_with_github') }}
            </button>
        </form>
        <div class="flex items-center gap-3 pt-1">
            <div class="flex-1 h-px bg-border-default"></div>
            <span class="text-xs text-text-muted">{{ __('auth.or_continue_with_email') }}</span>
            <div class="flex-1 h-px bg-border-default"></div>
        </div>
    </div>

    @once
        <script nonce="{{ $cspNonce ?? '' }}">
            (function () {
                'use strict';

                document.querySelectorAll('form[action^="{{ url('auth') }}"]').forEach(function (form) {
                    var btn = form.querySelector('button[type="submit"]');
                    if (!btn) {
                        return;
                    }

                    form.addEventListener('submit', function () {
                        if (!btn.disabled) {
                            btn.disabled = true;
                            btn.classList.add('opacity-75', 'cursor-wait');
                        }
                    });
                });
            })();
        </script>
    @endonce
