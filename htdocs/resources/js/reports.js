import { bindPrint, bindSearch, onReady } from './entry-support.js';

function bindPeriodToggle(form) {
    const period = form.querySelector('[name="period"]');

    if (! period) {
        return;
    }

    const sync = () => {
        const mode = period.value || 'month';

        form.querySelectorAll('[data-period-fields]').forEach((group) => {
            const active = group.dataset.periodFields === mode;

            group.hidden = ! active;
            group.querySelectorAll('input, select').forEach((field) => {
                field.disabled = ! active;
            });
        });
    };

    period.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    sync();
}

onReady(() => {
    document.querySelectorAll('form[data-report-search]').forEach((form) => {
        bindSearch(form);
        bindPeriodToggle(form);
    });
    document.querySelectorAll('[data-report-print]').forEach(bindPrint);
});
