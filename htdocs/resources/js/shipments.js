import { setStatus } from './components/status.js';
import { getJson, money, onReady } from './entry-support.js';

function parseNumber(value) {
    const number = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
}

function round2(value) {
    return Math.round(value * 100) / 100;
}

function selectedText(select) {
    return select?.selectedOptions?.[0]?.textContent?.trim() ?? '';
}

// Fiyatlar ton başına, miktar kantardaki net kg: matrah = kg / 1000 × fiyat. KDV matrah üzerinden,
// tevkifat KDV'nin seçilen payıdır. Kırmızı kutu nakliyeci, yeşil kutu müşteri tarafıdır.
function bindShipment(form) {
    const field = (name) => form.querySelector(`[name="${name}"]`);
    const plate = form.querySelector('[data-shipment-plate]');
    const carrierHint = form.querySelector('[data-shipment-carrier]');
    const customer = form.querySelector('[data-shipment-customer]');
    const addresses = form.querySelector('[data-shipment-addresses]');
    const total = form.querySelector('[data-shipment-total]');

    if (! total) {
        return;
    }

    const value = (name) => parseNumber(field(name)?.value);

    const side = (box, price, vatRate, withholding) => {
        const base = round2(value('net_kg') / 1000 * value(price));
        const vat = round2(base * value(vatRate) / 100);
        const withheld = round2(vat * value(withholding));
        const net = round2(base + vat - withheld);
        const target = form.querySelector(`[data-shipment-totals="${box}"]`);
        const set = (key, text) => {
            const element = target?.querySelector(`[data-total="${key}"]`);

            if (element) {
                element.textContent = text;
            }
        };

        set('base', `₺${money.format(base)}`);
        set('vat-rate', `%${value(vatRate)}`);
        set('vat', `+₺${money.format(vat)}`);
        set('withholding-rate', selectedText(field(withholding)) || 'Yok');
        set('withholding', `−₺${money.format(withheld)}`);
        set('net', `₺${money.format(net)}`);

        return base;
    };

    const render = () => {
        const carrierBase = side('carrier', 'carrier_price', 'carrier_vat_rate', 'carrier_withholding_rate');
        const saleBase = side('customer', 'sale_price', 'vat_rate', 'withholding_rate');
        const profit = saleBase - carrierBase - value('driver_amount') - value('commission_amount');

        setStatus(total, value('net_kg') > 0 ? 'ok' : 'idle', `Sefer kârı (KDV hariç): ₺${money.format(profit)}`);
    };

    const loadAddresses = async () => {
        if (! addresses || ! customer?.value || ! form.dataset.addressesUrl) {
            return;
        }

        try {
            const names = await getJson(form.dataset.addressesUrl, { party: customer.value });

            addresses.replaceChildren(...names.map((name) => Object.assign(document.createElement('option'), { value: name })));
        } catch {
            addresses.replaceChildren();
        }
    };

    plate?.addEventListener('autocomplete:select', (event) => {
        const owner = event.detail.item?.meta;

        setStatus(carrierHint, owner ? 'ok' : 'err', owner ? `Nakliyeci: ${owner}` : 'Bu plakanın sahibi yok');
    });
    customer?.addEventListener('change', loadAddresses);
    form.querySelectorAll('[data-shipment-amount]').forEach((input) => input.addEventListener('input', render));
    form.querySelectorAll('[data-shipment-tax]').forEach((input) => input.addEventListener('change', render));

    loadAddresses();
    render();
}

onReady(() => {
    document.querySelectorAll('form[data-shipment-entry]').forEach(bindShipment);
});
