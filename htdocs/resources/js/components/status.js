const STATUS_ICONS = {
    idle: 'fa-solid fa-circle',
    ok: 'fa-solid fa-circle-check',
    err: 'fa-solid fa-circle-xmark',
};

export function setStatus(element, state, message) {
    const status = element.querySelector('.panel-field-status');
    const text = element.querySelector('[data-status-text]');

    element.dataset.idleText ??= text.textContent;
    element.classList.remove('is-ok', 'is-err');
    status.className = `panel-field-status is-${state}`;
    status.querySelector('i').className = STATUS_ICONS[state];

    if (state !== 'idle') {
        element.classList.add(`is-${state}`);
    }

    text.textContent = message ?? element.dataset.idleText;
}
