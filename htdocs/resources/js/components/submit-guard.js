const TOKEN_FIELD = '_submit_token';

function newToken() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function isGuarded(form) {
    return (form.getAttribute('method') ?? '').toLowerCase() === 'post' && ! form.hasAttribute('data-no-block');
}

function stampToken(form) {
    let input = form.querySelector(`input[name="${TOKEN_FIELD}"]`);

    if (! input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = TOKEN_FIELD;
        form.appendChild(input);
    }

    input.value = newToken();
}

export function blockPage(message = 'İşleniyor…') {
    let overlay = document.getElementById('panel-block');

    if (! overlay) {
        overlay = document.createElement('div');
        overlay.id = 'panel-block';
        overlay.className = 'panel-block';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = '<div class="panel-block-box"><span class="ajax-spinner" aria-hidden="true"></span><span class="panel-block-text"></span></div>';
        document.body.appendChild(overlay);
    }

    overlay.querySelector('.panel-block-text').textContent = message;
    overlay.classList.add('is-active');
    document.body.setAttribute('aria-busy', 'true');
}

export function unblockPage() {
    document.getElementById('panel-block')?.classList.remove('is-active');
    document.body.removeAttribute('aria-busy');
}

export function submitForm(form) {
    if (form.dataset.submitting === '1') {
        return;
    }

    form.dataset.submitting = '1';
    blockPage(form.dataset.blockMessage);
    HTMLFormElement.prototype.submit.call(form);
}

export function bindSubmitGuard(root = document) {
    root.querySelectorAll('form').forEach((form) => {
        if (isGuarded(form)) {
            stampToken(form);
        }
    });

    if (document.documentElement.dataset.submitGuardBound === '1') {
        return;
    }

    document.documentElement.dataset.submitGuardBound = '1';

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || ! isGuarded(form) || event.defaultPrevented) {
            return;
        }

        if (form.dataset.submitting === '1') {
            event.preventDefault();

            return;
        }

        form.dataset.submitting = '1';
        blockPage(form.dataset.blockMessage);
    });

    window.addEventListener('pageshow', (event) => {
        if (! event.persisted) {
            return;
        }

        unblockPage();
        document.querySelectorAll('form').forEach((form) => {
            delete form.dataset.submitting;

            if (isGuarded(form)) {
                stampToken(form);
            }
        });
    });
}
