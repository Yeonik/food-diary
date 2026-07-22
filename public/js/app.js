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

// The goal switch dims its card live, matching the saved state's rendering.
document.addEventListener('change', function (event) {
    const toggle = event.target.closest('[data-dim-toggle]');
    if (!toggle) {
        return;
    }

    const card = toggle.closest('[data-dim]');
    if (card) {
        card.classList.toggle('card--dim', !toggle.checked);
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

    submit.disabled = chosen.size !== groups.size;
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
