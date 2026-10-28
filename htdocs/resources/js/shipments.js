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
    const kg = form.querySelector('[data-shipment-kg]');
    const quantity = form.querySelector('[data-shipment-quantity]');
    const commission = form.querySelector('[data-shipment-commission]');
    const total = form.querySelector('[data-shipment-total]');
    const carrierTotal = form.querySelector('[data-shipment-carrier-total]');
    const customerTotal = form.querySelector('[data-shipment-customer-total]');

    if (! kg || ! quantity || ! total || ! carrierTotal || ! customerTotal || ! form.dataset.pricesUrl) {
        return;
    }

    const kind = () => form.querySelector('[name="kind"]:checked')?.value ?? 'material_sale';
    const price = (key) => parseNumber(prices.find((input) => input.dataset.shipmentPrice === key)?.value);
    const rate = (name) => parseNumber(field(name)?.value);
    // Tonluk malzemede miktar kantardaki net kg'dan, m³'lük malzemede doğrudan girilir.
    const amount = () => (kg.offsetParent !== null ? parseNumber(kg.value) / 1000 : parseNumber(quantity.value));
    const taxed = (base, vatName, withholdingName) => {
        const tax = round2(base * rate(vatName) / 100);

        return base + tax - round2(tax * rate(withholdingName));
    };
    let request = 0;

    const renderTotal = () => {
        const units = amount();

        if (units <= 0) {
            setStatus(carrierTotal, 'idle', 'Nakliyeci net toplamı hesaplanır');
            setStatus(customerTotal, 'idle', 'Müşteri net toplamı hesaplanır');
            setStatus(total, 'idle', 'Sefer kârı hesaplanır');

            return;
        }

        const sale = round2(units * price('sale_price'));
        const carrier = round2(units * price('carrier_price'));
        const quarry = kind() === 'material_sale' ? round2(units * price('quarry_price')) : 0;
        const fee = parseNumber(commission?.value);

        setStatus(carrierTotal, 'ok', `Matrah ${money.format(carrier)} TL · nakliyeci net ${money.format(taxed(carrier, 'carrier_vat_rate', 'carrier_withholding_rate'))} TL alacak`);
        setStatus(customerTotal, 'ok', `Matrah ${money.format(sale)} TL · müşteri net ${money.format(taxed(sale, 'vat_rate', 'withholding_rate'))} TL borç`);
        setStatus(total, 'ok', (quarry ? `Ocak ${money.format(quarry)} TL · ` : '') + `Sefer kârı (KDV hariç) ${money.format(sale - carrier - quarry - fee)} TL`);
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
    [kg, quantity, commission, ...prices].forEach((input) => input?.addEventListener('input', renderTotal));
    form.querySelectorAll('[data-shipment-tax]').forEach((input) => input.addEventListener('change', renderTotal));
    field('material_id')?.addEventListener('change', () => window.setTimeout(renderTotal));

    if (form.hasAttribute('data-keep-price')) {
        renderTotal();
    } else {
        refresh();
    }
}

onReady(() => {
    document.querySelectorAll('form[data-shipment-entry]').forEach(bindShipment);
});
