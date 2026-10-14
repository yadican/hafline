const TOAST_STYLES = {
    ok: { title: 'Başarılı', icon: 'fa-solid fa-check', duration: 3500 },
    err: { title: 'Hata', icon: 'fa-solid fa-exclamation', duration: 6000 },
    info: { title: 'Bilgi', icon: 'fa-solid fa-info', duration: 4500 },
};

export function toast(message, type = 'ok') {
    const text = String(message ?? '').trim();

    if (text === '') {
        return;
    }

    let host = document.getElementById('panel-toast-host');

    if (! host) {
        host = document.createElement('div');
        host.id = 'panel-toast-host';
        host.className = 'panel-toast-host';
        document.body.appendChild(host);
    }

    const style = TOAST_STYLES[type] ?? TOAST_STYLES.ok;
    const element = document.createElement('div');

    element.className = `panel-toast panel-toast-${type}`;
    element.setAttribute('role', type === 'err' ? 'alert' : 'status');
    element.innerHTML = `<span class="panel-toast-icon"><i class="${style.icon}" aria-hidden="true"></i></span><div class="panel-toast-body"><div class="panel-toast-title"></div><div class="panel-toast-msg"></div></div><button type="button" class="panel-toast-close" aria-label="Kapat"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button><span class="panel-toast-bar" aria-hidden="true"></span>`;
    element.querySelector('.panel-toast-title').textContent = style.title;
    element.querySelector('.panel-toast-msg').textContent = text;
    element.querySelector('.panel-toast-bar').style.animationDuration = `${style.duration}ms`;
    host.appendChild(element);
    requestAnimationFrame(() => {
        element.classList.add('panel-toast-in');
    });

    let closed = false;
    const close = () => {
        if (closed) {
            return;
        }

        closed = true;
        element.classList.remove('panel-toast-in');
        element.classList.add('panel-toast-out');
        setTimeout(() => element.remove(), 260);
    };

    element.querySelector('.panel-toast-close').addEventListener('click', close);
    element.querySelector('.panel-toast-bar').addEventListener('animationend', close);
}

export function showFlash() {
    const node = document.getElementById('panel-flash');

    if (! node) {
        return;
    }

    const flash = JSON.parse(node.textContent || '{}');

    if (flash.success) {
        toast(flash.success, 'ok');
    }

    if (flash.error) {
        toast(flash.error, 'err');
    }

    if (flash.info) {
        toast(flash.info, 'info');
    }
}
