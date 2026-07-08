@once
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ route('webauthn.js') }}" type="application/javascript"></script>
@endonce

@php($recaptchaSiteKey = config('services.recaptcha.site_key'))
<input type="hidden" id="webauthn-register-recaptcha" value="">

<div class="bg-bg-card border border-border-default rounded-2xl p-6 md:p-8 mt-6">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-full bg-gold-400/10 flex items-center justify-center text-gold-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A19.874 19.874 0 0012 21.5c4.307 0 8.17-1.34 11.248-3.598M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 7a2 2 0 100-4 2 2 0 000 4z"/>
            </svg>
        </div>
        <h2 class="font-semibold text-text-primary text-lg">{{ __('profile.biometric_security') }}</h2>
    </div>
    <p class="text-sm text-text-secondary mb-6">{{ __('profile.biometric_security_desc') }}</p>

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <input type="text" id="passkey-alias" placeholder="{{ __('profile.passkey_name_placeholder') }}"
            class="flex-1 bg-bg-input border border-border-default rounded-xl px-4 py-3 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none transition-colors">
        <button type="button" id="passkey-add-btn" class="btn-primary px-5 py-3 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
            {{ __('profile.add_passkey') }}
        </button>
    </div>

    <div id="passkey-list" class="space-y-3">
        <div class="text-sm text-text-muted">{{ __('profile.passkey_no_credentials') }}</div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        'use strict';

        var addBtn = document.getElementById('passkey-add-btn');
        var aliasInput = document.getElementById('passkey-alias');
        var listEl = document.getElementById('passkey-list');
        var tokenField = document.getElementById('webauthn-register-recaptcha');
        var siteKey = @json($recaptchaSiteKey);
        var notSupportedMessage = @json(__('profile.passkey_not_supported'));
        var errorMessage = @json(__('profile.passkey_error'));
        var removeConfirm = @json(__('profile.remove_confirm'));
        var noCredentials = @json(__('profile.passkey_no_credentials'));
        var addedLabel = @json(__('profile.added'));

        if (!addBtn || !aliasInput || !listEl) {
            return;
        }

        if (typeof WebAuthn === 'undefined' || !WebAuthn.supportsWebAuthn()) {
            addBtn.disabled = true;
            addBtn.title = notSupportedMessage;
            return;
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function showError(message) {
            alert(message || errorMessage);
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(iso) {
            if (!iso) return '-';
            try {
                return new Date(iso).toLocaleDateString();
            } catch (e) {
                return iso;
            }
        }

        function withRecaptchaToken(callback) {
            if (!siteKey || typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                callback(tokenField ? tokenField.value : '');
                return;
            }

            grecaptcha.ready(function () {
                grecaptcha.execute(siteKey, { action: 'webauthn_register' })
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

        function renderCredentials(credentials) {
            listEl.innerHTML = '';

            if (!credentials || credentials.length === 0) {
                listEl.innerHTML = '<div class="text-sm text-text-muted">' + escapeHtml(noCredentials) + '</div>';
                return;
            }

            credentials.forEach(function (credential) {
                var item = document.createElement('div');
                item.className = 'flex items-center justify-between p-4 bg-bg-section rounded-xl border border-border-default';

                var left = document.createElement('div');
                left.innerHTML = '<p class="font-medium text-text-primary">' + escapeHtml(credential.alias || credential.id) + '</p>' +
                    '<p class="text-xs text-text-muted mt-1">' + escapeHtml(credential.origin || '-') + ' &middot; ' + escapeHtml(addedLabel) + ' ' + escapeHtml(formatDate(credential.created_at)) + '</p>';

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'text-sm text-red-400 hover:text-red-300 font-medium';
                removeBtn.textContent = @json(__('profile.remove'));
                removeBtn.addEventListener('click', function () {
                    revokeCredential(credential.id, item);
                });

                item.appendChild(left);
                item.appendChild(removeBtn);
                listEl.appendChild(item);
            });
        }

        function loadCredentials() {
            fetch('{{ route('webauthn.passkeys.index') }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (response) { return response.json(); })
                .then(function (data) { renderCredentials(data.credentials || []); })
                .catch(function () { renderCredentials([]); });
        }

        function revokeCredential(id, element) {
            if (!confirm(removeConfirm)) {
                return;
            }

            fetch('{{ route('webauthn.passkeys.destroy', '__ID__') }}'.replace('__ID__', encodeURIComponent(id)), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Failed');
                    element.remove();
                    if (listEl.children.length === 0) {
                        renderCredentials([]);
                    }
                })
                .catch(function () { showError(); });
        }

        addBtn.addEventListener('click', function () {
            var alias = aliasInput.value.trim();

            addBtn.disabled = true;
            addBtn.classList.add('opacity-75');

            withRecaptchaToken(function (token) {
                var webauthn = new WebAuthn({}, {}, false);

                webauthn.register({ alias: alias, 'g-recaptcha-response': token }, {})
                    .then(function () {
                        aliasInput.value = '';
                        loadCredentials();
                    })
                    .catch(function () { showError(); })
                    .finally(function () {
                        addBtn.disabled = false;
                        addBtn.classList.remove('opacity-75');
                    });
            });
        });

        loadCredentials();
    })();
</script>
