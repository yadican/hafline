import { getJson, money } from '../entry-support.js';

function amount(value) {
    return `${money.format(Math.abs(value))} TL`;
}

// Pozitif bakiye: personele ödenecek (alacak). Negatif: fazla ödeme.
function balanceTone(value) {
    if (value > 0.005) {
        return 'credit';
    }

    if (value < -0.005) {
        return 'debit';
    }

    return 'zero';
}

function line(label, value, rowTone, total = false) {
    const row = document.createElement('div');
    const name = document.createElement('span');
    const figure = document.createElement('span');

    row.className = `panel-cari-summary-row is-${rowTone}${total ? ' is-total' : ''}`;
    name.textContent = label;
    figure.textContent = value;
    row.append(name, figure);

    return row;
}

function renderSummary(element, state, info) {
    element.replaceChildren();
    element.classList.remove('is-idle', 'is-err');

    if (state !== 'ok') {
        element.classList.add(state === 'err' ? 'is-err' : 'is-idle');
        element.textContent = info;

        return;
    }

    const balance = Number(info.balance);
    const tone = balanceTone(balance);
    const balanceText = tone === 'credit'
        ? `${amount(balance)} alacağı var`
        : (tone === 'debit' ? `${amount(balance)} fazla ödenmiş` : amount(balance));

    const name = document.createElement('div');
    name.className = 'panel-cari-summary-name';
    name.textContent = info.name;

    element.append(
        name,
        line('Devreden', amount(info.carry), balanceTone(Number(info.carry))),
        line('Maaş', amount(info.earned), Number(info.earned) > 0.005 ? 'credit' : 'zero'),
        line('Prim', amount(info.bonus), Number(info.bonus) > 0.005 ? 'credit' : 'zero'),
        line('Ödenen', amount(info.paid), Number(info.paid) > 0.005 ? 'debit' : 'zero'),
        line('Bakiye', balanceText, tone, true),
    );
}

function periodParams(form) {
    const year = form.querySelector('[name="year"]');
    const month = form.querySelector('[name="month"]');

    if (year?.value && month?.value) {
        return { year: year.value, month: month.value };
    }

    const occurred = form.querySelector('[name="occurred_on"]');

    if (occurred?.value) {
        const [y, m] = occurred.value.split('-');

        if (y && m) {
            return { year: y, month: String(Number(m)) };
        }
    }

    const now = new Date();

    return { year: String(now.getFullYear()), month: String(now.getMonth() + 1) };
}

function bindForm(form) {
    if (form.dataset.employeeBalanceBound === '1') {
        return;
    }

    const select = form.querySelector('[data-employee-balance]');
    const hint = form.querySelector('[data-employee-balance-status]');

    if (! select || ! hint || ! form.dataset.balanceUrl) {
        return;
    }

    form.dataset.employeeBalanceBound = '1';

    let request = 0;

    const refresh = async () => {
        const current = ++request;

        if (! select.value) {
            renderSummary(hint, 'idle', 'Personel seçilince maaş / prim / ödeme bakiyesi gelir');

            return;
        }

        try {
            const info = await getJson(
                form.dataset.balanceUrl.replace('__ID__', select.value),
                periodParams(form),
            );

            if (current !== request) {
                return;
            }

            renderSummary(hint, 'ok', info);
        } catch {
            if (current === request) {
                renderSummary(hint, 'err', 'Personel bakiyesi alınamadı');
            }
        }
    };

    select.addEventListener('change', refresh);
    form.querySelectorAll('[name="year"], [name="month"], [name="occurred_on"]').forEach((field) => {
        field.addEventListener('change', refresh);
    });
    refresh();
}

export function bindEmployeeBalances(root = document) {
    root.querySelectorAll('form[data-employee-balance-form]').forEach(bindForm);
}
