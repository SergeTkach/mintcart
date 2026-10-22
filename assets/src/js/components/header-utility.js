/**
 * Header utility group (language, currency, account, wishlist).
 *
 * A theme marks the group `[data-mc-header-utility]` and gives the mobile
 * drawer a `[data-mc-header-utility-slot]`. Below the `lg` breakpoint the
 * group moves into the slot (inside the menu drawer), above it moves back to
 * where it was. It is the same DOM node in both places - the legacy
 * language/currency widgets carry ids and handlers (`#form-currency`) that a
 * second copy would break.
 */
const MOBILE_QUERY = '(max-width: 991.98px)';

export function initHeaderUtility(root = document) {
    const group = root.querySelector('[data-mc-header-utility]');
    const slot = root.querySelector('[data-mc-header-utility-slot]');

    if (!group || !slot || typeof window.matchMedia !== 'function') {
        return;
    }

    // A comment marks the group's home so it can be put back exactly there.
    const home = document.createComment('mc-header-utility');

    group.before(home);

    const media = window.matchMedia(MOBILE_QUERY);

    const place = () => {
        if (media.matches) {
            slot.appendChild(group);
        } else {
            home.after(group);
        }
    };

    place();
    media.addEventListener('change', place);
}
