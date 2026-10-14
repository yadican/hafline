import Alpine from 'alpinejs';
import { alert, blockPage, confirm, mount, showFlash, toast, unblockPage } from './components/index.js';

window.Alpine = Alpine;
window.panelMount = mount;
window.panelToast = toast;
window.panelConfirm = confirm;
window.panelAlert = alert;
window.panelBlock = blockPage;
window.panelUnblock = unblockPage;

Alpine.start();

function boot() {
    mount();
    showFlash();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
