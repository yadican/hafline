import { bindAutocompletes } from './autocomplete.js';
import { bindDatePickers } from './date-picker.js';
import { bindConfirms, bindRoleAssignmentConfirms } from './dialog.js';
import { bindEmployeeBalances } from './employee-balance.js';
import { bindErrorSummary } from './error-summary.js';
import { bindLocationSelects } from './location-select.js';
import { bindMultiSelects } from './multi-select.js';
import { bindSubmitGuard } from './submit-guard.js';

export { alert, confirm } from './dialog.js';
export { setStatus } from './status.js';
export { blockPage, submitForm, unblockPage } from './submit-guard.js';
export { showFlash, toast } from './toast.js';

// Her bileşen kendi data-* işaretini arar ve aynı öğeye ikinci kez bağlanmaz;
// sonradan eklenen HTML için mount(eklenenKök) tekrar çağrılabilir.
const binders = [
    bindSubmitGuard,
    bindConfirms,
    bindRoleAssignmentConfirms,
    bindDatePickers,
    bindMultiSelects,
    bindLocationSelects,
    bindAutocompletes,
    bindEmployeeBalances,
    bindErrorSummary,
];

export function mount(root = document) {
    binders.forEach((bind) => bind(root));
}
