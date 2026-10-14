import flatpickr from 'flatpickr';
import { Turkish } from 'flatpickr/dist/l10n/tr.js';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
import weekSelectPlugin from 'flatpickr/dist/plugins/weekSelect/weekSelect.js';

const WEEK_VALUE = 'iso-week';
const WEEK_LABEL = 'iso-week-label';

function isoWeek(date) {
    const day = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const weekday = day.getUTCDay() || 7;

    day.setUTCDate(day.getUTCDate() + 4 - weekday);

    const year = day.getUTCFullYear();
    const week = Math.ceil(((day - Date.UTC(year, 0, 1)) / 86400000 + 1) / 7);

    return { year, week };
}

function weekBounds(date) {
    const monday = new Date(date.getFullYear(), date.getMonth(), date.getDate() - ((date.getDay() || 7) - 1));
    const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6);

    return { monday, sunday };
}

function formatWeekDate(date, format, locale) {
    if (format === WEEK_VALUE) {
        const { year, week } = isoWeek(date);

        return `${year}-W${String(week).padStart(2, '0')}`;
    }

    if (format === WEEK_LABEL) {
        const { week } = isoWeek(date);
        const { monday, sunday } = weekBounds(date);
        const start = flatpickr.formatDate(monday, 'j M', locale);
        const end = flatpickr.formatDate(sunday, 'j M Y', locale);

        return `${week}. hafta · ${start} – ${end}`;
    }

    return flatpickr.formatDate(date, format, locale);
}

function parseWeekDate(value, format) {
    const match = String(value).trim().match(/^(\d{4})-W(\d{1,2})$/);

    if (match) {
        const year = Number(match[1]);
        const jan4 = new Date(year, 0, 4);

        return new Date(year, 0, 4 - ((jan4.getDay() || 7) - 1) + (Number(match[2]) - 1) * 7);
    }

    return flatpickr.parseDate(value, format);
}

const pickerOptions = {
    date: () => ({
        dateFormat: 'Y-m-d',
        altFormat: 'j F Y, l',
    }),
    time: () => ({
        enableTime: true,
        noCalendar: true,
        dateFormat: 'H:i',
        altFormat: 'H:i',
    }),
    'datetime-local': () => ({
        enableTime: true,
        dateFormat: 'Y-m-d\\TH:i',
        altFormat: 'j F Y, l · H:i',
    }),
    month: () => ({
        plugins: [monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    }),
    week: () => ({
        dateFormat: WEEK_VALUE,
        altFormat: WEEK_LABEL,
        formatDate: formatWeekDate,
        parseDate: parseWeekDate,
        plugins: [weekSelectPlugin()],
    }),
};

export function bindDatePickers(root = document) {
    root.querySelectorAll('input[data-picker]').forEach((input) => {
        const options = pickerOptions[input.dataset.picker];

        if (! options || input._flatpickr) {
            return;
        }

        const wrap = input.closest('[data-picker-wrap]');
        const clear = wrap?.querySelector('[data-picker-clear]');
        const placeholder = {
            time: 'Saat seçin',
            month: 'Ay seçin',
            week: 'Hafta seçin',
        }[input.dataset.picker] ?? 'Tarih seçin';

        const picker = flatpickr(input, {
            locale: Turkish,
            altInput: true,
            altInputClass: `${input.className} panel-date-input`,
            disableMobile: true,
            monthSelectorType: 'static',
            time_24hr: true,
            minuteIncrement: 5,
            onChange: (dates) => {
                if (clear) {
                    clear.hidden = dates.length === 0;
                }
            },
            ...options(),
            ...(input.dataset.altFormat ? { altFormat: input.dataset.altFormat } : {}),
        });

        picker.altInput.placeholder = input.placeholder || placeholder;

        if (input.hasAttribute('aria-label')) {
            picker.altInput.setAttribute('aria-label', input.getAttribute('aria-label'));
        }

        if (input.id) {
            picker.altInput.id = input.id;
            input.removeAttribute('id');
        }

        clear?.addEventListener('click', () => {
            picker.clear();
            picker.altInput.focus();
        });
    });
}
