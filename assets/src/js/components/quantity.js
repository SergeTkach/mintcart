/**
 * mc-quantity stepper behavior.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="quantity-decrement"]` /
 * `[data-mc-action="quantity-increment"]` inside `.mc-quantity`. Delegated on
 * `document` so quantity steppers rendered after page load (e.g. inserted
 * into a cart drawer via fetch()) work without re-wiring - no per-instance
 * init call needed at render time, same approach as mc-alert.
 *
 * Contract: docs/developer/components/quantity.md
 */

function readBound(input, attribute, fallback) {
    const raw = input.getAttribute(attribute);

    if (raw === null || raw === '') {
        return fallback;
    }

    const value = Number(raw);

    return Number.isNaN(value) ? fallback : value;
}

function syncQuantityButtons(quantity) {
    const input = quantity.querySelector('.mc-quantity__input');

    if (!input) {
        return;
    }

    const value = Number(input.value) || 0;
    const min = readBound(input, 'min', -Infinity);
    const max = readBound(input, 'max', Infinity);
    const decrementButton = quantity.querySelector('[data-mc-action="quantity-decrement"]');
    const incrementButton = quantity.querySelector('[data-mc-action="quantity-increment"]');

    if (decrementButton) {
        decrementButton.disabled = input.disabled || value <= min;
    }

    if (incrementButton) {
        incrementButton.disabled = input.disabled || value >= max;
    }
}

export function initQuantityStepper(root = document) {
    root.querySelectorAll('.mc-quantity').forEach(syncQuantityButtons);

    root.addEventListener('click', (event) => {
        const button = event.target.closest(
            '[data-mc-action="quantity-decrement"], [data-mc-action="quantity-increment"]'
        );

        if (!button) {
            return;
        }

        const quantity = button.closest('.mc-quantity');
        const input = quantity?.querySelector('.mc-quantity__input');

        if (!input || input.disabled) {
            return;
        }

        const step = readBound(input, 'step', 1);
        const min = readBound(input, 'min', -Infinity);
        const max = readBound(input, 'max', Infinity);
        const direction = button.dataset.mcAction === 'quantity-increment' ? 1 : -1;
        const current = Number(input.value) || 0;
        const next = Math.min(max, Math.max(min, current + direction * step));

        if (next === current) {
            return;
        }

        input.value = String(next);
        input.dispatchEvent(new Event('change', { bubbles: true }));
        quantity.dispatchEvent(
            new CustomEvent('mc:quantity:change', { bubbles: true, detail: { value: next } })
        );
        syncQuantityButtons(quantity);
    });

    root.addEventListener('input', (event) => {
        const input = event.target.closest('.mc-quantity__input');

        if (!input) {
            return;
        }

        const quantity = input.closest('.mc-quantity');

        if (quantity) {
            syncQuantityButtons(quantity);
        }
    });
}
