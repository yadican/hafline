import { setStatus } from './components/status.js';
import { bindPrint, bindSearch, getJson, money, normalizePlate, onReady } from './entry-support.js';

function parseNumber(value) {
    const number = Number(String(value ?? '').replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
}

function formatHours(hours) {
    return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 }).format(hours);
}

function bindPlate(form) {
    const input = form.querySelector('[data-machine-plate]');
    const hint = form.querySelector('[data-machine-plate-hint]');

    if (! input || ! hint) {
        return;
    }

    let machines = [];

    const render = () => {
        const key = normalizePlate(input.value);
        const match = machines.find((machine) => normalizePlate(machine.value) === key);

        if (match) {
            setStatus(hint, 'ok', match.meta || 'Kayıtlı makine');
        } else if (key.length >= 2) {
            setStatus(hint, 'err', 'Saatlik çalışan kayıtlı makine bulunamadı');
        } else {
            setStatus(hint, 'idle');
        }
    };

    input.addEventListener('autocomplete:results', (event) => {
        machines = event.detail.items;
        render();
    });
    input.addEventListener('autocomplete:select', render);
    input.addEventListener('autocomplete:error', () => setStatus(hint, 'err', 'Makine listesi alınamadı'));
}

// Makine veya tarih değişince o tarihte geçerli dönem ücreti saat ücreti alanına yazılır; alan elle
// değiştirilebilir. Düzenleme ekranında kayıtlı ücret, makine ya da tarih değişmedikçe korunur.
function bindRate(form) {
    const plate = form.querySelector('[data-machine-plate]');
    const date = form.querySelector('[data-machine-date]');
    const hours = form.querySelector('[data-machine-hours]');
    const rate = form.querySelector('[data-machine-rate]');
    const hint = form.querySelector('[data-machine-total]');

    if (! plate || ! date || ! hours || ! rate || ! hint || ! form.dataset.rateUrl) {
        return;
    }

    let request = 0;
    let timer;
    let note = '';

    const renderTotal = () => {
        const h = parseNumber(hours.value);

        if (rate.value === '') {
            setStatus(hint, note ? 'err' : 'idle', note || 'Makine ve tarih seçilince dönem ücreti otomatik gelir');

            return;
        }

        const r = parseNumber(rate.value);
        const total = `${formatHours(h)} saat × ${money.format(r)} TL = ${money.format(h * r)} TL`;

        setStatus(hint, 'ok', note ? `${total} · ${note}` : total);
    };

    const refresh = async () => {
        const current = ++request;

        if (normalizePlate(plate.value).length < 2 || ! date.value) {
            return;
        }

        try {
            const quote = await getJson(form.dataset.rateUrl, { plate: plate.value, date: date.value });

            if (current !== request) {
                return;
            }

            if (quote.rate === null) {
                note = 'Bu tarihte makinenin saat ücreti tanımlı değil; ücreti elle girin';
                rate.value = '';
            } else {
                note = `dönem ücreti, ${quote.since} tarihinden beri`;
                rate.value = Number(quote.rate).toFixed(2);
            }

            renderTotal();
        } catch {
            if (current === request) {
                note = '';
                setStatus(hint, 'err', 'Saat ücreti alınamadı; plakayı listeden seçin');
            }
        }
    };

    const later = () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 350);
    };

    plate.addEventListener('autocomplete:select', refresh);
    plate.addEventListener('input', later);
    date.addEventListener('change', refresh);
    hours.addEventListener('input', renderTotal);
    rate.addEventListener('input', () => {
        note = '';
        renderTotal();
    });

    if (! form.hasAttribute('data-keep-rate') && rate.value === '') {
        refresh();
    } else if (rate.value !== '') {
        renderTotal();
    }
}

// Personel olan operatöre fişten ücret yazılmaz, alan kapanır. Serbest operatör seçilince son fişindeki
// ücret gelir; kayıtlı fişte operatör değişmedikçe girilen ücret korunur.
function bindOperator(form) {
    const operator = form.querySelector('[data-machine-operator]');
    const rate = form.querySelector('[data-machine-operator-rate]');
    const hint = form.querySelector('[data-machine-operator-total]');
    const hours = form.querySelector('[data-machine-hours]');

    if (! operator || ! rate || ! hint || ! hours || ! form.dataset.operatorUrl) {
        return;
    }

    let request = 0;
    let salaried = false;

    const render = () => {
        if (! operator.value) {
            setStatus(hint, 'idle', 'Operatör yok; kimseye operatör ücreti yazılmaz');
        } else if (salaried) {
            setStatus(hint, 'idle', 'Personel; maaşlı olduğu için carisine ücret yazılmaz');
        } else if (rate.value === '') {
            setStatus(hint, 'err', 'Operatörün carisine yazılacak saat ücretini girin');
        } else {
            const h = parseNumber(hours.value);
            const r = parseNumber(rate.value);

            setStatus(hint, 'ok', `${formatHours(h)} saat × ${money.format(r)} TL = ${money.format(h * r)} TL operatöre alacak`);
        }
    };

    const lock = (locked) => {
        rate.disabled = locked;

        if (locked) {
            rate.value = '';
        }
    };

    const refresh = async (keepRate) => {
        const current = ++request;

        salaried = false;

        if (! operator.value) {
            lock(true);
            render();

            return;
        }

        try {
            const info = await getJson(form.dataset.operatorUrl, { id: operator.value });

            if (current !== request) {
                return;
            }

            salaried = info.salaried;
            lock(salaried);

            if (! salaried && (! keepRate || rate.value === '') && info.rate !== null) {
                rate.value = Number(info.rate).toFixed(2);
            }
        } catch {
            if (current === request) {
                lock(false);
            }
        }

        render();
    };

    operator.addEventListener('change', () => refresh(false));
    hours.addEventListener('input', render);
    rate.addEventListener('input', render);

    refresh(true);
}

onReady(() => {
    document.querySelectorAll('form[data-machine-entry]').forEach((form) => {
        bindPlate(form);
        bindRate(form);
        bindOperator(form);
    });

    document.querySelectorAll('form[data-entry-search]').forEach(bindSearch);
    document.querySelectorAll('[data-entry-print]').forEach(bindPrint);
});
