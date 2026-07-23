'use strict';

// Progressive enhancement only. Every control here works without JavaScript —
// the number inputs are directly editable and the form submits on its own; this
// file just adds the stepper buttons and the live goal-card dimming.

// Stepper buttons adjust a number input, clamped to its min/max.
document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-step-target]');
    if (!button) {
        return;
    }

    const input = document.getElementById(button.dataset.stepTarget);
    if (!input) {
        return;
    }

    const delta = Number(button.dataset.stepDelta || 0);
    const min = input.min !== '' ? Number(input.min) : -Infinity;
    const max = input.max !== '' ? Number(input.max) : Infinity;
    const current = Number(input.value || 0);

    input.value = String(Math.min(max, Math.max(min, current + delta)));
});

// The goal switch dims its card live, matching what the server renders for the
// saved state. The switch and the card it dims are separate cards, so the toggle
// names its target by id — the same shape as the stepper buttons above. The
// class is the one the server writes, so the two can never disagree.
document.addEventListener('change', function (event) {
    const toggle = event.target.closest('[data-dim-toggle]');
    if (!toggle) {
        return;
    }

    const card = document.getElementById(toggle.dataset.dimToggle);
    if (card) {
        card.classList.toggle('dim', !toggle.checked);
    }
});

// Confirm screen: the submit button stays disabled until every dish has a
// source chosen. Server-side the same rule holds — a dish with no choice is not
// logged — so this is UX, not the guarantee.
function updateConfirmSubmit() {
    const submit = document.querySelector('[data-confirm-submit]');
    if (!submit) {
        return;
    }

    const groups = new Set();
    const chosen = new Set();
    document.querySelectorAll('input[type="radio"][data-confirm-source]').forEach(function (radio) {
        groups.add(radio.name);
        if (radio.checked) {
            chosen.add(radio.name);
        }
    });

    const complete = chosen.size === groups.size;
    submit.disabled = !complete;

    // The hint explains the disabled button; once every dish has a source it has
    // nothing left to say.
    const hint = document.querySelector('[data-confirm-hint]');
    if (hint) {
        hint.hidden = complete;
    }
}

document.addEventListener('change', function (event) {
    if (event.target.matches('input[type="radio"][data-confirm-source]')) {
        updateConfirmSubmit();
    }
});

document.addEventListener('DOMContentLoaded', updateConfirmSubmit);

// Dialogs: [data-dialog-open] opens by id, [data-dialog-close] closes, and a
// click on the backdrop closes too. Esc is handled natively by <dialog>. The
// openers are ordinary links, so without JS they navigate to a full page.
document.addEventListener('click', function (event) {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
        const dialog = document.getElementById(opener.dataset.dialogOpen);
        if (dialog && typeof dialog.showModal === 'function') {
            event.preventDefault();
            dialog.showModal();
        }
        return;
    }

    const closer = event.target.closest('[data-dialog-close]');
    if (closer) {
        const owner = closer.closest('dialog');
        if (owner) {
            owner.close();
        }
        return;
    }

    // A click landing on the dialog element itself is a click on the backdrop,
    // outside the panel — close it.
    if (event.target.matches('dialog.dialog')) {
        event.target.close();
    }
});

// Barcode scanning: native BarcodeDetector on a still frame from the same
// capture input the photo path uses — no getUserMedia, no viewfinder, no
// scanner library. Where the API is missing (Firefox, Safari, iOS) the manual
// code field is the whole feature, and we say why rather than degrade silently.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-barcode-form]');
    if (!form) {
        return;
    }

    const scanField = form.querySelector('[data-barcode-scan]');
    const unsupported = form.querySelector('[data-barcode-unsupported]');
    const frame = form.querySelector('[data-barcode-frame]');
    const code = form.querySelector('[data-barcode-code]');
    const unread = form.querySelector('[data-barcode-unread]');

    if (!('BarcodeDetector' in window)) {
        // No scanning here — tell the user plainly; the code field stays.
        if (unsupported) {
            unsupported.hidden = false;
        }
        return;
    }

    if (scanField) {
        scanField.hidden = false;
    }

    if (!frame) {
        return;
    }

    frame.addEventListener('change', async function () {
        const file = frame.files && frame.files[0];
        if (!file) {
            return;
        }

        if (unread) {
            unread.hidden = true;
        }

        let value = null;
        try {
            const bitmap = await createImageBitmap(file);
            const codes = await new window.BarcodeDetector().detect(bitmap);
            value = codes.length ? codes[0].rawValue : null;
        } catch (error) {
            value = null;
        }

        if (value) {
            code.value = value;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } else if (unread) {
            // A single still frame can be blurred or angled: point back to the
            // manual field rather than fail in silence.
            unread.hidden = false;
            code.focus();
        }
    });
});
