/**
 * mc-viewed-products - "Recently viewed" storefront strip
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §11 "viewed products", Commerce
 * Essentials, priority P0).
 *
 * Two independent jobs, both handled by `initViewedProducts()`:
 *
 * 1. **Record** — on any page that renders the current product's own
 *    add-to-cart button (`[data-mc-product-form] [data-mc-action="add-to-cart"][data-product-id]`,
 *    the same hook `product-card.js`/`quick-order.js` already read), append
 *    that product id to a small `localStorage` history, most-recent-first,
 *    deduplicated, capped at `MAX_HISTORY`. No PHP/Twig change was needed to
 *    expose the id — `product.twig` already carries it on that button for
 *    the add-to-cart flow itself.
 * 2. **Render** — for every `[data-mc-viewed-products]` root found in the
 *    document, read that history (minus the current page's own product,
 *    named in `data-mc-viewed-products-exclude`), and if any ids remain,
 *    fetch `mintcart/viewed/products` for live name/price/image/availability
 *    (a since-disabled or deleted product silently drops out server-side —
 *    `docs/developer/commerce/viewed-products.md`) and render a strip of
 *    simplified `mc-product-card`-style items. The root stays `hidden` when
 *    there is nothing to show, same empty-state approach `mc-search-box`
 *    uses for its own results panel.
 *
 * Deliberately reuses `mc-product-card__media`/`__body`/`__title`/`mc-price`
 * classes rather than a second card look (`MINTCART_IMPLEMENTATION_GUIDE.md`
 * §9) — this strip omits wishlist/compare/add-to-cart/quick-order entirely
 * (the endpoint's item shape has no `actions`/`minimum` to drive them), so it
 * is a link-through card, not a full `mc-product-card`.
 *
 * Contract: docs/developer/components/viewed-products.md
 */

const SELECTORS = {
    productForm: '[data-mc-product-form] [data-mc-action="add-to-cart"][data-product-id]',
    root: '[data-mc-viewed-products]',
    list: '[data-mc-viewed-products-list]',
};

const STORAGE_KEY = 'mc:viewed:product_ids';
const MAX_HISTORY = 12;
const MAX_DISPLAY = 8;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function readHistory() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.filter((id) => Number.isInteger(id) && id > 0) : [];
    } catch (error) {
        return [];
    }
}

function writeHistory(ids) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch (error) {
        // localStorage can throw (private mode quota, disabled storage) -
        // recording history is a progressive enhancement, never a required
        // step for the current page to work.
    }
}

export function recordViewedProduct(root = document) {
    const button = root.querySelector(SELECTORS.productForm);
    const productId = button ? parseInt(button.dataset.productId, 10) : NaN;

    if (!Number.isInteger(productId) || productId <= 0) {
        return;
    }

    const history = readHistory().filter((id) => id !== productId);
    history.unshift(productId);
    writeHistory(history.slice(0, MAX_HISTORY));
}

function renderItems(listEl, items) {
    listEl.innerHTML = '';

    items.forEach((item) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-lg-3';

        const priceHtml = item.special
            ? `<span class="mc-price__current">${escapeHtml(item.special)}</span>
               <span class="mc-price__original">${escapeHtml(item.price)}</span>`
            : `<span class="mc-price__current">${escapeHtml(item.price)}</span>`;

        col.innerHTML = `
            <article class="mc-product-card mc-viewed-products__item">
                <div class="mc-product-card__media">
                    <a class="mc-product-card__media-link" href="${escapeHtml(item.url)}" tabindex="-1" aria-hidden="true">
                        <img class="mc-product-card__image" src="${escapeHtml(item.image)}" alt="" loading="lazy">
                    </a>
                </div>
                <div class="mc-product-card__body">
                    <h3 class="mc-product-card__title">
                        <a href="${escapeHtml(item.url)}">${escapeHtml(item.name)}</a>
                    </h3>
                    ${item.price ? `<div class="mc-price${item.special ? ' mc-price--special' : ''}">${priceHtml}</div>` : ''}
                </div>
            </article>
        `;

        listEl.appendChild(col);
    });
}

async function renderViewedProducts(root) {
    const listEl = root.querySelector(SELECTORS.list);

    if (!listEl) {
        return;
    }

    const excludeId = parseInt(root.dataset.mcViewedProductsExclude, 10);
    const ids = readHistory()
        .filter((id) => id !== excludeId)
        .slice(0, MAX_DISPLAY);

    if (ids.length === 0) {
        root.hidden = true;
        return;
    }

    try {
        const query = ids.map((id) => `product_id[]=${id}`).join('&');
        const response = await fetch(`index.php?route=mintcart/viewed/products&${query}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const result = await response.json();
        const items = result.items ?? [];

        if (items.length === 0) {
            root.hidden = true;
            return;
        }

        renderItems(listEl, items);
        root.hidden = false;

        root.dispatchEvent(new CustomEvent('mc:viewed-products:rendered', {
            bubbles: true,
            detail: { items },
        }));
    } catch (error) {
        root.hidden = true;
    }
}

export function initViewedProducts(root = document) {
    recordViewedProduct(root);

    root.querySelectorAll(SELECTORS.root).forEach((el) => {
        renderViewedProducts(el);
    });
}
