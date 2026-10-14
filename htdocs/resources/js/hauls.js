const PLATE_STATES = {
    idle: 'fa-solid fa-circle',
    ok: 'fa-solid fa-circle-check',
    err: 'fa-solid fa-circle-xmark',
};

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

    const list = document.getElementById(input.getAttribute('list'));
    const status = hint.querySelector('.panel-field-status');
    const icon = status.querySelector('i');
    const text = hint.querySelector('[data-haul-plate-text]');
    const idleText = text.textContent;
    let vehicles = [];
    let request = 0;
    let timer = null;

    const setState = (state, message) => {
        hint.classList.remove('is-ok', 'is-err');
        status.className = `panel-field-status is-${state}`;
        icon.className = PLATE_STATES[state];

        if (state !== 'idle') {
            hint.classList.add(`is-${state}`);
        }

        text.textContent = message;
    };

    const render = () => {
        const key = normalizePlate(input.value);

        if (key === '') {
            setState('idle', idleText);

            return;
        }

        const match = vehicles.find((vehicle) => normalizePlate(vehicle.plate) === key);

        if (match) {
            setState('ok', [match.owner, match.class, match.driver ? `Şoför: ${match.driver}` : null].filter(Boolean).join(' · ') || 'Kayıtlı plaka');
        } else if (key.length >= 2) {
            setState('err', 'Kayıtlı plaka bulunamadı');
        } else {
            setState('idle', idleText);
        }
    };

    const lookup = async () => {
        const current = ++request;

        if (normalizePlate(input.value).length < 2) {
            vehicles = [];
            list?.replaceChildren();
            render();

            return;
        }

        try {
            const result = await getJson(input.dataset.url, { q: input.value });

            if (current !== request) {
                return;
            }

            vehicles = result;
            list?.replaceChildren(...vehicles.map((vehicle) => {
                const option = document.createElement('option');

                option.value = vehicle.plate;
                option.label = [vehicle.owner, vehicle.class].filter(Boolean).join(' · ');

                return option;
            }));
            render();
        } catch {
            setState('err', 'Plaka listesi alınamadı');
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(lookup, 200);
    });

    if (input.value) {
        lookup();
    }
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
