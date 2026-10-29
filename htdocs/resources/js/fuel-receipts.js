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

// Yakıt yeri değişince fiyatlar yakıt yerinin kartından gelir ve tek şantiyesi varsa o seçilir.
// Saha pompasında alış fiyatı yoktur (dolumda tedarikçiye yazıldı). Düzenlemede kayıtlı fiyatlar,
// yakıt yeri değişmedikçe korunur.
function bindStation(form) {
    const station = form.querySelector('[data-fuel-station]');
    const hint = form.querySelector('[data-fuel-station-hint]');
    const site = form.querySelector('[data-fuel-site]');
    const quantity = form.querySelector('[data-fuel-quantity]');
    const price = form.querySelector('[data-fuel-price]');
    const purchase = form.querySelector('[data-fuel-purchase-price]');
    const purchaseField = form.querySelector('[data-fuel-purchase-field]');
    const costLabel = form.querySelector('[data-fuel-cost-label]');
    const costNote = form.querySelector('[data-fuel-cost-note]');
    const cost = form.querySelector('[data-fuel-cost]');
    const sale = form.querySelector('[data-fuel-sale]');
    const marginRow = form.querySelector('[data-fuel-margin-row]');
    const margin = form.querySelector('[data-fuel-margin]');

    if (! station || ! hint || ! price || ! quantity || ! cost || ! sale || ! margin || ! form.dataset.stationUrl) {
        return;
    }

    let request = 0;
    let tank = false;

    const line = (litres, rate) => `${formatQuantity(litres)} lt × ${money.format(rate)} = ₺${money.format(litres * rate)}`;

    const renderTotal = () => {
        const litres = parseNumber(quantity.value);
        const hasLitres = quantity.value !== '' && litres > 0;
        const hasSale = hasLitres && price.value !== '';
        const hasCost = hasLitres && ! tank && Boolean(purchase?.value);

        sale.textContent = hasSale ? line(litres, parseNumber(price.value)) : '—';

        if (tank) {
            costLabel.textContent = 'SAHA POMPASI:';
            cost.textContent = hasLitres ? `${formatQuantity(litres)} lt stoktan düşer` : '—';
            costNote.textContent = 'Yakıt bizim; cariye alacak yazılmaz, maliyet pompa stoğundan gelir';
        } else {
            costLabel.textContent = 'İSTASYONA ALACAK:';
            cost.textContent = hasCost ? line(litres, parseNumber(purchase.value)) : '—';
            costNote.textContent = 'Litre × alış fiyatı istasyonun carisine alacak yazılır';
        }

        marginRow.hidden = tank;
        margin.textContent = hasSale && hasCost
            ? `₺${money.format(litres * parseNumber(price.value) - litres * parseNumber(purchase.value))}`
            : '—';
    };

    const refresh = async (keepPrice) => {
        const current = ++request;

        if (! station.value) {
            setStatus(hint, 'idle', 'Seçilince yakıt yerinin fiyatları gelir');
            renderTotal();

            return;
        }

        try {
            const info = await getJson(form.dataset.stationUrl.replace('__ID__', station.value), {});

            if (current !== request) {
                return;
            }

            tank = Boolean(info.tank);
            setStatus(hint, 'ok', tank ? 'Saha pompası; stoktan düşer' : `Akaryakıt firması: ${info.party ?? ''}`);

            if (! keepPrice || price.value === '') {
                const rate = Number(info.unit_price);

                price.value = rate > 0 ? rate.toFixed(4) : '';
            }

            if (purchaseField) {
                purchaseField.hidden = tank;
            }

            if (purchase) {
                purchase.disabled = tank;

                if (tank) {
                    purchase.value = '';
                } else if (! keepPrice || purchase.value === '') {
                    const cost = Number(info.purchase_price);

                    purchase.value = cost > 0 ? cost.toFixed(4) : '';
                }
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
    purchase?.addEventListener('input', renderTotal);

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
