import { bindPrint, bindSearch, onReady } from './entry-support.js';

onReady(() => {
    document.querySelectorAll('form[data-report-search]').forEach(bindSearch);
    document.querySelectorAll('[data-report-print]').forEach(bindPrint);
});
