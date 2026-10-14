import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Alpine = Alpine;
window.Swal = Swal;

Alpine.start();

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

window.panelToast = function panelToast(message, type = 'ok') {
    const text = String(message ?? '').trim();

    if (text === '') {
        return;
    }

    let host = document.getElementById('panel-toast-host');

    if (! host) {
        host = document.createElement('div');
        host.id = 'panel-toast-host';
        host.className = 'panel-toast-host';
        document.body.appendChild(host);
    }

    const icon = type === 'err'
        ? 'fa-solid fa-circle-exclamation'
        : (type === 'info' ? 'fa-solid fa-circle-info' : 'fa-solid fa-circle-check');
    const toast = document.createElement('div');

    toast.className = `panel-toast panel-toast-${type}`;
    toast.innerHTML = `<span class="panel-toast-icon"><i class="${icon}" aria-hidden="true"></i></span><span class="panel-toast-msg"></span><button type="button" class="panel-toast-close" aria-label="Kapat"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
    toast.querySelector('.panel-toast-msg').textContent = text;
    host.appendChild(toast);
    requestAnimationFrame(() => {
        toast.classList.add('panel-toast-in');
    });

    const close = () => {
        toast.classList.remove('panel-toast-in');
        toast.classList.add('panel-toast-out');
        setTimeout(() => toast.remove(), 220);
    };

    toast.querySelector('.panel-toast-close').addEventListener('click', close);
    setTimeout(close, type === 'err' ? 4500 : 2800);
};

window.panelConfirm = function panelConfirm(options = {}) {
    const title = options.title || 'Emin misiniz?';
    const text = options.text || '';
    const button = options.button || 'Tamam';
    const icon = options.icon || 'fa-solid fa-triangle-exclamation';
    const tone = options.tone === 'ok' ? ' panel-swal-ok' : '';

    return Swal.fire({
        customClass: {
            popup: `panel-swal${tone}`,
            htmlContainer: 'panel-swal-body',
            confirmButton: 'panel-swal-confirm',
            cancelButton: 'panel-swal-cancel',
            actions: 'panel-swal-actions',
        },
        buttonsStyling: false,
        icon: false,
        title: false,
        html: `<div class="panel-swal-mark"><i class="${escapeHtml(icon)}" aria-hidden="true"></i></div><div class="panel-swal-heading">${escapeHtml(title)}</div><p class="panel-swal-text">${escapeHtml(text)}</p>`,
        showCancelButton: true,
        confirmButtonText: button,
        cancelButtonText: 'Vazgeç',
        reverseButtons: true,
        focusCancel: true,
    }).then((result) => Boolean(result.isConfirmed));
};

function bindConfirms(root = document) {
    root.querySelectorAll('form.js-confirm').forEach((form) => {
        if (form.dataset.confirmBound === '1') {
            return;
        }

        form.dataset.confirmBound = '1';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const confirmed = await window.panelConfirm({
                title: form.dataset.confirmTitle,
                text: form.dataset.confirmText,
                button: form.dataset.confirmButton || 'Sil',
                icon: form.dataset.confirmIcon || 'fa-solid fa-trash-can',
                tone: form.dataset.confirmTone,
            });

            if (confirmed) {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });
}

function bootPanel() {
    bindConfirms();

    const flashNode = document.getElementById('panel-flash');

    if (! flashNode) {
        return;
    }

    const flash = JSON.parse(flashNode.textContent || '{}');

    if (flash.success) {
        window.panelToast(flash.success, 'ok');
    }

    if (flash.error) {
        window.panelToast(flash.error, 'err');
    }

    if (flash.info) {
        window.panelToast(flash.info, 'info');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPanel);
} else {
    bootPanel();
}
