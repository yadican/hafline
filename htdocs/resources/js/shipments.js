import { setStatus } from './components/status.js';
import { getJson, money, onReady } from './entry-support.js';

function parseNumber(value) {
    const number = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
}

function round2(value) {
    return Math.round(value * 100) / 100;
}

// Tür, tarih, ocak, malzeme veya sevk yeri değişince birim fiyatlar fiyat listesinden gelir.
// Düzenlemede kayıtlı fiyatlar, kullanıcı anahtar alanlardan birini değiştirmedikçe korunur.
function bindShipment(form) {
    const field = (name) => form.querySelector(`[name="${name}"]`);
    const prices = [...form.querySelectorAll('[data-shipment-price]')];
    const quantity = form.querySelector('[data-shipment-quantity]');
    const vat = form.querySelector('[data-shipment-vat]');
    const withholding = form.querySelector('[data-shipment-withholding]');
    const commission = form.querySelector('[data-shipment-commission]');
    const total = form.querySelector('[data-shipment-total]');

    if (! quantity || ! total || ! form.dataset.pricesUrl) {
        return;
    }

    const kind = () => form.querySelector('[name="kind"]:checked')?.value ?? 'material_sale';
    const price = (key) => parseNumber(prices.find((input) => input.dataset.shipmentPrice === key)?.value);
    let request = 0;

    const renderTotal = () => {
        const amount = parseNumber(quantity.value);

        if (amount <= 0) {
            setStatus(total, 'idle', 'Miktar ve fiyatlar girilince cari tutarları hesaplanır');

            return;
        }

        const sale = round2(amount * price('sale_price'));
        const tax = round2(sale * parseNumber(vat?.value) / 100);
        const withheld = round2(tax * parseNumber(withholding?.value));
        const carrier = round2(amount * price('carrier_price'));
        const quarry = kind() === 'material_sale' ? round2(amount * price('quarry_price')) : 0;
        const fee = parseNumber(commission?.value);
        const profit = sale - carrier - quarry - fee;

        setStatus(total, 'ok', `Müşteri ${money.format(sale + tax - withheld)} TL borç · nakliyeci ${money.format(carrier)} TL`
            + (quarry ? ` · ocak ${money.format(quarry)} TL` : '')
            + (fee ? ` · komisyon ${money.format(fee)} TL` : '')
            + ` · kâr ${money.format(profit)} TL`);
    };

    const refresh = async () => {
        const current = ++request;

        try {
            const found = await getJson(form.dataset.pricesUrl, {
                kind: kind(),
                shipped_on: field('shipped_on')?.value ?? '',
                quarry_id: field('quarry_id')?.value ?? '',
                material_id: field('material_id')?.value ?? '',
                delivery_address_id: field('delivery_address_id')?.value ?? '',
            });

            if (current !== request) {
                return;
            }

            prices.forEach((input) => {
                const value = Number(found[input.dataset.shipmentPrice]);

                input.value = value > 0 ? value.toFixed(4) : '';
            });
        } catch {
            // Fiyat listesine ulaşılamazsa elle girilen değerler kalır.
        }

        renderTotal();
    };

    form.querySelectorAll('[data-shipment-price-key]').forEach((input) => input.addEventListener('change', refresh));
    form.querySelectorAll('[name="kind"]').forEach((input) => input.addEventListener('change', refresh));
    [quantity, vat, withholding, commission, ...prices].forEach((input) => input?.addEventListener('input', renderTotal));
    [vat, withholding].forEach((input) => input?.addEventListener('change', renderTotal));

    if (form.hasAttribute('data-keep-price')) {
        renderTotal();
    } else {
        refresh();
    }
}

onReady(() => {
    document.querySelectorAll('form[data-shipment-entry]').forEach(bindShipment);
});
