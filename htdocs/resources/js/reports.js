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

// Yön (şantiye↔döküm) değişince güzergah etiket sırası anında değişir; aynı id korunur.
function bindMonthlyVoucherRoutes(form) {
    const raw = form.getAttribute('data-monthly-voucher-routes');

    if (! raw) {
        return;
    }

    let maps;

    try {
        maps = JSON.parse(raw);
    } catch {
        return;
    }

    const orientation = form.querySelector('[data-monthly-voucher-orientation]');
    const route = form.querySelector('[data-monthly-voucher-route]');

    if (! orientation || ! route) {
        return;
    }

    const fill = () => {
        const items = maps[orientation.value] || {};
        const keep = route.value;
        const placeholder = route.dataset.placeholder || 'Güzergah';
        const $ = window.jQuery;

        route.replaceChildren(
            new Option(placeholder, ''),
            ...Object.entries(items).map(([id, name]) => new Option(name, id)),
        );
        route.value = Object.prototype.hasOwnProperty.call(items, keep) ? keep : '';

        if ($ && $(route).hasClass('select2-hidden-accessible')) {
            $(route).trigger('change');
        } else {
            route.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    orientation.addEventListener('change', fill);

    const $ = window.jQuery;

    if ($ && $(orientation).hasClass('select2-hidden-accessible')) {
        $(orientation).on('select2:select select2:clear', fill);
    }
}

onReady(() => {
    document.querySelectorAll('form[data-report-search]').forEach((form) => {
        bindSearch(form);
        bindPeriodToggle(form);
        bindMonthlyVoucherRoutes(form);
    });
    document.querySelectorAll('[data-report-print]').forEach(bindPrint);
});
