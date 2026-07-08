// Cwt Academy Frontend
import './bootstrap';
import '../css/app.css';
import.meta.glob(['../images/**']);

document.addEventListener('DOMContentLoaded', () => {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', (e) => {
            const container = e.target.closest('div');
            if (container && e.target.files.length > 0) {
                const file = e.target.files[0];
                const label = container.querySelector('.text-text-muted');
                if (label) {
                    label.textContent = file.name;
                }
            }
        });
    });

    // Mobile navigation toggle
    const navToggle = document.getElementById('mobile-nav-toggle');
    const navDrawer = document.getElementById('mobile-nav');
    if (navToggle && navDrawer) {
        navToggle.addEventListener('click', () => {
            const expanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!expanded));
            navDrawer.classList.toggle('hidden');
            const icon = navToggle.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = expanded ? 'menu' : 'close';
            }
        });
    }

    // Copy-to-clipboard buttons (e.g. contact phone)
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = button.getAttribute('data-copy-target');
            const text = target ? (document.querySelector(target)?.textContent || '').trim() : '';
            if (!text) return;
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) { /* noop */ }
                document.body.removeChild(ta);
            }
            const labelSpan = button.querySelector('.copy-label') || button;
            const original = labelSpan.dataset.originalLabel || labelSpan.textContent;
            if (!labelSpan.dataset.originalLabel) labelSpan.dataset.originalLabel = original;
            labelSpan.textContent = button.getAttribute('data-copy-success') || 'Copied!';
            setTimeout(() => {
                labelSpan.textContent = labelSpan.dataset.originalLabel || original;
            }, 1800);
        });
    });

    // Lightweight modal handler (data-modal-open, data-modal, data-modal-close)
    const openModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const id = trigger.getAttribute('data-modal-open');
            openModal(document.getElementById(id));
        });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.querySelectorAll('[data-modal-close]').forEach((closer) => {
            closer.addEventListener('click', (e) => {
                e.preventDefault();
                closeModal(modal);
            });
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-modal]:not(.hidden)').forEach(closeModal);
        }
    });

    // Payment proof file picker — show filename + size in the dropzone
    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) return '';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let value = bytes;
        while (value >= 1024 && i < units.length - 1) {
            value /= 1024;
            i += 1;
        }
        return `${value.toFixed(value >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
    };

    document.querySelectorAll('[data-proof-input]').forEach((input) => {
        const dropzone = input.closest('[data-proof-dropzone]');
        if (!dropzone) return;
        const placeholder = dropzone.querySelector('[data-proof-placeholder]');
        const helper = dropzone.querySelector('[data-proof-helper]');
        const originalPlaceholder = placeholder ? placeholder.textContent : '';
        const originalHelper = helper ? helper.textContent : '';

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) {
                if (placeholder) placeholder.textContent = originalPlaceholder;
                if (helper) helper.textContent = originalHelper;
                dropzone.classList.remove('border-gold-400');
                return;
            }
            if (placeholder) placeholder.textContent = file.name;
            if (helper) helper.textContent = formatBytes(file.size);
            dropzone.classList.add('border-gold-400');
        });
    });

    // Password visibility toggle (data-toggle-password="input-id")
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const inputId = btn.getAttribute('data-toggle-password');
            const input = document.getElementById(inputId);
            if (!input) return;
            const openEye = btn.querySelector('.eye-open, [data-eye-open]');
            const closedEye = btn.querySelector('.eye-closed, [data-eye-closed]');
            if (input.type === 'password') {
                input.type = 'text';
                if (openEye) openEye.classList.add('hidden');
                if (closedEye) closedEye.classList.remove('hidden');
            } else {
                input.type = 'password';
                if (openEye) openEye.classList.remove('hidden');
                if (closedEye) closedEye.classList.add('hidden');
            }
        });
    });

    // Password strength meter + requirements
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        const strengthBox = document.getElementById('password-strength');
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        const reqBox = document.getElementById('password-requirements');
        const reqs = {
            length: { el: document.getElementById('req-length'), test: pw => pw.length >= 12 },
            uppercase: { el: document.getElementById('req-uppercase'), test: pw => /[A-Z]/.test(pw) },
            number: { el: document.getElementById('req-number'), test: pw => /[0-9]/.test(pw) },
            special: { el: document.getElementById('req-special'), test: pw => /[^A-Za-z0-9]/.test(pw) },
        };

        const checkStrength = (pw) => {
            let score = 0;
            if (pw.length >= 12) score++;
            if (pw.length >= 16) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[a-z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;
            return score;
        };

        const updateRequirements = (pw) => {
            for (const key in reqs) {
                if (!reqs[key].el) continue;
                const met = reqs[key].test(pw);
                const icon = reqs[key].el.querySelector('.req-icon');
                if (met) {
                    reqs[key].el.classList.add('text-green-500');
                    reqs[key].el.classList.remove('text-text-secondary');
                    if (icon) icon.classList.remove('opacity-0');
                } else {
                    reqs[key].el.classList.remove('text-green-500');
                    reqs[key].el.classList.add('text-text-secondary');
                    if (icon) icon.classList.add('opacity-0');
                }
            }
        };

        const renderStrength = (pw) => {
            if (!strengthBox || !reqBox) return;
            if (pw.length === 0) {
                strengthBox.classList.add('hidden');
                reqBox.classList.add('hidden');
                return;
            }
            strengthBox.classList.remove('hidden');
            reqBox.classList.remove('hidden');
            updateRequirements(pw);
            const score = checkStrength(pw);
            let width = '0%', color = 'bg-red-500', text = 'لاواز', textColor = 'text-red-500';
            if (score <= 2) {
                width = '25%'; color = 'bg-red-500'; text = 'لاواز'; textColor = 'text-red-500';
            } else if (score <= 4) {
                width = '65%'; color = 'bg-blue-500'; text = 'باش'; textColor = 'text-blue-500';
            } else {
                width = '100%'; color = 'bg-green-500'; text = 'بەهێز'; textColor = 'text-green-500';
            }
            if (strengthBar) {
                strengthBar.style.width = width;
                strengthBar.className = 'h-full rounded-full transition-all duration-300 ' + color;
            }
            if (strengthText) {
                strengthText.textContent = text;
                strengthText.className = 'text-xs font-medium ' + textColor;
            }
        };

        passwordInput.addEventListener('input', () => renderStrength(passwordInput.value));
        passwordInput.addEventListener('focus', () => renderStrength(passwordInput.value));

        // Catch delayed browser autofill
        let checks = 0;
        const poll = setInterval(() => {
            renderStrength(passwordInput.value);
            if (++checks >= 10) clearInterval(poll);
        }, 200);
    }

    // Form submit loading states
    document.querySelectorAll('form[data-submit-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const buttons = form.querySelectorAll('button[data-loading-text]');
            buttons.forEach((btn) => {
                btn.disabled = true;
                const textSpan = btn.querySelector('.btn-text');
                const spinner = btn.querySelector('.btn-spinner');
                if (textSpan && btn.dataset.loadingText) {
                    if (!textSpan.dataset.originalText) textSpan.dataset.originalText = textSpan.textContent;
                    textSpan.textContent = btn.dataset.loadingText;
                }
                if (spinner) spinner.classList.remove('hidden');
            });
        });
    });
});

