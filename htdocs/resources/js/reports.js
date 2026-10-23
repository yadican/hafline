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

// Cari türü değişince firma listesi o role göre dolar.
function bindCompanyLedgerParties(form) {
    const raw = form.getAttribute('data-company-ledger-parties');

    if (! raw) {
        return;
    }

    let maps;

    try {
        maps = JSON.parse(raw);
    } catch {
        return;
    }

    const role = form.querySelector('[data-company-ledger-role]');
    const party = form.querySelector('[data-company-ledger-party]');

    if (! role || ! party) {
        return;
    }

    const fill = () => {
        const items = maps[role.value] || {};
        const keep = party.value;
        const placeholder = party.dataset.placeholder || 'Firma seçin';
        const $ = window.jQuery;

        party.replaceChildren(
            new Option(placeholder, ''),
            ...Object.entries(items).map(([id, name]) => new Option(name, id)),
        );
        party.value = Object.prototype.hasOwnProperty.call(items, keep) ? keep : '';

        if ($ && $(party).hasClass('select2-hidden-accessible')) {
            $(party).trigger('change');
        } else {
            party.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    role.addEventListener('change', fill);

    const $ = window.jQuery;

    if ($ && $(role).hasClass('select2-hidden-accessible')) {
        $(role).on('select2:select select2:clear', fill);
    }
}

onReady(() => {
    document.querySelectorAll('form[data-report-search]').forEach((form) => {
        bindSearch(form);
        bindPeriodToggle(form);
        bindMonthlyVoucherRoutes(form);
        bindCompanyLedgerParties(form);
    });
    document.querySelectorAll('[data-report-print]').forEach(bindPrint);
});
