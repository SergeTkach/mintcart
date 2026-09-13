/**
 * mc-product-card Quick Order behavior.
 *
 * Behavior hook (CLAUDE.md §14): `[data-mc-action="quick-order"]` inside
 * `.mc-product-card` (the button `product-card.twig` already renders,
 * previously hook-only — see that file's own header comment). Delegated on
 * `document`, same approach every other product-card action uses.
 *
 * A single `mc-modal` instance is built once on first use and reused for
 * every Quick Order click (one modal per page, not one per card) - product
 * listings can render dozens of cards, and Bootstrap's `Modal` assumes one
 * live instance per dialog element. Populated per click with the clicked
 * product's id/name (`data-product-id`/`data-product-name`).
 *
 * Submits via `../commerce/quick-order-client.js`. A required-option/
 * recurring-plan product redirects to the product page, the same fallback
 * `add-to-cart` already uses (no option picker on a grid card); field
 * validation errors render inline using the `mc-field`/`has-error`
 * contract (docs/developer/components/field.md); success closes the modal
 * and shows a toast with the server's own confirmation message.
 *
 * Contract: docs/developer/commerce/quick-order-service.md
 */

import { Modal } from 'bootstrap';
import { submitQuickOrder } from '../commerce/quick-order-client.js';
import { createToast, showToast } from './toast.js';

const MODAL_ID = 'mc-quick-order-modal';

function fieldMarkup(name, label, type, describedBy) {
    return `
        <div class="mc-field" data-mc-quick-order-field="${name}">
            <label class="mc-field__label" for="${describedBy}">${label}</label>
            <input type="${type}" id="${describedBy}" name="${name}" class="mc-field__control" autocomplete="off">
            <p class="mc-field__error" id="${describedBy}-error" role="alert" hidden></p>
        </div>
    `;
}

function buildModal() {
    const modal = document.createElement('div');

    modal.className = 'modal mc-modal fade';
    modal.id = MODAL_ID;
    modal.tabIndex = -1;
    modal.setAttribute('aria-labelledby', `${MODAL_ID}-title`);
    modal.setAttribute('aria-hidden', 'true');

    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <form class="mc-quick-order-form" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title mc-modal__title" id="${MODAL_ID}-title"></h2>
                        <button type="button" class="mc-modal__close" data-mc-action="close-modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${fieldMarkup('quantity', 'Quantity', 'number', 'mc-quick-order-quantity')}
                        ${fieldMarkup('name', 'Name', 'text', 'mc-quick-order-name')}
                        ${fieldMarkup('telephone', 'Phone', 'tel', 'mc-quick-order-telephone')}
                        ${fieldMarkup('email', 'Email', 'email', 'mc-quick-order-email')}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="mc-button mc-button--secondary" data-mc-action="close-modal">Cancel</button>
                        <button type="submit" class="mc-button mc-button--primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const quantityInput = modal.querySelector('[name="quantity"]');
    quantityInput.min = '1';
    quantityInput.value = '1';

    modal.querySelector('form').addEventListener('submit', (event) => {
        event.preventDefault();
        handleSubmit(modal);
    });

    return modal;
}

function getModal() {
    return document.getElementById(MODAL_ID) || buildModal();
}

function clearErrors(modal) {
    modal.querySelectorAll('.mc-field').forEach((field) => {
        field.classList.remove('has-error');
        const control = field.querySelector('.mc-field__control');
        const error = field.querySelector('.mc-field__error');

        control.removeAttribute('aria-invalid');
        error.hidden = true;
        error.textContent = '';
    });
}

function showErrors(modal, errors) {
    Object.entries(errors).forEach(([name, message]) => {
        // `contact` covers both telephone and email (at least one is
        // required) - surfaced on telephone, the first of the pair.
        const fieldName = name === 'contact' ? 'telephone' : name;
        const field = modal.querySelector(`[data-mc-quick-order-field="${fieldName}"]`);

        if (!field) {
            return;
        }

        field.classList.add('has-error');

        const control = field.querySelector('.mc-field__control');
        const error = field.querySelector('.mc-field__error');

        control.setAttribute('aria-invalid', 'true');
        error.hidden = false;
        error.textContent = message;
    });
}

async function handleSubmit(modal) {
    const form = modal.querySelector('form');
    const submitButton = form.querySelector('button[type="submit"]');
    const productId = form.dataset.productId;

    clearErrors(modal);
    submitButton.disabled = true;
    submitButton.classList.add('is-loading');

    try {
        const result = await submitQuickOrder(productId, {
            quantity: form.querySelector('[name="quantity"]').value,
            name: form.querySelector('[name="name"]').value,
            telephone: form.querySelector('[name="telephone"]').value,
            email: form.querySelector('[name="email"]').value,
        });

        if (result.redirected) {
            window.location.href = result.url;
            return;
        }

        if (result.errors) {
            showErrors(modal, result.errors);
            return;
        }

        Modal.getInstance(modal)?.hide();
        showToast(createToast({ title: 'Quick Order', message: result.message, variant: 'success' }));
    } catch {
        showToast(createToast({ title: 'Quick Order', message: 'Could not submit your request', variant: 'danger' }));
    } finally {
        submitButton.disabled = false;
        submitButton.classList.remove('is-loading');
    }
}

function openQuickOrder(button) {
    const modal = getModal();
    const form = modal.querySelector('form');

    clearErrors(modal);
    form.reset();
    form.querySelector('[name="quantity"]').value = '1';
    form.dataset.productId = button.dataset.productId ?? '';
    modal.querySelector('.mc-modal__title').textContent = button.dataset.productName || 'Quick Order';

    Modal.getOrCreateInstance(modal).show();
}

export function initQuickOrderActions(root = document) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-mc-action="quick-order"]');

        if (!button) {
            return;
        }

        openQuickOrder(button);
    });
}
