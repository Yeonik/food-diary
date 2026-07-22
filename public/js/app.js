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
