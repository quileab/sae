/**
 * Imperative open/close for the grading <dialog>.
 *
 * The dialog node is always rendered and never removed: only its content is
 * conditional. That keeps it stable across Livewire morphs, so Alpine init
 * timing never decides whether it opens. Livewire drives visibility by
 * dispatching browser events after each DOM update instead.
 */
const DIALOG_ID = 'grading-dialog';

function dialog() {
    return document.getElementById(DIALOG_ID);
}

window.addEventListener('grading-dialog-open', () => {
    const dlg = dialog();

    if (dlg && !dlg.open) {
        dlg.showModal();
    }
});

window.addEventListener('grading-dialog-close', () => {
    const dlg = dialog();

    if (dlg && dlg.open) {
        dlg.close();
    }
});
