function visibleControl(field) {
    return [...field.querySelectorAll('input, select, textarea, .select2-selection')]
        .find((element) => element.type !== 'hidden' && element.offsetParent !== null);
}

function fieldControl(key) {
    const name = key.replace(/\.([^.]+)/g, '[$1]');
    const control = document.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
    const field = control?.closest('.panel-field');

    return (field && visibleControl(field)) ?? control;
}

function focusControl(control) {
    if (! control) {
        return;
    }

    control.focus({ preventScroll: true });
    control.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

export function bindErrorSummary(root = document) {
    const summary = root.querySelector('[data-error-summary]');

    if (! summary || summary.dataset.errorSummaryBound === '1') {
        return;
    }

    summary.dataset.errorSummaryBound = '1';
    summary.querySelectorAll('[data-error-field]').forEach((button) => {
        button.addEventListener('click', () => focusControl(fieldControl(button.dataset.errorField)));
    });

    const first = document.querySelector('.panel-field-invalid');

    (first && visibleControl(first))?.focus({ preventScroll: true });
}
