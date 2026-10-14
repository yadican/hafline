import { setStatus } from './components/status.js';

function normalizePlate(value) {
    return String(value ?? '').toUpperCase().replace(/\s+/g, '');
}

async function getJson(url, params) {
    const target = new URL(url, window.location.origin);

    Object.entries(params).forEach(([key, value]) => target.searchParams.set(key, value));

    const response = await fetch(target, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

function bindPlate(form) {
    const input = form.querySelector('[data-haul-plate]');
    const hint = form.querySelector('[data-haul-plate-hint]');

    if (! input || ! hint) {
        return;
    }

    let vehicles = [];

    const render = () => {
        const key = normalizePlate(input.value);
        const match = vehicles.find((vehicle) => normalizePlate(vehicle.value) === key);

        if (match) {
            setStatus(hint, 'ok', match.meta || 'Kayıtlı plaka');
        } else if (key.length >= 2) {
            setStatus(hint, 'err', 'Kayıtlı plaka bulunamadı');
        } else {
            setStatus(hint, 'idle');
        }
    };

    input.addEventListener('autocomplete:results', (event) => {
        vehicles = event.detail.items;
        render();
    });
    input.addEventListener('autocomplete:select', render);
    input.addEventListener('autocomplete:error', () => setStatus(hint, 'err', 'Plaka listesi alınamadı'));
}

const money = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function describeQuote(quote, trips) {
    const price = Number(quote.price);
    const dumpPrice = Number(quote.dump_price);
    const source = quote.source === 'vehicle' ? 'araca özel' : `${quote.class ?? 'sınıf'} fiyatı`;
    const parts = [
        `Sefer ${money.format(price)} TL (${source})`,
        quote.dump_priced ? `döküm ${money.format(dumpPrice)} TL` : 'döküm ücreti yok',
    ];
    let text = `${parts.join(' + ')} = ${money.format(price + dumpPrice)} TL`;

    if (trips > 1) {
        text += ` · ${trips} sefer toplam ${money.format((price + dumpPrice) * trips)} TL`;
    }

    return text;
}

// Döküm yeri listesi seçilen şantiyeden açık güzergahı olan yerlerle doldurulur; tek seçenek varsa
// kendiliğinden seçilir. Yerel change olayı hem select2 görünümünü hem fiyat satırını günceller.
function bindDumpSites(form) {
    const site = form.querySelector('[data-haul-site]');
    const dumpSite = form.querySelector('[data-haul-dump-site]');
    const hint = form.querySelector('[data-haul-quote]');

    if (! site || ! dumpSite) {
        return;
    }

    let request = 0;

    const fill = (items) => {
        dumpSite.replaceChildren(
            new Option('Döküm yeri seçin', ''),
            ...items.map((item) => new Option(item.name, item.id)),
        );
        dumpSite.value = items.length === 1 ? items[0].id : '';
        dumpSite.disabled = items.length === 0;
        dumpSite.dispatchEvent(new Event('change', { bubbles: true }));
    };

    site.addEventListener('change', async () => {
        const current = ++request;

        if (! site.value) {
            fill([]);

            return;
        }

        try {
            const items = await getJson(dumpSite.dataset.url, { site: site.value, current: form.dataset.currentRoute ?? '' });

            if (current !== request) {
                return;
            }

            fill(items);

            if (! items.length && hint) {
                setStatus(hint, 'err', 'Bu şantiyeden açık güzergah yok');
            }
        } catch {
            fill([]);

            if (hint) {
                setStatus(hint, 'err', 'Döküm yerleri alınamadı');
            }
        }
    });

    if (! site.value) {
        dumpSite.disabled = true;
    }
}

function bindQuote(form) {
    const plate = form.querySelector('[data-haul-plate]');
    const site = form.querySelector('[data-haul-site]');
    const dumpSite = form.querySelector('[data-haul-dump-site]');
    const trips = form.querySelector('[data-haul-trips]');
    const hint = form.querySelector('[data-haul-quote]');

    if (! plate || ! site || ! dumpSite || ! hint || ! form.dataset.quoteUrl) {
        return;
    }

    let request = 0;
    let timer;

    const refresh = async () => {
        const current = ++request;

        if (normalizePlate(plate.value).length < 2 || ! site.value || ! dumpSite.value) {
            setStatus(hint, 'idle');

            return;
        }

        try {
            const quote = await getJson(form.dataset.quoteUrl, {
                plate: plate.value,
                site: site.value,
                dump_site: dumpSite.value,
                current: form.dataset.currentRoute ?? '',
            });

            if (current !== request) {
                return;
            }

            if (quote.source === null) {
                setStatus(hint, 'err', `${quote.class ?? 'Bu araç'} için bu güzergahta fiyat tanımlı değil; sefer 0 TL kaydedilir`);
            } else {
                setStatus(hint, 'ok', describeQuote(quote, Number(trips?.value) || 1));
            }
        } catch {
            if (current === request) {
                setStatus(hint, 'err', 'Fiyat hesaplanamadı; plakayı listeden seçin');
            }
        }
    };

    const later = () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 350);
    };

    plate.addEventListener('autocomplete:select', refresh);
    plate.addEventListener('input', later);
    dumpSite.addEventListener('change', refresh);
    trips?.addEventListener('input', later);

    if (form.hasAttribute('data-quote-initial')) {
        refresh();
    }
}

function bindSearch(form) {
    const fields = () => form.querySelectorAll('input[name], select[name]');

    form.addEventListener('submit', () => {
        fields().forEach((field) => {
            if (field.value === '') {
                field.disabled = true;
            }
        });
    });

    window.addEventListener('pageshow', () => {
        fields().forEach((field) => {
            field.disabled = false;
        });
    });
}

function boot() {
    document.querySelectorAll('form[data-haul-entry]').forEach((form) => {
        bindPlate(form);
        bindDumpSites(form);
        bindQuote(form);
    });

    document.querySelectorAll('form[data-haul-search]').forEach(bindSearch);

    document.querySelectorAll('[data-haul-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
