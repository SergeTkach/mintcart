/**
 * Live total price on the product page (`[data-mc-product-price]` inside
 * `[data-mc-product-form]`).
 *
 * On quantity or option change, posts the form (product_id, quantity,
 * option[...]) to `mintcart/price`, which prices it by the cart's own rules
 * (quantity discount tiers, special, option add-ons, tax, currency) and
 * returns formatted totals. Nothing is calculated or formatted here, so the
 * shown price cannot drift from what the cart charges.
 *
 * Hooks: `[data-mc-price-current]` (every one on the page - the buy bar
 * has one too), `[data-mc-price-original]`, `[data-mc-price-tax]`.
 *
 * Contract: docs/developer/commerce/product-price.md
 */

const ENDPOINT = 'index.php?route=mintcart/price';
const DEBOUNCE_MS = 150;

function formData(form, productId) {
    const data = new FormData();

    data.append('product_id', productId);

    form.querySelectorAll('[name="quantity"], [name^="option["]').forEach((field) => {
        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
            return;
        }

        if (field.type === 'file' || field.disabled) {
            return;
        }

        data.append(field.name, field.value);
    });

    return data;
}

function setText(selector, value) {
    document.querySelectorAll(selector).forEach((element) => {
        element.textContent = value;
    });
}

export function initProductPrice(root = document) {
    const form = root.querySelector('[data-mc-product-form]');
    const price = form?.querySelector('[data-mc-product-price]');
    const productId = price?.dataset.mcProductId;

    if (!price || !productId) {
        return;
    }

    let timer = 0;
    let controller = null;

    const refresh = async () => {
        controller?.abort();
        controller = new AbortController();
        price.classList.add('is-loading');

        try {
            const response = await fetch(ENDPOINT, {
                method: 'POST',
                body: formData(form, productId),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            const json = await response.json();

            if (!json.current) {
                return;
            }

            setText('[data-mc-price-current]', json.current);

            // `original` (struck-through regular total) only comes when a
            // special or a quantity tier makes the total cheaper.
            document.querySelectorAll('[data-mc-price-original]').forEach((element) => {
                element.textContent = json.original || '';
                element.hidden = !json.original;
            });
            price.classList.toggle('mc-price--special', Boolean(json.original));

            if (json.tax) {
                setText('[data-mc-price-tax]', json.tax);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                // Keep the last shown price; the cart still prices correctly.
                console.warn('[mc-product-price]', error);
            }
        } finally {
            price.classList.remove('is-loading');
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, DEBOUNCE_MS);
    };

    // The quantity stepper dispatches a bubbling `change`; typing in the
    // field fires `input`. Options fire `change`.
    form.addEventListener('change', (event) => {
        if (event.target.matches('[name="quantity"], [name^="option["]')) {
            schedule();
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[name="quantity"]')) {
            schedule();
        }
    });

    // The server-rendered price is for one unit, no options. When the
    // starting quantity is not 1 (product minimum), show the real total.
    const quantity = form.querySelector('[name="quantity"]');

    if (quantity && Number(quantity.value) !== 1) {
        refresh();
    }
}
