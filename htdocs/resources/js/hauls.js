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

function bindDumpSites(form) {
    const site = form.querySelector('[data-haul-site]');
    const dumpSite = form.querySelector('[data-haul-dump-site]');

    if (! site || ! dumpSite) {
        return;
    }

    let request = 0;

    const fill = (placeholder, items = []) => {
        dumpSite.replaceChildren(
            new Option(placeholder, ''),
            ...items.map((item) => new Option(item.name, item.id)),
        );
        dumpSite.disabled = items.length === 0;
    };

    site.addEventListener('change', async () => {
        const current = ++request;

        if (! site.value) {
            fill('Önce şantiye seçin');

            return;
        }

        fill('Yükleniyor…');

        try {
            const items = await getJson(dumpSite.dataset.url, { site: site.value });

            if (current !== request) {
                return;
            }

            fill(items.length ? 'Döküm yeri seçin' : 'Bu şantiye için döküm yeri tanımlı değil', items);
        } catch {
            fill('Döküm yerleri alınamadı');
        }
    });

    if (dumpSite.options.length <= 1 && ! site.value) {
        dumpSite.disabled = true;
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
