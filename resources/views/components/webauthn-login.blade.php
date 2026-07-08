@once
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ route('webauthn.js') }}" type="application/javascript"></script>
@endonce

@php($recaptchaSiteKey = config('services.recaptcha.site_key'))
<input type="hidden" id="webauthn-login-recaptcha" value="">

<button type="button" id="webauthn-login-btn"
    class="w-full flex items-center justify-center gap-3 bg-bg-section hover:bg-bg-card text-text-primary font-medium border border-border-default rounded-xl px-4 py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
    <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A19.874 19.874 0 0012 21.5c4.307 0 8.17-1.34 11.248-3.598M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 7a2 2 0 100-4 2 2 0 000 4z"/>
    </svg>
    {{ __('auth.sign_in_with_passkey') }}
</button>

<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        'use strict';

        var btn = document.getElementById('webauthn-login-btn');
        var tokenField = document.getElementById('webauthn-login-recaptcha');
        var siteKey = @json($recaptchaSiteKey);
        var notSupportedMessage = @json(__('auth.passkey_not_supported'));
        var errorMessage = @json(__('auth.passkey_error'));

        if (!btn || typeof WebAuthn === 'undefined' || !WebAuthn.supportsWebAuthn()) {
            if (btn) {
                btn.disabled = true;
                btn.title = notSupportedMessage;
            }
            return;
        }

        function withRecaptchaToken(callback) {
            if (!siteKey || typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                callback(tokenField ? tokenField.value : '');
                return;
            }

            grecaptcha.ready(function () {
                grecaptcha.execute(siteKey, { action: 'webauthn_login' })
                    .then(function (token) {
                        if (tokenField) {
                            tokenField.value = token;
                        }
                        callback(token);
                    })
                    .catch(function () {
                        callback(tokenField ? tokenField.value : '');
                    });
            });
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.classList.add('opacity-75');

            withRecaptchaToken(function (token) {
                var webauthn = new WebAuthn({}, {}, false);

                webauthn.login({ 'g-recaptcha-response': token })
                    .then(function (response) {
                        window.location.href = response && response.redirect ? response.redirect : '/dashboard';
                    })
                    .catch(function () {
                        alert(errorMessage);
                        btn.disabled = false;
                        btn.classList.remove('opacity-75');
                    });
            });
        });
    })();
</script>
