/**
 * mc-reviews - product page review list + write-review form.
 *
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §11 "integration-ready reviews area",
 * flagged as the Phase 6/8 product detail page's own deferred "Reviews tab
 * is a read-only placeholder" gap - see `docs/upstream-diff.md`'s
 * `product.php` entry and `docs/developer/components/reviews.md`.)
 *
 * Talks directly to OpenCart's own stock, unmodified
 * `product/product/review` (GET, returns a rendered HTML fragment - this
 * theme's own `mint_default/template/product/review.twig`) and
 * `product/product/write` (POST, returns `{error}` or `{success}` JSON) -
 * no new MintCart backend route. The GET endpoint already returns
 * server-rendered HTML (not JSON), so this module injects it directly via
 * `innerHTML` instead of re-implementing `Pagination::render()`'s markup
 * in JS - the same "reuse OpenCart, don't rewrite it" approach every other
 * `mint_default` template takes (`CLAUDE.md` §10/§84).
 *
 * Markup contract (`data-mc-reviews-*` are read-only hooks this module
 * queries - CLAUDE.md §14):
 *
 *   <div class="mc-reviews" data-mc-reviews data-mc-reviews-product-id="42">
 *     <div class="mc-reviews__list" data-mc-reviews-list></div>
 *     <form data-mc-reviews-form>
 *       ...name/text/rating fields...
 *       <div data-mc-reviews-message></div>
 *       <button type="submit" data-mc-reviews-submit>...</button>
 *     </form>
 *   </div>
 *
 * Behavior:
 * - On init, eagerly fetches page 1 of the review list (mirrors stock
 *   `default` theme's own unconditional `$('#review').load(...)` on page
 *   load - not lazy on tab show, same UX).
 * - Clicking a pagination link inside `[data-mc-reviews-list]` is
 *   intercepted (event delegation) and re-fetches that page instead of a
 *   full navigation - `Pagination::render()`'s own `<a href>` targets stay
 *   real links, so this still works with JS disabled (progressive
 *   enhancement, same spirit as `mc-search-box`'s "view all" link).
 * - Submitting `[data-mc-reviews-form]` posts via `fetch()`/`FormData` and
 *   shows the single `error`/`success` message the stock endpoint returns
 *   as an `mc-alert` inside `[data-mc-reviews-message]` (the endpoint
 *   itself only ever returns one message, not per-field errors, so no
 *   `has-error` field highlighting is attempted). On success the form
 *   resets but the list is not re-fetched - a new review needs admin
 *   approval first (`text_success`), so it would not appear yet anyway.
 *
 * Contract: docs/developer/components/reviews.md
 */

const SELECTORS = {
    root: '[data-mc-reviews]',
    list: '[data-mc-reviews-list]',
    pagination: '[data-mc-reviews-pagination]',
    form: '[data-mc-reviews-form]',
    message: '[data-mc-reviews-message]',
    submit: '[data-mc-reviews-submit]',
};

function alertMarkup(message, variant) {
    return `
        <div class="mc-alert mc-alert--${variant}" role="${variant === 'danger' ? 'alert' : 'status'}">
            <div class="mc-alert__body">
                <p class="mc-alert__message">${message}</p>
            </div>
        </div>
    `;
}

function reviewUrl(productId, page) {
    return `index.php?route=product/product/review&product_id=${encodeURIComponent(productId)}&page=${encodeURIComponent(page)}`;
}

async function loadReviews(listEl, url) {
    listEl.closest(SELECTORS.root)?.classList.add('is-loading');

    try {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        listEl.innerHTML = await response.text();
    } catch {
        // Leave whatever was already rendered (initial empty-state markup
        // from product.twig, or the previous page) rather than clearing it.
    } finally {
        listEl.closest(SELECTORS.root)?.classList.remove('is-loading');
    }
}

function pageFromHref(href) {
    try {
        return new URL(href, window.location.href).searchParams.get('page') ?? '1';
    } catch {
        return '1';
    }
}

async function handleSubmit(root, form) {
    const productId = root.dataset.mcReviewsProductId;
    const submitButton = form.querySelector(SELECTORS.submit);
    const messageEl = form.querySelector(SELECTORS.message);

    if (messageEl) {
        messageEl.innerHTML = '';
    }

    submitButton?.setAttribute('disabled', 'disabled');
    submitButton?.classList.add('is-loading');

    try {
        const response = await fetch(`index.php?route=product/product/write&product_id=${encodeURIComponent(productId)}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });

        const json = await response.json();

        if (messageEl) {
            if (json.error) {
                messageEl.innerHTML = alertMarkup(json.error, 'danger');
            } else if (json.success) {
                messageEl.innerHTML = alertMarkup(json.success, 'success');
                form.reset();
            }
        }
    } catch {
        if (messageEl) {
            messageEl.innerHTML = alertMarkup('Could not submit your review.', 'danger');
        }
    } finally {
        submitButton?.removeAttribute('disabled');
        submitButton?.classList.remove('is-loading');
    }
}

function initReview(root) {
    const productId = root.dataset.mcReviewsProductId;
    const listEl = root.querySelector(SELECTORS.list);
    const form = root.querySelector(SELECTORS.form);

    if (!productId || !listEl) {
        return;
    }

    loadReviews(listEl, reviewUrl(productId, 1));

    listEl.addEventListener('click', (event) => {
        const link = event.target.closest(`${SELECTORS.pagination} a[href]`);

        if (!link) {
            return;
        }

        event.preventDefault();
        loadReviews(listEl, reviewUrl(productId, pageFromHref(link.getAttribute('href'))));
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        handleSubmit(root, form);
    });
}

export function initReviews(root = document) {
    root.querySelectorAll(SELECTORS.root).forEach((el) => {
        if (el.mcReviewsInitialized) {
            return;
        }

        el.mcReviewsInitialized = true;
        initReview(el);
    });
}
