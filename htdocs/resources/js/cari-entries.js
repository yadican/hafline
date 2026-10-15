import { getJson, money, normalizePlate, onReady } from './entry-support.js';

const PARTY_KINDS = ['supplier', 'customer', 'fuel', 'market', 'owner_payment'];
const PLATE_KINDS = ['vehicle_payment', 'vehicle_receivable'];

function amount(value) {
    return `${money.format(Math.abs(value))} TL`;
}

function tone(value) {
    if (value > 0.005) {
        return 'debit';
    }

    if (value < -0.005) {
        return 'credit';
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

// Borç kırmızı, alacak yeşil; bakiye hangi taraf ağır basıyorsa o rengi alır.
function renderSummary(element, state, info) {
    element.replaceChildren();
    element.classList.remove('is-idle', 'is-err');

    if (state !== 'ok') {
        element.classList.add(state === 'err' ? 'is-err' : 'is-idle');
        element.textContent = info;

        return;
    }

    const name = document.createElement('div');
    const balance = Number(info.balance);
    const balanceTone = tone(balance);
    const balanceText = balanceTone === 'debit'
        ? `${amount(balance)} borçlu`
        : (balanceTone === 'credit' ? `${amount(balance)} alacaklı` : amount(balance));

    name.className = 'panel-cari-summary-name';
    name.textContent = info.name;
    element.append(
        name,
        line('Borç', amount(info.debit), Number(info.debit) > 0.005 ? 'debit' : 'zero'),
        line('Alacak', amount(info.credit), Number(info.credit) > 0.005 ? 'credit' : 'zero'),
        line('Bakiye', balanceText, balanceTone, true),
    );
}

// Seçilen firmanın cari borç, alacak ve bakiyesini formun altında gösterir.
function bindParty(form) {
    const selects = form.querySelectorAll('[data-cari-party]');
    const hint = form.querySelector('[data-cari-party-status]');
    const kind = form.querySelector('#kind');

    if (selects.length === 0 || ! hint || ! kind || ! form.dataset.partyUrl) {
        return;
    }

    let request = 0;

    const active = () => [...selects].find((select) => ! select.disabled && PARTY_KINDS.includes(kind.value));

    const refresh = async () => {
        const current = ++request;
        const select = active();

        if (! select?.value) {
            renderSummary(hint, 'idle', 'Firma veya kişi seçilince cari durumu gelir');

            return;
        }

        try {
            const info = await getJson(form.dataset.partyUrl.replace('__ID__', select.value), {});

            if (current !== request) {
                return;
            }

            renderSummary(hint, 'ok', info);
        } catch {
            if (current === request) {
                renderSummary(hint, 'err', 'Cari durumu alınamadı');
            }
        }
    };

    selects.forEach((select) => select.addEventListener('change', refresh));
    // Alpine, tür değişince diğer firmayı bir sonraki karede kapatır.
    kind.addEventListener('change', () => requestAnimationFrame(() => refresh()));

    refresh();
}

// Plaka ödeme ve plaka alacak, o tarihteki araç sahibinin carisine yazılır.
function bindPlate(form) {
    const plate = form.querySelector('#plate');
    const date = form.querySelector('[name="occurred_on"]');
    const hint = form.querySelector('[data-cari-plate-status]');
    const kind = form.querySelector('#kind');

    if (! plate || ! date || ! hint || ! kind || ! form.dataset.plateUrl) {
        return;
    }

    let request = 0;
    let timer;

    const refresh = async () => {
        const current = ++request;

        if (! PLATE_KINDS.includes(kind.value)) {
            return;
        }

        if (normalizePlate(plate.value).length < 2 || date.value === '') {
            renderSummary(hint, 'idle', 'Plaka girilince araç sahibinin cari durumu gelir');

            return;
        }

        try {
            const info = await getJson(form.dataset.plateUrl, { plate: plate.value, date: date.value });

            if (current !== request) {
                return;
            }

            renderSummary(hint, 'ok', { ...info, name: `${info.plate} · ${info.name}` });
        } catch (error) {
            if (current === request) {
                renderSummary(hint, 'err', error.payload?.message || 'Cari durumu alınamadı');
            }
        }
    };

    const later = () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 350);
    };

    plate.addEventListener('input', later);
    plate.addEventListener('autocomplete:select', refresh);
    date.addEventListener('change', refresh);
    kind.addEventListener('change', () => requestAnimationFrame(() => refresh()));

    refresh();
}

onReady(() => {
    document.querySelectorAll('form[data-cari-entry]').forEach((form) => {
        bindParty(form);
        bindPlate(form);
    });
});
