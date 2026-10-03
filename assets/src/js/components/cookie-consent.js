/**
 * mc-cookie-consent - storefront cookie notice
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §11 "Commerce Essentials", P0
 * "cookie consent" - the one item in that list with no MintCart
 * implementation anywhere in this repo yet, per
 * `docs/developer/components/cookie-consent.md`).
 *
 * Deliberately a plain acknowledgement banner ("we use cookies, OK") with
 * a single Accept action, not a granular cookie-category consent-management
 * platform - that is explicit CLAUDE.md §16 scope creep for MintCart 1.0
 * ("large visual page builder" / "100+ widgets"-style over-building is the
 * same principle applied here to a consent UI). A store that needs
 * per-category opt-in can layer that on top of the `mc:cookie-consent:*`
 * event contract below without this component changing shape.
 *
 * Markup contract (`data-mc-cookie-consent-*` are read-only hooks this
 * module queries - CLAUDE.md §14):
 *
 *   <div class="mc-cookie-consent" role="region" aria-label="…" data-mc-cookie-consent>
 *     <p class="mc-cookie-consent__message">…</p>
 *     <div class="mc-cookie-consent__actions">
 *       <a class="mc-cookie-consent__link" href="…">…</a>            (optional)
 *       <button type="button" class="mc-button mc-button--primary mc-button--sm"
 *               data-mc-action="accept-cookie-consent">…</button>
 *     </div>
 *   </div>
 *
 * Two ways this markup can reach the page, both handled by the same
 * `accept-cookie-consent` delegated handler:
 *
 * 1. **Today:** no template/controller wires this component to any page
 *    yet (`CLAUDE.md` §85 - no admin-configurable text, no per-language
 *    strings), so `initCookieConsent()` builds the banner above from a
 *    hardcoded English default and appends it to `document.body` itself,
 *    the moment it runs (catalog.js already loads unconditionally on every
 *    Mint-aware page since Phase 7's asset-enqueue work - a real consumer
 *    with no template change needed).
 * 2. **Future:** once a real MintCart Settings facade exists for the
 *    message/link text (mirroring `ControllerMintcartSettingsDiagnostics`'s
 *    "own isolated setting group" shape), a controller can render this same
 *    markup server-side (translated, admin-editable) instead. `initCookieConsent()`
 *    detects an already-present `[data-mc-cookie-consent]` element and
 *    reuses it rather than injecting a second one - so that future increment
 *    needs no JS change, only a Twig partial.
 *
 * Consent is remembered in `localStorage` (`mc-cookie-consent` key,
 * `"accepted"` or absent) - first `localStorage` use anywhere in this
 * codebase, so every read/write is wrapped in `try`/`catch`: a store in
 * Private Browsing mode (Safari throws on `setItem`) or with storage
 * disabled must still render a working, dismissible banner, it just won't
 * be remembered across reloads (a graceful degradation, not a broken page).
 *
 * `mc:cookie-consent:shown` and `mc:cookie-consent:accepted` (both bubbling
 * `CustomEvent`s, dispatched on the `.mc-cookie-consent` element) let other
 * code react - e.g. a future analytics/marketing script waiting for
 * consent before it loads.
 *
 * Contract: docs/developer/components/cookie-consent.md
 */

const STORAGE_KEY = 'mc-cookie-consent';
const ACCEPTED_VALUE = 'accepted';
const ROOT_SELECTOR = '[data-mc-cookie-consent]';
const ACCEPT_ACTION = 'accept-cookie-consent';

function hasConsented() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === ACCEPTED_VALUE;
    } catch (error) {
        return false;
    }
}

function rememberConsent() {
    try {
        window.localStorage.setItem(STORAGE_KEY, ACCEPTED_VALUE);
    } catch (error) {
        // Private Browsing / disabled storage - the banner still closes for
        // this page view, it just won't be remembered on the next one.
    }
}

/**
 * @param {{message?: string, acceptLabel?: string, policyUrl?: string, policyLabel?: string}} options
 * @returns {HTMLElement}
 */
export function createCookieConsentBanner(options = {}) {
    const {
        message = 'We use cookies to make this store work and to improve your experience. By continuing to browse, you agree to our use of cookies.',
        acceptLabel = 'Accept',
        policyUrl = '',
        policyLabel = 'Learn more',
    } = options;

    const root = document.createElement('div');
    root.className = 'mc-cookie-consent';
    root.setAttribute('role', 'region');
    root.setAttribute('aria-label', 'Cookie notice');
    root.setAttribute('data-mc-cookie-consent', '');

    const messageEl = document.createElement('p');
    messageEl.className = 'mc-cookie-consent__message';
    messageEl.textContent = message;
    root.appendChild(messageEl);

    const actions = document.createElement('div');
    actions.className = 'mc-cookie-consent__actions';

    if (policyUrl) {
        const link = document.createElement('a');
        link.className = 'mc-cookie-consent__link';
        link.href = policyUrl;
        link.textContent = policyLabel;
        actions.appendChild(link);
    }

    const acceptButton = document.createElement('button');
    acceptButton.type = 'button';
    acceptButton.className = 'mc-button mc-button--primary mc-button--sm';
    acceptButton.setAttribute('data-mc-action', ACCEPT_ACTION);
    acceptButton.textContent = acceptLabel;
    actions.appendChild(acceptButton);

    root.appendChild(actions);

    return root;
}

function closeBanner(root) {
    root.remove();
}

let delegatedHandlerBound = false;

function bindDelegatedAcceptHandler() {
    if (delegatedHandlerBound) {
        return;
    }

    delegatedHandlerBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest(`[data-mc-action="${ACCEPT_ACTION}"]`);

        if (!button) {
            return;
        }

        const root = button.closest(ROOT_SELECTOR);

        if (!root) {
            return;
        }

        rememberConsent();

        root.dispatchEvent(new CustomEvent('mc:cookie-consent:accepted', { bubbles: true }));

        closeBanner(root);
    });
}

/**
 * @param {{message?: string, acceptLabel?: string, policyUrl?: string, policyLabel?: string}} options
 *        Only used when this call builds its own banner (no
 *        `[data-mc-cookie-consent]` already present in the page) - a
 *        server-rendered banner's own markup always wins, see the module
 *        docblock's "Future" case.
 */
export function initCookieConsent(options = {}) {
    bindDelegatedAcceptHandler();

    if (hasConsented()) {
        // A future server-rendered banner could still be present for a
        // returning visitor whose language cookie beat their consent
        // cookie into existence; remove it rather than leave a stale
        // "accept" prompt on screen for someone who already accepted.
        document.querySelectorAll(ROOT_SELECTOR).forEach(closeBanner);

        return;
    }

    let root = document.querySelector(ROOT_SELECTOR);

    if (!root) {
        root = createCookieConsentBanner(options);
        document.body.appendChild(root);
    }

    root.dispatchEvent(new CustomEvent('mc:cookie-consent:shown', { bubbles: true }));
}
