export function normalizePlate(value) {
    return String(value ?? '').toUpperCase().replace(/\s+/g, '');
}

export async function getJson(url, params) {
    const target = new URL(url, window.location.origin);

    Object.entries(params).forEach(([key, value]) => target.searchParams.set(key, value));

    const response = await fetch(target, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        const error = new Error(`HTTP ${response.status}`);

        error.payload = await response.json().catch(() => null);
        throw error;
    }

    return response.json();
}

export const money = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Boş filtreler gönderilmez; adres çubuğunda yalnızca dolu alanlar kalır.
export function bindSearch(form) {
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

export function bindPrint(button) {
    button.addEventListener('click', () => window.print());
}

export function onReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}
