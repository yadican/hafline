import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { submitForm } from './submit-guard.js';

const DIALOG_TONES = {
    danger: { icon: 'fa-solid fa-triangle-exclamation', button: 'fa-solid fa-check' },
    info: { icon: 'fa-solid fa-circle-info', button: 'fa-solid fa-check' },
    success: { icon: 'fa-solid fa-check', button: 'fa-solid fa-check' },
};

const DIALOG_TONE_ALIASES = { ok: 'success', err: 'danger', error: 'danger', warn: 'danger' };

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function dialogTone(value, fallback) {
    const tone = DIALOG_TONE_ALIASES[value] ?? value;

    return DIALOG_TONES[tone] ? tone : fallback;
}

function dialog({ tone, icon, title, text, button, buttonIcon, cancel }) {
    const style = DIALOG_TONES[tone];

    return Swal.fire({
        customClass: {
            container: 'panel-swal-backdrop',
            popup: `panel-swal panel-swal-${tone}`,
            htmlContainer: 'panel-swal-body',
            confirmButton: 'panel-swal-confirm',
            cancelButton: 'panel-swal-cancel',
            actions: 'panel-swal-actions',
        },
        buttonsStyling: false,
        icon: false,
        title: false,
        html: `<div class="panel-swal-mark"><i class="${escapeHtml(icon || style.icon)}" aria-hidden="true"></i></div><div class="panel-swal-heading">${escapeHtml(title)}</div>${text ? `<p class="panel-swal-text">${escapeHtml(text)}</p>` : ''}`,
        showCancelButton: Boolean(cancel),
        confirmButtonText: `<i class="${escapeHtml(buttonIcon || style.button)}" aria-hidden="true"></i> ${escapeHtml(button)}`,
        cancelButtonText: '<i class="fa-solid fa-xmark" aria-hidden="true"></i> Vazgeç',
        reverseButtons: true,
        focusCancel: Boolean(cancel),
    }).then((result) => Boolean(result.isConfirmed));
}

export function confirm(options = {}) {
    return dialog({
        tone: dialogTone(options.tone, 'danger'),
        icon: options.icon,
        title: options.title || 'Emin misiniz?',
        text: options.text || '',
        button: options.button || 'Tamam',
        buttonIcon: options.buttonIcon,
        cancel: true,
    });
}

export function alert(options = {}) {
    const settings = typeof options === 'string' ? { text: options } : options;
    const tone = dialogTone(settings.type ?? settings.tone, 'info');

    return dialog({
        tone,
        icon: settings.icon,
        title: settings.title || { danger: 'Hata', success: 'Başarılı', info: 'Bilgi' }[tone],
        text: settings.text || '',
        button: settings.button || 'Tamam',
        buttonIcon: settings.buttonIcon,
        cancel: false,
    });
}

export function bindConfirms(root = document) {
    root.querySelectorAll('form.js-confirm').forEach((form) => {
        if (form.dataset.confirmBound === '1') {
            return;
        }

        form.dataset.confirmBound = '1';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const confirmed = await confirm({
                title: form.dataset.confirmTitle,
                text: form.dataset.confirmText,
                button: form.dataset.confirmButton || 'Sil',
                icon: form.dataset.confirmIcon || 'fa-solid fa-trash-can',
                buttonIcon: form.dataset.confirmButtonIcon || 'fa-solid fa-trash-can',
                tone: form.dataset.confirmTone,
            });

            if (confirmed) {
                submitForm(form);
            }
        });
    });
}

/**
 * Cari role checkboxes that open domain cards (e.g. Personel) ask for confirmation first.
 */
export function bindRoleAssignmentConfirms(root = document) {
    root.querySelectorAll('input[type="checkbox"][data-role-confirm]').forEach((input) => {
        if (input.dataset.roleConfirmBound === '1') {
            return;
        }

        input.dataset.roleConfirmBound = '1';
        input.addEventListener('change', async () => {
            if (! input.checked) {
                return;
            }

            const confirmed = await confirm({
                title: input.dataset.roleConfirmTitle || 'Emin misiniz?',
                text: input.dataset.roleConfirmText || '',
                button: input.dataset.roleConfirmButton || 'Evet, aç',
                icon: input.dataset.roleConfirmIcon || 'fa-solid fa-triangle-exclamation',
                buttonIcon: 'fa-solid fa-check',
                tone: 'danger',
            });

            if (! confirmed) {
                input.checked = false;
            }
        });
    });
}
