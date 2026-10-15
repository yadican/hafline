import { setStatus } from './components/status.js';
import { bindPrint, bindSearch, getJson, money, normalizePlate, onReady } from './entry-support.js';

function parseNumber(value) {
    const number = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
}

function formatQuantity(quantity) {
    return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 }).format(quantity);
}

function bindPlate(form) {
    const input = form.querySelector('[data-fuel-plate]');
    const hint = form.querySelector('[data-fuel-plate-hint]');

    if (! input || ! hint) {
        return;
    }

    let vehicles = [];

    const render = () => {
        const key = normalizePlate(input.value);
        const match = vehicles.find((vehicle) => normalizePlate(vehicle.value) === key);

        if (match) {
            setStatus(hint, 'ok', match.meta || 'Kayıtlı araç');
        } else if (key.length >= 2) {
            setStatus(hint, 'err', 'Kayıtlı araç bulunamadı');
        } else {
            setStatus(hint, 'idle');
        }
    };

    input.addEventListener('autocomplete:results', (event) => {
        vehicles = event.detail.items;
        render();
    });
    input.addEventListener('autocomplete:select', render);
    input.addEventListener('autocomplete:error', () => setStatus(hint, 'err', 'Araç listesi alınamadı'));
}

function setSelect(select, value) {
    select.value = value;

    if (window.jQuery) {
        window.jQuery(select).val(value).trigger('change');
    }
}

// Yakıt yeri değişince birim fiyat istasyonun fiyatından gelir ve tek şantiyesi varsa o seçilir.
// Düzenlemede kayıtlı fiyat, yer değişmedikçe korunur.
function bindStation(form) {
    const station = form.querySelector('[data-fuel-station]');
    const hint = form.querySelector('[data-fuel-station-hint]');
    const site = form.querySelector('[data-fuel-site]');
    const quantity = form.querySelector('[data-fuel-quantity]');
    const price = form.querySelector('[data-fuel-price]');
    const total = form.querySelector('[data-fuel-total]');

    if (! station || ! hint || ! price || ! total || ! quantity || ! form.dataset.stationUrl) {
        return;
    }

    let request = 0;

    const renderTotal = () => {
        if (price.value === '' || quantity.value === '') {
            setStatus(total, 'idle', 'Litre × birim fiyat araç sahibine borç yazılır');

            return;
        }

        const litres = parseNumber(quantity.value);
        const rate = parseNumber(price.value);

        setStatus(total, 'ok', `${formatQuantity(litres)} lt × ${money.format(rate)} TL = ${money.format(litres * rate)} TL borç`);
    };

    const refresh = async (keepPrice) => {
        const current = ++request;

        if (! station.value) {
            setStatus(hint, 'idle', 'Seçilince istasyonun birim fiyatı gelir');
            renderTotal();

            return;
        }

        try {
            const info = await getJson(form.dataset.stationUrl.replace('__ID__', station.value), {});

            if (current !== request) {
                return;
            }

            setStatus(hint, 'ok', info.party ? `Akaryakıt firması: ${info.party}` : 'Yakıt yeri seçildi');

            if (! keepPrice || price.value === '') {
                const rate = Number(info.unit_price);

                price.value = rate > 0 ? rate.toFixed(4) : '';
            }

            const sites = info.sites ?? [];
            const ids = sites.map((item) => item.id);

            if (site && sites.length === 1) {
                setSelect(site, ids[0]);
            } else if (site && site.value && ids.length > 0 && ! ids.includes(site.value)) {
                setSelect(site, '');
            }

            renderTotal();
        } catch {
            if (current === request) {
                setStatus(hint, 'err', 'Yakıt yeri bilgisi alınamadı');
            }
        }
    };

    station.addEventListener('change', () => refresh(false));
    quantity.addEventListener('input', renderTotal);
    price.addEventListener('input', renderTotal);

    refresh(form.hasAttribute('data-keep-price'));
}

onReady(() => {
    document.querySelectorAll('form[data-fuel-entry]').forEach((form) => {
        bindPlate(form);
        bindStation(form);
    });

    document.querySelectorAll('form[data-entry-search]').forEach(bindSearch);
    document.querySelectorAll('[data-entry-print]').forEach(bindPrint);
});
