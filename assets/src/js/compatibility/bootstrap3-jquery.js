/**
 * Bootstrap 3 -> Bootstrap 5 JavaScript compatibility for legacy markup.
 *
 * Mint-aware storefront themes load Bootstrap 5 instead of Bootstrap 3
 * (ADR 010), but ~160 `default/` templates, `common.js` and third-party
 * extensions still speak Bootstrap 3. Two gaps would silently break them
 * (audit: docs/developer/storefront-bootstrap5-migration.md):
 *
 * 1. `data-toggle` / `data-target` / `data-dismiss` / `data-parent` /
 *    `data-placement` - Bootstrap 5 only reads the `data-bs-*` spelling.
 *    Mirrored onto the BS5 name, for the initial DOM and for HTML inserted
 *    later (common.js and payment widgets inject alerts/modals via AJAX).
 * 2. `$(el).button('loading' | 'reset')` with `data-loading-text` - the
 *    Bootstrap 3 Button plugin, gone since Bootstrap 4, used ~157 times.
 *
 * `.modal('show')` / `.tooltip({...})` need nothing: Bootstrap 5 still
 * registers its own jQuery interface when `window.jQuery` exists.
 *
 * Legacy-compatibility code only (CLAUDE.md §7): it touches jQuery solely
 * to keep legacy callers working and does nothing when jQuery is absent.
 * Mint-owned code never calls into it.
 */

const LEGACY_ATTRIBUTES = ['toggle', 'target', 'dismiss', 'parent', 'placement'];

const LEGACY_SELECTOR = LEGACY_ATTRIBUTES.map((name) => `[data-${name}]`).join(',');

function mirrorElement(element) {
    for (const name of LEGACY_ATTRIBUTES) {
        const legacy = element.getAttribute(`data-${name}`);

        if (legacy !== null && !element.hasAttribute(`data-bs-${name}`)) {
            element.setAttribute(`data-bs-${name}`, legacy);
        }
    }
}

/**
 * Copies Bootstrap 3 `data-*` API attributes onto their `data-bs-*` names
 * for `root` itself (when it is an element) and every matching descendant.
 * An existing `data-bs-*` value always wins.
 */
export function mirrorBootstrap3Attributes(root) {
    if (root.nodeType === 1 && root.matches(LEGACY_SELECTOR)) {
        mirrorElement(root);
    }

    root.querySelectorAll(LEGACY_SELECTOR).forEach(mirrorElement);
}

/**
 * Bootstrap 3 Button plugin's `loading`/`reset` states, same observable
 * behaviour: the text is swapped for `data-loading-text` (`data-<state>-text`
 * in general), the original is restored on `reset`, and the control is
 * disabled while loading. Any other argument (Bootstrap 5's `'toggle'`) is
 * passed to `fallback`, the plugin this one replaces.
 */
export function createLegacyButtonPlugin($, fallback) {
    return function legacyButton(state) {
        if (typeof state !== 'string' || state === 'toggle') {
            return fallback ? fallback.apply(this, arguments) : this;
        }

        return this.each(function () {
            const $el = $(this);
            const method = $el.is('input') ? 'val' : 'html';

            if ($el.data('resetText') == null) {
                $el.data('resetText', $el[method]());
            }

            const text = $el.data(`${state}Text`);

            $el[method](text == null ? $el.data('resetText') : text);

            if (state === 'loading') {
                $el.addClass('disabled').attr('disabled', 'disabled').prop('disabled', true);
            } else {
                $el.removeClass('disabled').removeAttr('disabled').prop('disabled', false);
            }
        });
    };
}

/**
 * Must run after Bootstrap 5 has registered its jQuery plugins (it does so
 * on DOMContentLoaded), otherwise its own `$.fn.button` would replace ours.
 * catalog.js calls this from its boot(), which is registered after the
 * `import 'bootstrap'` side effect.
 */
export function initBootstrap3Compat(doc = document, win = window) {
    mirrorBootstrap3Attributes(doc.documentElement);

    if (typeof win.MutationObserver === 'function') {
        new win.MutationObserver((mutations) => {
            for (const mutation of mutations) {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        mirrorBootstrap3Attributes(node);
                    }
                });
            }
        }).observe(doc.documentElement, { childList: true, subtree: true });
    }

    const $ = win.jQuery;

    if ($ && $.fn) {
        $.fn.button = createLegacyButtonPlugin($, $.fn.button);
    }
}
