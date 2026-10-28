import { setStatus } from './components/status.js';
import { getJson, money, onReady } from './entry-support.js';

function parseNumber(value) {
    const number = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
}

function round2(value) {
    return Math.round(value * 100) / 100;
}

// Fiyatlar ton başına, miktar kantardaki net kg: matrah = kg / 1000 × fiyat. KDV matrah üzerinden,
// tevkifat KDV'nin seçilen payıdır.
function bindShipment(form) {
    const field = (name) => form.querySelector(`[name="${name}"]`);
    const carrier = form.querySelector('[data-shipment-carrier]');
    const plate = form.querySelector('[data-shipment-plate]');
    const customer = form.querySelector('[data-shipment-customer]');
    const addresses = form.querySelector('[data-shipment-addresses]');
    const carrierTotal = form.querySelector('[data-shipment-carrier-total]');
    const customerTotal = form.querySelector('[data-shipment-customer-total]');
    const total = form.querySelector('[data-shipment-total]');

    if (! carrierTotal || ! customerTotal || ! total) {
        return;
    }

    const value = (name) => parseNumber(field(name)?.value);

    const side = (price, vatRate, withholding) => {
        const base = round2(value('net_kg') / 1000 * value(price));
        const vat = round2(base * value(vatRate) / 100);
        const withheld = round2(vat * value(withholding));

        return { base, vat, withheld, net: base + vat - withheld };
    };

    const describe = (amounts, label) => `Matrah ${money.format(amounts.base)} TL`
        + (amounts.vat ? ` + KDV ${money.format(amounts.vat)}` : '')
        + (amounts.withheld ? ` − tevkifat ${money.format(amounts.withheld)}` : '')
        + ` · ${label} ${money.format(amounts.net)} TL`;

    const render = () => {
        if (value('net_kg') <= 0) {
            setStatus(carrierTotal, 'idle', 'Nakliyeci net toplamı hesaplanır');
            setStatus(customerTotal, 'idle', 'Müşteri satış net toplamı hesaplanır');
            setStatus(total, 'idle', 'Sefer kârı hesaplanır');

            return;
        }

        const carrierSide = side('carrier_price', 'carrier_vat_rate', 'carrier_withholding_rate');
        const customerSide = side('sale_price', 'vat_rate', 'withholding_rate');
        const referral = value('commission_amount');

        setStatus(carrierTotal, 'ok', describe(carrierSide, 'nakliyeci net'));
        setStatus(customerTotal, 'ok', describe(customerSide, 'müşteri net'));
        setStatus(total, 'ok', `Sefer kârı (KDV hariç) ${money.format(customerSide.base - carrierSide.base - referral)} TL`);
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

    carrier?.addEventListener('change', () => {
        if (plate && form.dataset.vehiclesUrl) {
            const url = new URL(form.dataset.vehiclesUrl, window.location.origin);

            url.searchParams.set('party', carrier.value);
            plate.dataset.url = url.toString();
            plate.value = '';
        }
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
