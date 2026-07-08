@php($recaptchaSiteKey = config('services.recaptcha.site_key'))
@if (is_string($recaptchaSiteKey) && $recaptchaSiteKey !== '')
    <input type="hidden" name="g-recaptcha-response" value="">
    @once
        <script nonce="{{ $cspNonce ?? '' }}" src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}" async defer></script>
        <script nonce="{{ $cspNonce ?? '' }}">
            (function () {
                'use strict';

                var SITE_KEY = @json($recaptchaSiteKey);

                document.addEventListener('submit', function (event) {
                    var form = event.target;

                    if (!(form instanceof HTMLFormElement)) {
                        return;
                    }

                    var tokenField = form.querySelector('input[name="g-recaptcha-response"]');
                    if (!tokenField) {
                        return; // Form does not use reCAPTCHA.
                    }

                    if (form.dataset.recaptchaDone === '1') {
                        return; // Token already injected; allow native submit.
                    }

                    event.preventDefault();

                    var action = (form.getAttribute('action') || '/submit')
                        .replace(/[^a-zA-Z0-9/_]/g, '')
                        .replace(/\//g, '_')
                        .replace(/^_+/, '') || 'submit';

                    var submitWithToken = function (token) {
                        tokenField.value = token || '';
                        form.dataset.recaptchaDone = '1';
                        // Use requestSubmit when available so HTML validation
                        // and submit handlers behave natively.
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    };

                    if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                        // reCAPTCHA failed to load (network block, adblocker).
                        // Submit anyway; the server decides whether to reject.
                        submitWithToken('');

                        return;
                    }

                    grecaptcha.ready(function () {
                        grecaptcha.execute(SITE_KEY, { action: action })
                            .then(submitWithToken)
                            .catch(function () {
                                submitWithToken('');
                            });
                    });
                }, true);
            })();
        </script>
    @endonce
@endif
