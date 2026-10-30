import { getJson, money, normalizePlate, onReady } from './entry-support.js';

const PARTY_KINDS = ['supplier', 'customer', 'fuel', 'market', 'owner_payment', 'opening', 'party_transfer'];
const PLATE_KINDS = ['vehicle_payment', 'vehicle_receivable'];
const ACCOUNT_KINDS = ['cash', 'account_transfer'];

// Kasa hareketlerinin ve hesaplar arası virmanın party alanı yok; bu türlerde filtreyi kapat.
function bindSearchToolbar(form) {
    const kind = form.querySelector('[name="kind"]');
    const party = form.querySelector('[name="party"]');

    if (! kind || ! party) {
        return;
    }

    const sync = () => {
        const locked = ACCOUNT_KINDS.includes(kind.value);
        const $ = window.jQuery;

        if ($ && locked) {
            $(party).val(null).trigger('change');
        }

        if ($) {
            $(party).prop('disabled', locked);
        } else {
            party.disabled = locked;
            if (locked) {
                party.value = '';
            }
        }
    };

    kind.addEventListener('change', sync);
    sync();
}

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
        line(info.owner ? `Cari sahibi bakiyesi · ${info.owner}` : 'Bakiye', balanceText, balanceTone, true),
    );

    if (info.plate_balance !== undefined) {
        const plateBalance = Number(info.plate_balance);
        const plateTone = tone(plateBalance);
        const plateText = plateTone === 'debit'
            ? `${amount(plateBalance)} borçlu`
            : (plateTone === 'credit' ? `${amount(plateBalance)} alacaklı` : amount(plateBalance));

        element.append(line(`${info.plate} plakasında`, plateText, plateTone));
    }
}

// Seçilen carinin borç, alacak ve bakiyesini verilen kutuda gösterir; mahsupta başlığa cari rolü eklenir.
function bindPartySummary(form, selects, hint, kinds, idleText, role) {
    const kind = form.querySelector('#kind');

    if (selects.length === 0 || ! hint || ! kind || ! form.dataset.partyUrl) {
        return;
    }

    let request = 0;

    const active = () => [...selects].find((select) => ! select.disabled && kinds.includes(kind.value));

    const refresh = async () => {
        const current = ++request;
        const select = active();

        if (! select?.value) {
            renderSummary(hint, 'idle', idleText);

            return;
        }

        try {
            const info = await getJson(form.dataset.partyUrl.replace('__ID__', select.value), {});

            if (current !== request) {
                return;
            }

            const label = role(kind.value);

            renderSummary(hint, 'ok', label ? { ...info, name: `${label} · ${info.name}` } : info);
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

function bindParty(form) {
    bindPartySummary(
        form,
        form.querySelectorAll('[data-cari-party]'),
        form.querySelector('[data-cari-party-status]'),
        PARTY_KINDS,
        'Firma veya kişi seçilince cari durumu gelir',
        (kind) => (kind === 'party_transfer' ? 'Borçlanacak cari' : null),
    );

    bindPartySummary(
        form,
        form.querySelectorAll('[data-cari-counter-party]'),
        form.querySelector('[data-cari-counter-status]'),
        ['party_transfer'],
        'Alacaklanacak cari seçilince cari durumu gelir',
        () => 'Alacaklanacak cari',
    );
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

            renderSummary(hint, 'ok', { ...info, name: `${info.plate} · ${info.name}`, owner: info.name });
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

    document.querySelectorAll('form[data-cari-entry-search]').forEach(bindSearchToolbar);
});
