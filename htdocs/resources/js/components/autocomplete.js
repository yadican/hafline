// Input üzerinde tetiklenen olaylar:
//   autocomplete:results  detail.items  her başarılı aramada (boş dizi dahil)
//   autocomplete:select   detail.item   listeden seçim yapıldığında
//   autocomplete:error                  istek başarısız olduğunda

function compact(value) {
    return String(value ?? '').toLocaleUpperCase('tr').replace(/\s+/g, '');
}

function highlighted(text, query) {
    const fragment = document.createDocumentFragment();
    const positions = [];

    [...text].forEach((char, index) => {
        if (! /\s/.test(char)) {
            positions.push(index);
        }
    });

    const key = compact(query);
    const start = key === '' ? -1 : compact(text).indexOf(key);

    if (start === -1) {
        fragment.append(text);

        return fragment;
    }

    const from = positions[start];
    const to = positions[start + key.length - 1] + 1;
    const mark = document.createElement('mark');

    mark.textContent = text.slice(from, to);
    fragment.append(text.slice(0, from), mark, text.slice(to));

    return fragment;
}

async function fetchItems(url, query) {
    const target = new URL(url, window.location.origin);

    target.searchParams.set('q', query);

    const response = await fetch(target, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

function mountAutocomplete(wrap) {
    const input = wrap.querySelector('[data-autocomplete-input]');
    const menu = wrap.querySelector('[data-autocomplete-menu]');
    const min = Number(input.dataset.min || 2);
    let items = [];
    let active = -1;
    let request = 0;
    let timer = null;

    const emit = (name, detail = {}) => input.dispatchEvent(new CustomEvent(`autocomplete:${name}`, { bubbles: true, detail }));
    const options = () => menu.querySelectorAll('[role="option"]');

    const close = () => {
        menu.hidden = true;
        active = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    };

    const highlight = (index) => {
        active = index;
        options().forEach((option, i) => {
            option.classList.toggle('is-active', i === index);
            option.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });

        if (index < 0) {
            input.removeAttribute('aria-activedescendant');

            return;
        }

        const option = options()[index];

        input.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
    };

    const open = () => {
        if (! items.length || document.activeElement !== input) {
            close();

            return;
        }

        menu.replaceChildren(...items.map((item, index) => {
            const option = document.createElement('div');
            const label = document.createElement('span');

            option.id = `${menu.id}-${index}`;
            option.className = 'panel-autocomplete-option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            label.className = 'panel-autocomplete-label';
            label.append(highlighted(item.label ?? item.value, input.value));
            option.append(label);

            if (item.meta) {
                const meta = document.createElement('span');

                meta.className = 'panel-autocomplete-meta';
                meta.textContent = item.meta;
                option.append(meta);
            }

            return option;
        }));

        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(-1);
    };

    const pick = (index) => {
        const item = items[index];

        if (! item) {
            return;
        }

        input.value = item.value;
        close();
        emit('select', { item });
    };

    const search = async () => {
        const current = ++request;

        if (compact(input.value).length < min) {
            items = [];
            close();
            emit('results', { items });

            return;
        }

        try {
            const result = await fetchItems(input.dataset.url, input.value);

            if (current !== request) {
                return;
            }

            items = result;
            open();
            emit('results', { items });
        } catch {
            items = [];
            close();
            emit('error');
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, 200);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (menu.hidden) {
                open();
            }

            if (menu.hidden) {
                return;
            }

            event.preventDefault();
            const count = items.length;
            const step = event.key === 'ArrowDown' ? 1 : -1;

            highlight(active === -1 && step === -1 ? count - 1 : (active + step + count) % count);
        } else if (event.key === 'Enter' && ! menu.hidden && active !== -1) {
            event.preventDefault();
            pick(active);
        } else if (event.key === 'Escape' && ! menu.hidden) {
            event.preventDefault();
            close();
        }
    });

    input.addEventListener('blur', close);
    menu.addEventListener('mousedown', (event) => event.preventDefault());

    menu.addEventListener('click', (event) => {
        const option = event.target.closest('[role="option"]');

        if (option) {
            pick([...options()].indexOf(option));
        }
    });

    menu.addEventListener('mousemove', (event) => {
        const option = event.target.closest('[role="option"]');
        const index = option ? [...options()].indexOf(option) : -1;

        if (index !== -1 && index !== active) {
            highlight(index);
        }
    });

    if (input.value) {
        search();
    }
}

export function bindAutocompletes(root = document) {
    root.querySelectorAll('[data-autocomplete]').forEach((wrap) => {
        if (wrap.dataset.autocompleteBound === '1') {
            return;
        }

        wrap.dataset.autocompleteBound = '1';
        mountAutocomplete(wrap);
    });
}
