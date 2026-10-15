// İl → ilçe → semt zinciri: üst seçim değişince alt liste sunucudan doldurulur, daha alttakiler boşalır.
// data-url içindeki __ID__ seçilen üst kaydın kimliğiyle değiştirilir. Yerel change olayı select2
// görünümünü de günceller ve zinciri bir alt seviyeye taşır.
async function load(url, id) {
    const response = await fetch(url.replace('__ID__', encodeURIComponent(id)), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

function fill(select, items, placeholder) {
    select.replaceChildren(new Option(placeholder, ''), ...items.map((item) => new Option(item.name, item.id)));
    select.value = '';
    select.disabled = items.length === 0;
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

function chain(parent, child) {
    let request = 0;

    parent.addEventListener('change', async () => {
        const current = ++request;
        const placeholder = child.dataset.placeholder || 'Seçin';

        if (! parent.value) {
            fill(child, [], placeholder);

            return;
        }

        try {
            const items = await load(child.dataset.url, parent.value);

            if (current === request) {
                fill(child, items, placeholder);
            }
        } catch {
            fill(child, [], 'Liste alınamadı');
        }
    });

    if (! parent.value) {
        child.disabled = true;
    }
}

export function bindLocationSelects(root = document) {
    root.querySelectorAll('[data-location]').forEach((group) => {
        if (group.dataset.locationBound) {
            return;
        }

        group.dataset.locationBound = '1';

        const city = group.querySelector('[data-location-city]');
        const district = group.querySelector('[data-location-district]');
        const neighborhood = group.querySelector('[data-location-neighborhood]');

        if (city && district) {
            chain(city, district);
        }

        if (district && neighborhood) {
            chain(district, neighborhood);
        }
    });
}
