/**
 * Clipboard for the assisted grading dialog.
 *
 * Lives here rather than in an inline <script> inside the component: an inline
 * script only runs once Livewire injects it, and the handler had to be resolved
 * out of the global scope by Alpine. Delegation from a real module is loaded with
 * the page, so the button works on first paint and after any re-render.
 */
const COPIED_FOR_MS = 2500;

const selectors = {
    source: '#assist-markdown',
    trigger: '[data-assist-copy]',
    copied: '[data-assist-feedback="copied"]',
    failed: '[data-assist-feedback="failed"]',
};

function show(selector) {
    document.querySelectorAll(selector).forEach((element) => {
        element.classList.remove('hidden');
    });
}

function hide(selector) {
    document.querySelectorAll(selector).forEach((element) => {
        element.classList.add('hidden');
    });
}

function notify(success) {
    hide(selectors.copied);
    hide(selectors.failed);
    show(success ? selectors.copied : selectors.failed);

    if (success) {
        setTimeout(() => hide(selectors.copied), COPIED_FOR_MS);
    }
}

function legacyCopy() {
    try {
        // Deprecated, but still the only path on insecure origins and older
        // browsers, and it must run synchronously inside the click.
        return document.execCommand('copy');
    } catch (error) {
        return false;
    }
}

async function copy(field) {
    field.focus();
    field.select();
    field.setSelectionRange(0, field.value.length);

    if (navigator.clipboard?.writeText && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(field.value);

            return true;
        } catch (error) {
            return legacyCopy();
        }
    }

    return legacyCopy();
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest(selectors.trigger);

    if (!trigger) {
        return;
    }

    const field = document.querySelector(selectors.source);

    if (!field) {
        return;
    }

    event.preventDefault();
    copy(field).then(notify);
});